<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\TranslationItem;
use App\Models\TranslationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TranslationService
{
    public function __construct(
        protected LMStudioService $lmStudio,
        protected CodePreservationService $codePreserver
    ) {}

    /**
     * Get real-time stats of the translation system.
     *
     * @return array
     */
    public function getStats(): array
    {
        $total = TranslationItem::count();
        $completed = TranslationItem::where('status', 'completed')->count();
        $pending = TranslationItem::where('status', 'pending')->count();
        $failed = TranslationItem::where('status', 'failed')->count();
        $translating = TranslationItem::where('status', 'translating')->count();

        // Breakdown by type
        $postsTotal = TranslationItem::where('item_type', 'post')->count();
        $postsCompleted = TranslationItem::where('item_type', 'post')->where('status', 'completed')->count();

        $categoriesTotal = TranslationItem::where('item_type', 'category')->count();
        $categoriesCompleted = TranslationItem::where('item_type', 'category')->where('status', 'completed')->count();

        $tagsTotal = TranslationItem::where('item_type', 'tag')->count();
        $tagsCompleted = TranslationItem::where('item_type', 'tag')->where('status', 'completed')->count();

        $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        $runnerState = SystemSetting::get('runner_state', 'idle');
        $currentTaskId = SystemSetting::get('current_task_id');
        $currentItem = $currentTaskId ? TranslationItem::find($currentTaskId) : null;

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'failed' => $failed,
            'translating' => $translating,
            'percentage' => $percent,
            'runner_state' => $runnerState,
            'current_item' => $currentItem,
            'breakdown' => [
                'posts' => [
                    'total' => $postsTotal,
                    'completed' => $postsCompleted,
                    'remaining' => $postsTotal - $postsCompleted,
                ],
                'categories' => [
                    'total' => $categoriesTotal,
                    'completed' => $categoriesCompleted,
                    'remaining' => $categoriesTotal - $categoriesCompleted,
                ],
                'tags' => [
                    'total' => $tagsTotal,
                    'completed' => $tagsCompleted,
                    'remaining' => $tagsTotal - $tagsCompleted,
                ],
            ],
        ];
    }

    /**
     * Sync posts, categories, and tags from WordPress MySQL into SQLite.
     *
     * @return array{posts_synced: int, categories_synced: int, tags_synced: int, total_in_db: int}
     */
    public function syncFromWordPress(): array
    {
        $postsSynced = 0;
        $categoriesSynced = 0;
        $tagsSynced = 0;
        $now = now()->toDateTimeString();

        // 1. Sync Published Posts in batches using UPSERT
        DB::connection('wp_mysql')
            ->table('wp_posts')
            ->where('post_type', 'post')
            ->where('post_status', 'publish')
            ->select('ID', 'post_title', 'post_content', 'post_title_en', 'post_content_en', 'post_translated_at')
            ->orderBy('ID', 'asc')
            ->chunk(150, function ($posts) use (&$postsSynced, $now) {
                $batch = [];
                foreach ($posts as $p) {
                    $isCompleted = !empty($p->post_title_en) && !empty($p->post_content_en);
                    $batch[] = [
                        'item_type' => 'post',
                        'source_id' => $p->ID,
                        'title_ja' => $p->post_title,
                        'title_en' => $p->post_title_en,
                        'content_ja' => $p->post_content,
                        'content_en' => $p->post_content_en,
                        'status' => $isCompleted ? 'completed' : 'pending',
                        'translated_at' => $p->post_translated_at,
                        'synced_to_wp_at' => $isCompleted ? $p->post_translated_at : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::connection('sqlite')->table('translation_items')->upsert(
                    $batch,
                    ['item_type', 'source_id'],
                    ['title_ja', 'content_ja', 'updated_at']
                );
                $postsSynced += count($batch);
            });

        // 2. Sync Categories
        $wpCategories = DB::connection('wp_mysql')
            ->table('wp_terms as t')
            ->join('wp_term_taxonomy as tt', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', 'category')
            ->select('t.term_id', 't.name', 't.name_en', 't.term_translated_at')
            ->get();

        $catBatch = [];
        $directCatUpdates = [];
        foreach ($wpCategories as $cat) {
            $nameJa = $cat->name;
            $nameEn = $cat->name_en;

            // 英字、数字、記号の組み合わせの文字ならLM Studioを介さずenへ設定
            if (empty($nameEn) && $this->isAlphaNumericSymbolOnly($nameJa)) {
                $nameEn = mb_convert_kana(trim($nameJa), 'as', 'UTF-8');
                $directCatUpdates[] = [
                    'term_id' => $cat->term_id,
                    'name_en' => $nameEn,
                ];
            }

            $isCompleted = !empty($nameEn);
            $catBatch[] = [
                'item_type' => 'category',
                'source_id' => $cat->term_id,
                'title_ja' => $cat->name,
                'title_en' => $nameEn,
                'content_ja' => null,
                'content_en' => null,
                'status' => $isCompleted ? 'completed' : 'pending',
                'translated_at' => $isCompleted ? ($cat->term_translated_at ?: $now) : null,
                'synced_to_wp_at' => $isCompleted ? ($cat->term_translated_at ?: $now) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // WordPress MySQLの英数記号カテゴリへ直接反映
        foreach ($directCatUpdates as $update) {
            DB::connection('wp_mysql')
                ->table('wp_terms')
                ->where('term_id', $update['term_id'])
                ->update([
                    'name_en' => $update['name_en'],
                    'term_translated_at' => $now,
                ]);
        }

        if (!empty($catBatch)) {
            DB::connection('sqlite')->table('translation_items')->upsert(
                $catBatch,
                ['item_type', 'source_id'],
                ['title_ja', 'title_en', 'status', 'translated_at', 'synced_to_wp_at', 'updated_at']
            );
            $categoriesSynced = count($catBatch);
        }

        // 3. Sync Tags (in chunks)
        DB::connection('wp_mysql')
            ->table('wp_terms as t')
            ->join('wp_term_taxonomy as tt', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', 'post_tag')
            ->select('t.term_id', 't.name', 't.name_en', 't.term_translated_at')
            ->orderBy('t.term_id', 'asc')
            ->chunk(200, function ($tags) use (&$tagsSynced, $now) {
                $tagBatch = [];
                $directTagUpdates = [];
                foreach ($tags as $tag) {
                    $nameJa = $tag->name;
                    $nameEn = $tag->name_en;

                    // 英字、数字、記号の組み合わせの文字ならLM Studioを介さずenへ設定
                    if (empty($nameEn) && $this->isAlphaNumericSymbolOnly($nameJa)) {
                        $nameEn = mb_convert_kana(trim($nameJa), 'as', 'UTF-8');
                        $directTagUpdates[] = [
                            'term_id' => $tag->term_id,
                            'name_en' => $nameEn,
                        ];
                    }

                    $isCompleted = !empty($nameEn);
                    $tagBatch[] = [
                        'item_type' => 'tag',
                        'source_id' => $tag->term_id,
                        'title_ja' => $tag->name,
                        'title_en' => $nameEn,
                        'content_ja' => null,
                        'content_en' => null,
                        'status' => $isCompleted ? 'completed' : 'pending',
                        'translated_at' => $isCompleted ? ($tag->term_translated_at ?: $now) : null,
                        'synced_to_wp_at' => $isCompleted ? ($tag->term_translated_at ?: $now) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // WordPress MySQLの英数記号タグへ直接反映
                foreach ($directTagUpdates as $update) {
                    DB::connection('wp_mysql')
                        ->table('wp_terms')
                        ->where('term_id', $update['term_id'])
                        ->update([
                            'name_en' => $update['name_en'],
                            'term_translated_at' => $now,
                        ]);
                }

                DB::connection('sqlite')->table('translation_items')->upsert(
                    $tagBatch,
                    ['item_type', 'source_id'],
                    ['title_ja', 'title_en', 'status', 'translated_at', 'synced_to_wp_at', 'updated_at']
                );
                $tagsSynced += count($tagBatch);
            });

        // 既存のpendingデータに対しても英数記号の直接反映を実行
        $batchResult = $this->batchUpdateAlphaNumericItems();

        $totalInDb = TranslationItem::count();

        $directMsg = $batchResult['total'] > 0 ? " (英数記号 {$batchResult['total']}件 を直接反映)" : "";
        TranslationLog::log(
            "WordPressから同期完了: 記事 {$postsSynced}件, カテゴリ {$categoriesSynced}件, タグ {$tagsSynced}件 (総キュー数: {$totalInDb}件){$directMsg}",
            'info'
        );

        return [
            'posts_synced' => $postsSynced,
            'categories_synced' => $categoriesSynced,
            'tags_synced' => $tagsSynced,
            'total_in_db' => $totalInDb,
            'alphanumeric_direct_updated' => $batchResult['total'],
        ];
    }

    /**
     * Determine if a text consists only of letters, numbers, symbols, and whitespace
     * (i.e. ASCII printable characters without Japanese/CJK characters).
     *
     * @param string|null $text
     * @return bool
     */
    public function isAlphaNumericSymbolOnly(?string $text): bool
    {
        if ($text === null) {
            return false;
        }

        // HTMLエンティティ（&amp;, &quot;, &#038; 等）をデコード
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $trimmed = trim($decoded);
        if ($trimmed === '') {
            return false;
        }

        // 全角英数字および全角スペースを半角に正規化
        $normalized = mb_convert_kana($trimmed, 'as', 'UTF-8');

        // 日本語文字（ひらがな、カタカナ、漢字、CJK句読点等）が含まれている場合はfalse
        // \x{3040}-\x{309F}: ひらがな
        // \x{30A0}-\x{30FF}: カタカナ
        // \x{4E00}-\x{9FAF}: 漢字 (CJK統合漢字)
        // \x{3400}-\x{4DBF}: CJK統合漢字拡張A
        // \x{3000}-\x{303F}: CJKの記号および句読点
        if (preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FAF}\x{3400}-\x{4DBF}\x{3000}-\x{303F}]/u', $normalized)) {
            return false;
        }

        // 印字可能ASCII文字 (0x20〜0x7E) および改行・タブ等の空白文字のみで構成されているか
        if (!preg_match('/^[\x20-\x7E\r\n\t]+$/', $normalized)) {
            return false;
        }

        // 空白文字以外の印字可能文字（英字、数字、記号: 0x21〜0x7E）が少なくとも1文字含まれているか
        return preg_match('/[\x21-\x7E]/', $normalized) === 1;
    }

    /**
     * Batch update all pending/failed items consisting only of alphanumeric & symbol characters
     * directly into the en column without going through LM Studio.
     * Uses memory-safe chunking and query builder to support thousands of terms.
     *
     * @return array{categories: int, tags: int, posts: int, total: int}
     */
    public function batchUpdateAlphaNumericItems(): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $categoriesUpdated = 0;
        $tagsUpdated = 0;
        $postsUpdated = 0;
        $now = now()->toDateTimeString();

        // 1. カテゴリ＆タグの英数記号一括反映
        // メモリ枯渇を防ぐため必要なカラムのみ取得し chunkById で200件ずつ処理
        DB::connection('sqlite')
            ->table('translation_items')
            ->whereIn('item_type', ['category', 'tag'])
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'failed'])
                  ->orWhereNull('title_en')
                  ->orWhere('title_en', '');
            })
            ->select('id', 'source_id', 'item_type', 'title_ja')
            ->orderBy('id', 'asc')
            ->chunkById(200, function ($items) use (&$categoriesUpdated, &$tagsUpdated, $now) {
                $sqliteUpdates = [];
                $wpTermUpdates = [];

                foreach ($items as $item) {
                    if ($this->isAlphaNumericSymbolOnly($item->title_ja)) {
                        $nameEn = mb_convert_kana(trim($item->title_ja), 'as', 'UTF-8');
                        $sqliteUpdates[] = [
                            'id' => $item->id,
                            'title_en' => $nameEn,
                        ];
                        $wpTermUpdates[] = [
                            'term_id' => $item->source_id,
                            'name_en' => $nameEn,
                        ];

                        if ($item->item_type === 'category') {
                            $categoriesUpdated++;
                        } else {
                            $tagsUpdated++;
                        }
                    }
                }

                // SQLite更新
                foreach ($sqliteUpdates as $up) {
                    DB::connection('sqlite')
                        ->table('translation_items')
                        ->where('id', $up['id'])
                        ->update([
                            'title_en' => $up['title_en'],
                            'status' => 'completed',
                            'error_message' => null,
                            'translated_at' => $now,
                            'synced_to_wp_at' => $now,
                            'updated_at' => $now,
                        ]);
                }

                // WordPress MySQL更新
                foreach ($wpTermUpdates as $wpUp) {
                    DB::connection('wp_mysql')
                        ->table('wp_terms')
                        ->where('term_id', $wpUp['term_id'])
                        ->update([
                            'name_en' => $wpUp['name_en'],
                            'term_translated_at' => $now,
                        ]);
                }
            });

        // 2. 記事の英数記号一括反映 (タイトル・本文ともに英数記号または本文空)
        // 記事本文のメモリ消費を抑えるため chunkById(50) で軽量処理
        DB::connection('sqlite')
            ->table('translation_items')
            ->where('item_type', 'post')
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'failed'])
                  ->orWhereNull('title_en')
                  ->orWhere('title_en', '');
            })
            ->select('id', 'source_id', 'title_ja', 'content_ja')
            ->orderBy('id', 'asc')
            ->chunkById(50, function ($posts) use (&$postsUpdated, $now) {
                foreach ($posts as $item) {
                    $titleOnly = $this->isAlphaNumericSymbolOnly($item->title_ja);
                    $contentOnly = empty(trim($item->content_ja ?? '')) || $this->isAlphaNumericSymbolOnly($item->content_ja);

                    if ($titleOnly && $contentOnly) {
                        $titleEn = mb_convert_kana(trim($item->title_ja ?? ''), 'as', 'UTF-8');
                        $contentEn = $item->content_ja ?? '';

                        DB::connection('sqlite')
                            ->table('translation_items')
                            ->where('id', $item->id)
                            ->update([
                                'title_en' => $titleEn,
                                'content_en' => $contentEn,
                                'status' => 'completed',
                                'error_message' => null,
                                'translated_at' => $now,
                                'synced_to_wp_at' => $now,
                                'updated_at' => $now,
                            ]);

                        DB::connection('wp_mysql')
                            ->table('wp_posts')
                            ->where('ID', $item->source_id)
                            ->update([
                                'post_title_en' => $titleEn,
                                'post_content_en' => $contentEn,
                                'post_translated_at' => $now,
                            ]);

                        $postsUpdated++;
                    }
                }
            });

        $total = $categoriesUpdated + $tagsUpdated + $postsUpdated;

        if ($total > 0) {
            TranslationLog::log(
                "英数記号の直接反映完了: 合計 {$total}件 (カテゴリ: {$categoriesUpdated}件, タグ: {$tagsUpdated}件, 記事: {$postsUpdated}件) をLM Studioを介さずenカラムへ更新しました",
                'success'
            );
        }

        return [
            'categories' => $categoriesUpdated,
            'tags' => $tagsUpdated,
            'posts' => $postsUpdated,
            'total' => $total,
        ];
    }

    /**
     * Determine if a text contains any Japanese characters (Hiragana, Katakana, Kanji)
     * outside of HTML tags and source code placeholders.
     *
     * @param string|null $text
     * @return bool
     */
    public function containsJapanese(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        // Strip code block placeholders and HTML tags before checking
        $stripped = preg_replace('/___\s*SOURCE_CODE_BLOCK_\d+\s*___/i', '', $text);
        $stripped = strip_tags($stripped);
        $stripped = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (trim($stripped) === '') {
            return false;
        }

        return preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FAF}\x{3400}-\x{4DBF}\x{FF65}-\x{FF9F}]/u', $stripped) === 1;
    }

    /**
     * Reset stuck 'translating' items and exhausted 'failed' items so they can be retried cleanly.
     */
    public function resetStuckAndFailedItems(): void
    {
        TranslationItem::where('status', 'translating')
            ->update(['status' => 'pending']);

        TranslationItem::where('status', 'failed')
            ->where('attempts', '>=', 5)
            ->update(['attempts' => 0]);
    }

    /**
     * Translate a single TranslationItem.
     * Supports resumable chunk-by-chunk translation and an optional per-request time budget
     * so long articles never exceed HTTP/proxy or LMStudio timeouts.
     *
     * @param TranslationItem $item
     * @param string|null $modelOverride
     * @param float|null $timeBudgetSeconds Max seconds per HTTP step before returning partial progress
     * @return TranslationItem
     * @throws Exception
     */
    public function translateItem(TranslationItem $item, ?string $modelOverride = null, ?float $timeBudgetSeconds = null): TranslationItem
    {
        @ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $partialKey = "partial_post_{$item->id}";
        $existingPartialRaw = $item->item_type === 'post' ? SystemSetting::get($partialKey) : null;
        $existingPartial = $existingPartialRaw ? json_decode($existingPartialRaw, true) : null;

        // Only increment attempts when starting fresh or retrying after an actual failure
        if ($item->status !== 'translating' || !$existingPartial) {
            $item->attempts += 1;
        }
        $item->status = 'translating';
        $item->save();

        SystemSetting::set('current_task_id', $item->id);

        $stepStart = microtime(true);
        $isPartialReturn = false;

        try {
            if ($item->item_type === 'post') {
                $titleJa = trim($item->title_ja ?? '');
                $contentJa = $item->content_ja ?? '';
                $sourceHash = md5($titleJa . "\0" . $contentJa);

                $llmCallsInStep = 0;

                // Resume from saved partial state if valid, otherwise initialize
                if (is_array($existingPartial) && ($existingPartial['source_hash'] ?? '') === $sourceHash) {
                    $titleEn = $existingPartial['title_en'] ?? '';
                    $titleBypassed = (bool) ($existingPartial['title_bypassed'] ?? false);
                    $codeBlocks = $existingPartial['code_blocks'] ?? [];
                    $item->code_blocks_count = (int) ($existingPartial['code_blocks_count'] ?? count($codeBlocks));
                    $chunks = $existingPartial['chunks'] ?? [];
                    $translatedChunks = $existingPartial['translated_chunks'] ?? [];
                    $priorDuration = (float) ($existingPartial['elapsed_seconds'] ?? 0.0);
                } else {
                    // 1. Translate Title (英数記号または日本語なしならLM Studioを介さず直接反映)
                    $titleEn = '';
                    $titleBypassed = false;
                    if (!empty($titleJa)) {
                        if ($this->isAlphaNumericSymbolOnly($titleJa) || !$this->containsJapanese($titleJa)) {
                            $titleEn = mb_convert_kana($titleJa, 'as', 'UTF-8');
                            $titleBypassed = true;
                        } else {
                            $titleEn = $this->lmStudio->translate($titleJa, 'title', $modelOverride);
                            $llmCallsInStep++;
                        }
                    }

                    // 2. Protect Code in Content (Rule 9)
                    $protectResult = $this->codePreserver->protect($contentJa);
                    $protectedContent = $protectResult['text'];
                    $codeBlocks = $protectResult['blocks'];
                    $item->code_blocks_count = $protectResult['count'];

                    // 3. Split Content into safe small chunks (<= 1000 chars)
                    if (empty(trim($protectedContent))) {
                        $chunks = [];
                    } elseif ($this->isAlphaNumericSymbolOnly($protectedContent) || !$this->containsJapanese($protectedContent)) {
                        $chunks = [$protectedContent];
                    } else {
                        $chunks = $this->splitContentIntoChunks($protectedContent, 1000);
                    }

                    $translatedChunks = [];
                    $priorDuration = 0.0;

                    if (count($chunks) > 1) {
                        SystemSetting::set($partialKey, json_encode([
                            'source_hash' => $sourceHash,
                            'title_en' => $titleEn,
                            'title_bypassed' => $titleBypassed,
                            'code_blocks' => $codeBlocks,
                            'code_blocks_count' => $item->code_blocks_count,
                            'chunks' => $chunks,
                            'translated_chunks' => $translatedChunks,
                            'elapsed_seconds' => 0.0,
                        ], JSON_UNESCAPED_UNICODE));
                    }
                }

                $totalChunks = count($chunks);
                $contentBypassed = ($totalChunks === 1 && ($this->isAlphaNumericSymbolOnly($chunks[0]) || !$this->containsJapanese($chunks[0])));

                for ($idx = count($translatedChunks); $idx < $totalChunks; $idx++) {
                    $chunk = $chunks[$idx];

                    // Fast-path: chunk has no Japanese text (e.g. only placeholders/HTML/ASCII)
                    if (trim($chunk) === '' || $this->isAlphaNumericSymbolOnly($chunk) || !$this->containsJapanese($chunk)) {
                        $translatedChunks[$idx] = $chunk;
                        continue;
                    }

                    // Check time budget BEFORE starting another heavy LLM request
                    // (Ensure at least 1 LLM call is made per HTTP step so progress is always guaranteed)
                    $elapsedThisStep = microtime(true) - $stepStart;
                    if ($timeBudgetSeconds !== null && $llmCallsInStep >= 1 && $elapsedThisStep >= $timeBudgetSeconds) {
                        $totalElapsed = round($priorDuration + $elapsedThisStep, 2);
                        SystemSetting::set($partialKey, json_encode([
                            'source_hash' => $sourceHash,
                            'title_en' => $titleEn,
                            'title_bypassed' => $titleBypassed,
                            'code_blocks' => $codeBlocks,
                            'code_blocks_count' => $item->code_blocks_count,
                            'chunks' => $chunks,
                            'translated_chunks' => $translatedChunks,
                            'elapsed_seconds' => $totalElapsed,
                        ], JSON_UNESCAPED_UNICODE));

                        $isPartialReturn = true;
                        $item->title_en = $titleEn;
                        $item->save();
                        $item->setAttribute('partial_progress', [
                            'done' => count($translatedChunks),
                            'total' => $totalChunks,
                        ]);
                        return $item;
                    }

                    $translatedChunks[$idx] = $this->translateChunkSafely($chunk, $modelOverride);
                    $llmCallsInStep++;

                    // Persist chunk progress immediately so completed chunks are never lost on retry
                    if ($totalChunks > 1 && ($idx + 1) < $totalChunks) {
                        $totalElapsed = round($priorDuration + (microtime(true) - $stepStart), 2);
                        SystemSetting::set($partialKey, json_encode([
                            'source_hash' => $sourceHash,
                            'title_en' => $titleEn,
                            'title_bypassed' => $titleBypassed,
                            'code_blocks' => $codeBlocks,
                            'code_blocks_count' => $item->code_blocks_count,
                            'chunks' => $chunks,
                            'translated_chunks' => $translatedChunks,
                            'elapsed_seconds' => $totalElapsed,
                        ], JSON_UNESCAPED_UNICODE));
                    }
                }

                $contentEn = implode('', $translatedChunks);

                // 4. Restore Code Blocks byte-for-byte
                $contentEn = $this->codePreserver->restore($contentEn, $codeBlocks);

                // 5. Update SQLite
                $item->title_en = $titleEn;
                $item->content_en = $contentEn;
                $item->status = 'completed';
                $item->error_message = null;
                $item->translated_at = now();
                $item->save();

                // Clean up partial state
                SystemSetting::where('key', $partialKey)->delete();

                // 6. Sync to WordPress MySQL (Rule 7 & 8)
                DB::connection('wp_mysql')
                    ->table('wp_posts')
                    ->where('ID', $item->source_id)
                    ->update([
                        'post_title_en' => $titleEn,
                        'post_content_en' => $contentEn,
                        'post_translated_at' => now(),
                    ]);

                $item->synced_to_wp_at = now();
                $item->save();

                $duration = round($priorDuration + (microtime(true) - $stepStart), 2);
                $bypassLabels = [];
                if ($titleBypassed) $bypassLabels[] = 'タイトル英数バイパス';
                if ($contentBypassed) $bypassLabels[] = '本文英数バイパス';
                if ($totalChunks > 1) $bypassLabels[] = "{$totalChunks}分割チャンク";
                $bypassNotice = !empty($bypassLabels) ? ' [' . implode(', ', $bypassLabels) . ']' : '';

                TranslationLog::log(
                    "記事 [ID: {$item->source_id}] 「{$item->title_ja}」 の英訳が完了しました ({$duration}秒{$bypassNotice}, コード保護: {$item->code_blocks_count}箇所)",
                    'success',
                    $item->id
                );
            } else {
                // Category or Tag name translation (英数記号ならLM Studioを介さず直接enへ更新)
                $nameJa = trim($item->title_ja ?? '');
                $nameEn = '';
                $isDirect = false;

                if (!empty($nameJa)) {
                    if ($this->isAlphaNumericSymbolOnly($nameJa) || !$this->containsJapanese($nameJa)) {
                        $nameEn = mb_convert_kana($nameJa, 'as', 'UTF-8');
                        $isDirect = true;
                    } else {
                        $nameEn = $this->lmStudio->translate($nameJa, 'term', $modelOverride);
                    }
                }

                $item->title_en = $nameEn;
                $item->status = 'completed';
                $item->error_message = null;
                $item->translated_at = now();
                $item->save();

                // Sync to WordPress MySQL 
                DB::connection('wp_mysql')
                    ->table('wp_terms')
                    ->where('term_id', $item->source_id)
                    ->update([
                        'name_en' => $nameEn,
                        'term_translated_at' => now(),
                    ]);

                $item->synced_to_wp_at = now();
                $item->save();

                $typeName = $item->item_type === 'category' ? 'カテゴリー' : 'タグ';
                $duration = round(microtime(true) - $stepStart, 2);
                $viaNotice = $isDirect ? ' (英数記号のためLM Studioバイパス)' : " ({$duration}秒)";
                TranslationLog::log(
                    "{$typeName} [ID: {$item->source_id}] 「{$item->title_ja}」 -> 「{$nameEn}」 の英訳が完了しました{$viaNotice}",
                    'success',
                    $item->id
                );
            }

            return $item;
        } catch (\Throwable $e) {
            $item->status = 'failed';
            $item->error_message = $e->getMessage();
            $item->save();

            TranslationLog::log(
                "英訳エラー [{$item->item_type} ID: {$item->source_id}]: " . $e->getMessage(),
                'error',
                $item->id,
                $e->getTraceAsString()
            );

            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        } finally {
            if (!$isPartialReturn && SystemSetting::get('current_task_id') == $item->id) {
                SystemSetting::set('current_task_id', null);
            }
        }
    }

    /**
     * Split long HTML/Gutenberg content into small, semantically safe chunks.
     * Splits on Gutenberg closing comments, HTML block closing tags, newlines, and sentences.
     *
     * @param string $text
     * @param int $charLimit
     * @return array<int, string>
     */
    public function splitContentIntoChunks(string $text, int $charLimit = 1000): array
    {
        if (mb_strlen($text) <= $charLimit) {
            return [$text];
        }

        // Split on Gutenberg closing tags, common HTML block-closing tags, and line breaks
        $pattern = '/(<!--\s*\/wp:[^>]*-->\s*|<(?:\/p|\/div|\/h[1-6]|\/ul|\/ol|\/li|\/table|\/tr|\/blockquote|\/section|\/article|br\s*\/?)>\s*|\r\n|\n)/i';
        $rawParts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($rawParts === false || empty($rawParts)) {
            return mb_str_split($text, $charLimit);
        }

        // Break down any individual oversized part by sentence boundaries or hard limit
        $segments = [];
        foreach ($rawParts as $part) {
            if (mb_strlen($part) <= $charLimit) {
                $segments[] = $part;
                continue;
            }

            // Split oversized single-line blocks by Japanese/English sentence endings
            $sentences = preg_split('/(?<=[。！？\n])|(?<=\.\s)/u', $part, -1, PREG_SPLIT_NO_EMPTY);
            if ($sentences === false || count($sentences) <= 1) {
                foreach (mb_str_split($part, $charLimit) as $piece) {
                    $segments[] = $piece;
                }
            } else {
                foreach ($sentences as $sentence) {
                    if (mb_strlen($sentence) <= $charLimit) {
                        $segments[] = $sentence;
                    } else {
                        foreach (mb_str_split($sentence, $charLimit) as $piece) {
                            $segments[] = $piece;
                        }
                    }
                }
            }
        }

        // Greedily merge segments up to $charLimit
        $chunks = [];
        $currentChunk = '';

        foreach ($segments as $seg) {
            if ($currentChunk !== '' && mb_strlen($currentChunk . $seg) > $charLimit) {
                $chunks[] = $currentChunk;
                $currentChunk = $seg;
            } else {
                $currentChunk .= $seg;
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Translate a single chunk with automatic sub-chunk splitting fallback on timeout/error.
     */
    protected function translateChunkSafely(string $chunk, ?string $modelOverride = null): string
    {
        if (trim($chunk) === '' || $this->isAlphaNumericSymbolOnly($chunk) || !$this->containsJapanese($chunk)) {
            return $chunk;
        }

        try {
            return $this->lmStudio->translate($chunk, 'content', $modelOverride);
        } catch (\Throwable $e) {
            $len = mb_strlen($chunk);
            // If a chunk > 350 chars failed/timed out, split it in half and translate sub-chunks
            if ($len > 350) {
                $subLimit = max(250, (int) ceil($len / 2));
                $subChunks = $this->splitContentIntoChunks($chunk, $subLimit);
                if (count($subChunks) > 1) {
                    Log::warning("Chunk translation failed ({$len} chars), retrying in " . count($subChunks) . " smaller sub-chunks: " . $e->getMessage());
                    $translatedSubs = [];
                    foreach ($subChunks as $sub) {
                        if (trim($sub) === '' || $this->isAlphaNumericSymbolOnly($sub) || !$this->containsJapanese($sub)) {
                            $translatedSubs[] = $sub;
                        } else {
                            $translatedSubs[] = $this->lmStudio->translate($sub, 'content', $modelOverride);
                        }
                    }
                    return implode('', $translatedSubs);
                }
            }
            throw $e;
        }
    }

    /**
     * Safely translate content, splitting into chunks if exceptionally large.
     */
    protected function translateContentInChunks(string $text, ?string $modelOverride = null): string
    {
        $chunks = $this->splitContentIntoChunks($text, 1000);
        $translatedChunks = [];
        foreach ($chunks as $chunk) {
            $translatedChunks[] = $this->translateChunkSafely($chunk, $modelOverride);
        }
        return implode('', $translatedChunks);
    }

    /**
     * Process next pending translation item.
     * Uses a non-blocking file lock so concurrent HTTP requests never pile up on LMStudio,
     * and applies a time budget per step so long posts return chunk progress before any HTTP timeout.
     *
     * @param float $timeBudgetSeconds
     * @return array{status: string, message: string, item: TranslationItem|null, stats: array, progress?: array}
     */
    public function processNextStep(float $timeBudgetSeconds = 25.0): array
    {
        @ignore_user_abort(true);
        @set_time_limit(0);

        $state = SystemSetting::get('runner_state', 'idle');

        if ($state === 'paused') {
            return [
                'status' => 'paused',
                'message' => '翻訳処理は一時停止中です',
                'item' => null,
                'stats' => $this->getStats(),
            ];
        }

        if ($state === 'stopped') {
            return [
                'status' => 'stopped',
                'message' => '翻訳処理は停止されています',
                'item' => null,
                'stats' => $this->getStats(),
            ];
        }

        // Prevent concurrent requests from piling up on LMStudio
        $lockPath = storage_path('framework/translation_runner.lock');
        $lockFp = @fopen($lockPath, 'c+');
        if ($lockFp && !@flock($lockFp, LOCK_EX | LOCK_NB)) {
            @fclose($lockFp);
            $currentTaskId = SystemSetting::get('current_task_id');
            $currentItem = $currentTaskId ? TranslationItem::find($currentTaskId) : null;
            return [
                'status' => 'busy',
                'message' => '現在LM Studioで翻訳処理を実行中です。完了を待機しています...',
                'item' => $currentItem,
                'stats' => $this->getStats(),
            ];
        }

        try {
            // Target type prioritization: categories first, then tags, then posts, or mixed
            $targetTypes = json_decode(SystemSetting::get('target_types', '["category", "tag", "post"]'), true) ?: ['category', 'tag', 'post'];

            // 1. First check if there is an active in-progress item (e.g. multi-chunk post in progress)
            $currentTaskId = SystemSetting::get('current_task_id');
            $item = null;
            if ($currentTaskId) {
                $candidate = TranslationItem::where('id', $currentTaskId)
                    ->whereIn('item_type', $targetTypes)
                    ->where('status', 'translating')
                    ->first();
                if ($candidate) {
                    $item = $candidate;
                }
            }

            // 2. Otherwise pick any other 'translating' item (from interrupted step) or next 'pending'/'failed' item
            if (!$item) {
                $item = TranslationItem::whereIn('item_type', $targetTypes)
                    ->whereIn('status', ['translating', 'pending', 'failed'])
                    ->where('attempts', '<', 5)
                    ->orderByRaw("CASE WHEN status = 'translating' THEN 0 ELSE 1 END")
                    ->orderByRaw("CASE WHEN item_type = 'category' THEN 1 WHEN item_type = 'tag' THEN 2 ELSE 3 END")
                    ->orderBy('id', 'asc')
                    ->first();
            }

            if (!$item) {
                SystemSetting::set('runner_state', 'idle');
                return [
                    'status' => 'completed',
                    'message' => 'すべての対象の英訳が完了しました！',
                    'item' => null,
                    'stats' => $this->getStats(),
                ];
            }

            try {
                $translated = $this->translateItem($item, null, $timeBudgetSeconds);
                $partialProgress = $translated->getAttribute('partial_progress');

                if ($partialProgress && $translated->status === 'translating') {
                    $done = $partialProgress['done'];
                    $total = $partialProgress['total'];
                    return [
                        'status' => 'partial',
                        'message' => "記事 #{$translated->source_id} 「{$translated->title_ja}」 を長文分割翻訳中 ({$done}/{$total} パート完了)...",
                        'item' => $translated,
                        'progress' => $partialProgress,
                        'stats' => $this->getStats(),
                    ];
                }

                return [
                    'status' => 'success',
                    'message' => "{$translated->item_type} #{$translated->source_id} の英訳に成功しました",
                    'item' => $translated,
                    'stats' => $this->getStats(),
                ];
            } catch (\Throwable $e) {
                return [
                    'status' => 'error',
                    'message' => "英訳エラー: " . $e->getMessage(),
                    'item' => $item,
                    'stats' => $this->getStats(),
                ];
            }
        } finally {
            if ($lockFp) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
        }
    }
}

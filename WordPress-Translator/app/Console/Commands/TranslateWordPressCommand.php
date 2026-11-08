<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\TranslationItem;
use App\Services\TranslationService;
use Illuminate\Console\Command;

class TranslateWordPressCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translation:work
                            {--sync : First sync all posts and terms from WordPress}
                            {--alphanumeric-only : Only batch update alphanumeric items directly without LM Studio}
                            {--limit= : Limit the number of items to translate}
                            {--delay=1 : Delay in seconds between translations}
                            {--model= : Specific LMStudio model to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run continuous translation of WordPress posts, categories, and tags using LMStudio';

    /**
     * Execute the console command.
     */
    public function handle(TranslationService $translationService): int
    {
        $this->info('========================================================');
        $this->info('  WordPress Translation Worker (LMStudio / SQLite)');
        $this->info('========================================================');

        if ($this->option('alphanumeric-only')) {
            $this->info('英字・数字・記号のみの項目をLM Studioを介さずenカラムへ直接更新中...');
            $res = $translationService->batchUpdateAlphaNumericItems();
            $this->info("完了: 合計 {$res['total']}件 (カテゴリ: {$res['categories']}件, タグ: {$res['tags']}件, 記事: {$res['posts']}件) を更新しました。");
            return Command::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info('WordPressデータベースからデータを同期しています...');
            $result = $translationService->syncFromWordPress();
            $directCount = $result['alphanumeric_direct_updated'] ?? 0;
            $this->info("同期完了: 記事 {$result['posts_synced']}件, カテゴリ {$result['categories_synced']}件, タグ {$result['tags_synced']}件 (英数記号直接反映: {$directCount}件)");
        }

        SystemSetting::set('runner_state', 'running');

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $delay = (float) $this->option('delay');
        $processed = 0;

        $this->info("翻訳ワーカーを開始しました。(Ctrl+C または WebUI で停止・一時停止可能)");

        while (true) {
            $state = SystemSetting::get('runner_state', 'running');

            if ($state === 'paused') {
                $this->warn('システム設定により一時停止されました。待機中...');
                sleep(2);
                continue;
            }

            if ($state === 'stopped') {
                $this->warn('システム設定により停止されました。');
                break;
            }

            $step = $translationService->processNextStep();

            if ($step['status'] === 'completed') {
                $this->info('すべての英訳が完了しました！');
                break;
            }

            if ($step['status'] === 'partial' && $step['item']) {
                $item = $step['item'];
                $done = $step['progress']['done'] ?? 0;
                $total = $step['progress']['total'] ?? 0;
                $this->line("<fg=cyan>[PARTIAL]</> #{$item->source_id} ({$item->item_type}) 長文分割翻訳中 ({$done}/{$total} パート完了)...");
                continue;
            } elseif ($step['status'] === 'busy') {
                usleep(1000000);
                continue;
            } elseif ($step['status'] === 'success' && $step['item']) {
                $item = $step['item'];
                $processed++;
                $this->line("<fg=green>[SUCCESS]</> #{$item->source_id} ({$item->item_type}) 「{$item->title_ja}」 -> 「{$item->title_en}」");
            } elseif ($step['status'] === 'error') {
                $this->error("[ERROR] " . $step['message']);
            }

            if ($limit && $processed >= $limit) {
                $this->info("指定された上限 ({$limit}件) に達したため終了します。");
                break;
            }

            if ($delay > 0) {
                usleep((int)($delay * 1000000));
            }
        }

        SystemSetting::set('runner_state', 'idle');
        return Command::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\TranslationItem;
use App\Models\TranslationLog;
use App\Services\LMStudioService;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class TranslationController extends Controller
{
    public function __construct(
        protected TranslationService $translationService,
        protected LMStudioService $lmStudio
    ) {}

    /**
     * Display the main translation system dashboard.
     */
    public function index()
    {
        $stats = $this->translationService->getStats();
        $models = $this->lmStudio->getModels();
        $currentModel = SystemSetting::get('lmstudio_model', env('LMSTUDIO_MODEL', 'google/gemma-4-e4b'));
        $lmStudioUrl = SystemSetting::get('lmstudio_url', env('LMSTUDIO_BASE_URL'));

        return view('translator.dashboard', compact('stats', 'models', 'currentModel', 'lmStudioUrl'));
    }

    /**
     * API: Get real-time stats and runner status.
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->translationService->getStats());
    }

    /**
     * API: Get paginated translation items with filtering.
     */
    public function items(Request $request): JsonResponse
    {
        $query = TranslationItem::query();

        if ($type = $request->input('type')) {
            if ($type !== 'all') {
                $query->where('item_type', $type);
            }
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_ja', 'like', "%{$search}%")
                  ->orWhere('title_en', 'like', "%{$search}%")
                  ->orWhere('source_id', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('source_id', 'desc')->paginate(20);

        return response()->json($items);
    }

    /**
     * API: Get item details including full content.
     */
    public function show(TranslationItem $item): JsonResponse
    {
        $item->load('logs');
        return response()->json($item);
    }

    /**
     * API: Update an item manually and sync to WordPress.
     */
    public function update(Request $request, TranslationItem $item): JsonResponse
    {
        $validated = $request->validate([
            'title_en' => 'nullable|string',
            'content_en' => 'nullable|string',
        ]);

        $item->title_en = $validated['title_en'] ?? $item->title_en;
        if ($item->item_type === 'post') {
            $item->content_en = $validated['content_en'] ?? $item->content_en;
        }
        $item->status = 'completed';
        $item->translated_at = now();
        $item->save();

        // Sync to WordPress
        if ($item->item_type === 'post') {
            DB::connection('wp_mysql')
                ->table('wp_posts')
                ->where('ID', $item->source_id)
                ->update([
                    'post_title_en' => $item->title_en,
                    'post_content_en' => $item->content_en,
                    'post_translated_at' => now(),
                ]);
        } else {
            DB::connection('wp_mysql')
                ->table('wp_terms')
                ->where('term_id', $item->source_id)
                ->update([
                    'name_en' => $item->title_en,
                    'term_translated_at' => now(),
                ]);
        }

        $item->synced_to_wp_at = now();
        $item->save();

        TranslationLog::log("手動更新＆WordPress同期: {$item->item_type} #{$item->source_id}", 'info', $item->id);

        return response()->json(['success' => true, 'item' => $item]);
    }

    /**
     * API: Translate a single item immediately (supports chunked partial steps for long articles).
     */
    public function translateSingle(TranslationItem $item): JsonResponse
    {
        @ignore_user_abort(true);
        @set_time_limit(0);

        try {
            $translated = $this->translationService->translateItem($item, null, 25.0);
            $partialProgress = $translated->getAttribute('partial_progress');

            if ($partialProgress && $translated->status === 'translating') {
                return response()->json([
                    'success' => true,
                    'status' => 'partial',
                    'progress' => $partialProgress,
                    'item' => $translated,
                    'stats' => $this->translationService->getStats(),
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'item' => $translated,
                'stats' => $this->translationService->getStats(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'item' => $item->fresh(),
            ], 500);
        }
    }

    /**
     * API: Sync items from WordPress MySQL database.
     */
    public function sync(): JsonResponse
    {
        try {
            $result = $this->translationService->syncFromWordPress();
            return response()->json([
                'success' => true,
                'result' => $result,
                'stats' => $this->translationService->getStats(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'WordPress同期エラー: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Batch update alphanumeric & symbol items directly into en column without LM Studio.
     */
    public function batchAlphaNumeric(): JsonResponse
    {
        try {
            $result = $this->translationService->batchUpdateAlphaNumericItems();
            return response()->json([
                'success' => true,
                'result' => $result,
                'stats' => $this->translationService->getStats(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('英数記号の直接反映エラー: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => '英数記号の直接反映エラー: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Start or resume translation runner.
     */
    public function startRunner(): JsonResponse
    {
        $this->translationService->resetStuckAndFailedItems();
        SystemSetting::set('runner_state', 'running');
        TranslationLog::log('翻訳処理が開始/再開されました', 'info');

        return response()->json([
            'success' => true,
            'runner_state' => 'running',
            'stats' => $this->translationService->getStats(),
        ]);
    }

    /**
     * API: Pause translation runner.
     */
    public function pauseRunner(): JsonResponse
    {
        SystemSetting::set('runner_state', 'paused');
        TranslationLog::log('翻訳処理が一時停止されました', 'warning');

        return response()->json([
            'success' => true,
            'runner_state' => 'paused',
            'stats' => $this->translationService->getStats(),
        ]);
    }

    /**
     * API: Stop translation runner.
     */
    public function stopRunner(): JsonResponse
    {
        SystemSetting::set('runner_state', 'stopped');
        SystemSetting::set('current_task_id', null);
        TranslationLog::log('翻訳処理が停止されました', 'warning');

        return response()->json([
            'success' => true,
            'runner_state' => 'stopped',
            'stats' => $this->translationService->getStats(),
        ]);
    }

    /**
     * API: Process one single step in the translation runner.
     */
    public function processStep(): JsonResponse
    {
        $result = $this->translationService->processNextStep();
        return response()->json($result);
    }

    /**
     * API: Get latest execution logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $sinceId = $request->input('since_id');
        $query = TranslationLog::query()->orderBy('id', 'desc')->limit(60);

        if ($sinceId) {
            $query->where('id', '>', $sinceId);
        }

        $logs = $query->get()->reverse()->values();

        return response()->json($logs);
    }

    /**
     * API: Test LMStudio connectivity.
     */
    public function testLMStudio(Request $request): JsonResponse
    {
        $url = $request->input('url', SystemSetting::get('lmstudio_url'));
        $result = $this->lmStudio->testConnection($url);
        return response()->json($result);
    }

    /**
     * API: Save system settings.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lmstudio_url' => 'required|string',
            'lmstudio_model' => 'required|string',
            'delay_seconds' => 'nullable|numeric|min:0|max:10',
            'target_types' => 'nullable|array',
        ]);

        SystemSetting::set('lmstudio_url', $validated['lmstudio_url']);
        SystemSetting::set('lmstudio_model', $validated['lmstudio_model']);

        if (isset($validated['delay_seconds'])) {
            SystemSetting::set('delay_seconds', (string)$validated['delay_seconds']);
        }

        if (isset($validated['target_types'])) {
            SystemSetting::set('target_types', json_encode($validated['target_types']));
        }

        TranslationLog::log('設定が更新されました', 'info');

        return response()->json([
            'success' => true,
            'message' => '設定を保存しました',
        ]);
    }

    /**
     * SSE Stream: Real-time runner stream for continuous execution and monitoring.
     */
    public function streamRunner(Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            // Prevent output buffering
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Ensure runner is marked running
            SystemSetting::set('runner_state', 'running');

            $heartbeat = 0;
            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $state = SystemSetting::get('runner_state', 'idle');
                if ($state === 'paused' || $state === 'stopped') {
                    echo "data: " . json_encode(['type' => 'status', 'runner_state' => $state, 'message' => "処理は {$state} 状態です"]) . "\n\n";
                    flush();
                    break;
                }

                // Execute one step
                $stepResult = $this->translationService->processNextStep();
                echo "data: " . json_encode(['type' => 'step', 'result' => $stepResult]) . "\n\n";
                flush();

                if ($stepResult['status'] === 'completed' || $stepResult['status'] === 'paused' || $stepResult['status'] === 'stopped') {
                    break;
                }

                $delay = (float) SystemSetting::get('delay_seconds', '0.5');
                if ($delay > 0) {
                    usleep((int)($delay * 1000000));
                }
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}

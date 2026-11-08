<?php

use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

// Main Translation System UI at http://test.localhost:8080/
Route::get('/', [TranslationController::class, 'index'])->name('translator.dashboard');

// REST & Streaming APIs
Route::prefix('api')->group(function () {
    Route::get('/stats', [TranslationController::class, 'stats'])->name('api.stats');
    Route::get('/items', [TranslationController::class, 'items'])->name('api.items');
    Route::get('/items/{item}', [TranslationController::class, 'show'])->name('api.items.show');
    Route::post('/items/{item}', [TranslationController::class, 'update'])->name('api.items.update');
    Route::post('/items/{item}/translate', [TranslationController::class, 'translateSingle'])->name('api.items.translate');

    Route::post('/sync', [TranslationController::class, 'sync'])->name('api.sync');
    Route::post('/sync-alphanumeric', [TranslationController::class, 'batchAlphaNumeric'])->name('api.sync.alphanumeric');

    Route::post('/runner/start', [TranslationController::class, 'startRunner'])->name('api.runner.start');
    Route::post('/runner/pause', [TranslationController::class, 'pauseRunner'])->name('api.runner.pause');
    Route::post('/runner/stop', [TranslationController::class, 'stopRunner'])->name('api.runner.stop');
    Route::post('/runner/step', [TranslationController::class, 'processStep'])->name('api.runner.step');
    Route::get('/runner/stream', [TranslationController::class, 'streamRunner'])->name('api.runner.stream');

    Route::get('/logs', [TranslationController::class, 'logs'])->name('api.logs');
    Route::post('/settings', [TranslationController::class, 'saveSettings'])->name('api.settings.save');
    Route::post('/settings/test-lmstudio', [TranslationController::class, 'testLMStudio'])->name('api.settings.test');
});

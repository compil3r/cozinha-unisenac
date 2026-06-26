<?php

use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StepAnalysisController;
use App\Http\Controllers\TtsController;
use App\Http\Controllers\VoiceChatController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

/*
|--------------------------------------------------------------------------
| Rate Limiting para análise de imagens
|--------------------------------------------------------------------------
*/
RateLimiter::for('analysis', function ($request) {
    $limit = (int) config('vision.rate_limit.analyses_per_hour', 20);
    return Limit::perHour($limit)->by($request->ip())->response(function () {
        return response()->json([
            'success' => false,
            'message' => 'Você atingiu o limite de análises por hora. Aguarde alguns minutos e tente novamente.',
        ], 429);
    });
});

/*
|--------------------------------------------------------------------------
| Rotas principais
|--------------------------------------------------------------------------
*/

// Seleção de receitas (sem sessão) e receita em andamento (com sessão)
Route::get('/receitas', [RecipeController::class, 'selection'])->name('recipe.selection');
Route::get('/',         [RecipeController::class, 'index'])->name('recipe.index');

// Gerenciamento de sessão
Route::prefix('session')->name('session.')->group(function () {
    Route::post('/start',           [SessionController::class, 'start'])          ->name('start');
    Route::post('/advance-manually',[SessionController::class, 'advanceManually'])->name('advance-manually');
    Route::post('/restart',         [SessionController::class, 'restart'])        ->name('restart');

    // Somente disponível em modo mock
    Route::post('/mock-scenario',   [SessionController::class, 'setMockScenario'])->name('mock-scenario');
});

// Text-to-Speech via Amazon Polly
Route::post('/tts', [TtsController::class, 'synthesize'])->name('tts.synthesize');

// POC: chat de voz com a Polly
Route::get('/poc-voz',        [VoiceChatController::class, 'index'])->name('poc.voice');
Route::post('/poc-voz',       [VoiceChatController::class, 'chat'])->name('poc.voice.chat');
Route::post('/poc-voz/clear', [VoiceChatController::class, 'clearHistory'])->name('poc.voice.clear');

// Análise de imagem (com rate limiting)
Route::post('/session/analyze-step', [StepAnalysisController::class, 'analyze'])
    ->middleware('throttle:analysis')
    ->name('session.analyze-step');

// Debug: visualizar último retorno Bedrock (somente com APP_DEBUG=true)
if (config('app.debug')) {
    Route::get('/debug/bedrock', function () {
        return response()->json(
            session('debug_last_bedrock', ['message' => 'Nenhuma análise realizada ainda nesta sessão.'])
        );
    })->name('debug.bedrock');
}

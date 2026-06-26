<?php

use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StepAnalysisController;
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

// Página inicial da receita
Route::get('/', [RecipeController::class, 'index'])->name('recipe.index');

// Gerenciamento de sessão
Route::prefix('session')->name('session.')->group(function () {
    Route::post('/start',           [SessionController::class, 'start'])          ->name('start');
    Route::post('/advance-manually',[SessionController::class, 'advanceManually'])->name('advance-manually');
    Route::post('/restart',         [SessionController::class, 'restart'])        ->name('restart');

    // Somente disponível em modo mock
    Route::post('/mock-scenario',   [SessionController::class, 'setMockScenario'])->name('mock-scenario');
});

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

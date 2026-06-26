<?php

namespace App\Providers;

use App\Contracts\VisionProvider;
use App\Services\BedrockVisionProvider;
use App\Services\MockVisionProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind da interface VisionProvider à implementação ativa via configuração
        $this->app->bind(VisionProvider::class, function ($app) {
            $provider = config('vision.provider', 'mock');

            if ($provider === 'bedrock') {
                return new BedrockVisionProvider();
            }

            // Modo mock: lê o cenário da sessão se disponível
            $scenario = 'complete';
            if ($app->runningInConsole() === false) {
                $scenario = session('mock_scenario', 'complete');
            }

            return new MockVisionProvider($scenario);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

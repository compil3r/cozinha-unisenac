<?php

namespace App\Services;

use App\Contracts\VisionProvider;
use App\ValueObjects\VisionAnalysisResult;

/**
 * MockVisionProvider
 *
 * Implementação simulada de VisionProvider para desenvolvimento e demonstração.
 * Não realiza nenhuma análise real de imagem.
 * O cenário simulado é passado via sessão Laravel pelo frontend.
 */
class MockVisionProvider implements VisionProvider
{
    /**
     * Cenário de mock escolhido pelo usuário no frontend.
     * Deve ser um dos valores definidos em VisionAnalysisResult::mock().
     */
    private string $scenario;

    public function __construct(string $scenario = 'complete')
    {
        $this->scenario = in_array($scenario, ['complete', 'incomplete', 'poor_image', 'uncertain'])
            ? $scenario
            : 'complete';
    }

    public function analyzeRecipeStep(
        array  $recipe,
        array  $step,
        string $imageBytes,
        string $mimeType
    ): VisionAnalysisResult {
        // Simula um pequeno atraso para parecer realista
        usleep(random_int(300_000, 800_000)); // 300–800ms

        return VisionAnalysisResult::mock(
            scenario:  $this->scenario,
            stepTitle: $step['title'] ?? '',
        );
    }

    public function getScenario(): string
    {
        return $this->scenario;
    }
}

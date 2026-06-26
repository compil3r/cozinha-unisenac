<?php

namespace App\Contracts;

use App\ValueObjects\VisionAnalysisResult;

interface VisionProvider
{
    /**
     * Analisa uma imagem em relação à etapa atual de uma receita.
     *
     * @param  array  $recipe  Dados completos da receita
     * @param  array  $step    Dados da etapa atual
     * @param  string $imageBytes  Conteúdo binário da imagem
     * @param  string $mimeType    MIME type da imagem (image/jpeg ou image/png)
     * @return VisionAnalysisResult
     */
    public function analyzeRecipeStep(
        array $recipe,
        array $step,
        string $imageBytes,
        string $mimeType
    ): VisionAnalysisResult;
}

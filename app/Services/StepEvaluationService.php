<?php

namespace App\Services;

use App\ValueObjects\VisionAnalysisResult;

/**
 * StepEvaluationService
 *
 * Aplica regras determinísticas de negócio para decidir se uma etapa
 * pode avançar automaticamente, exige confirmação manual ou deve ser repetida.
 *
 * O modelo de IA sugere uma ação, mas este serviço é quem decide.
 */
class StepEvaluationService
{
    private float $autoAdvanceConfidence;
    private bool  $requireGoodImage;
    private bool  $requireCompleteStatus;

    public function __construct()
    {
        $this->autoAdvanceConfidence = (float) config('vision.advance_rules.auto_advance_confidence', 0.75);
        $this->requireGoodImage      = (bool)  config('vision.advance_rules.require_good_image', true);
        $this->requireCompleteStatus = (bool)  config('vision.advance_rules.require_complete_status', true);
    }

    /**
     * Avalia o resultado da análise e a etapa atual para determinar a ação.
     *
     * @return array{
     *   can_advance: bool,
     *   can_advance_manually: bool,
     *   action: string,
     *   reason: string
     * }
     */
    public function evaluate(VisionAnalysisResult $result, array $step): array
    {
        // Erros sempre exigem nova tentativa
        if ($result->isError) {
            return [
                'can_advance'         => false,
                'can_advance_manually' => $step['allow_manual_advance'] ?? false,
                'action'              => 'retry',
                'reason'              => 'error',
            ];
        }

        // Critérios para avanço automático (todos devem ser atendidos)
        $confidenceOk    = $result->confidence >= $this->autoAdvanceConfidence;
        $imageQualityOk  = ! $this->requireGoodImage || $result->isGoodQuality();
        $statusComplete  = ! $this->requireCompleteStatus || $result->isComplete();

        if ($confidenceOk && $imageQualityOk && $statusComplete) {
            return [
                'can_advance'          => true,
                'can_advance_manually' => true,
                'action'               => 'advance',
                'reason'               => 'auto_approved',
            ];
        }

        // Imagem de má qualidade → pedir nova foto
        if (! $result->isGoodQuality()) {
            return [
                'can_advance'          => false,
                'can_advance_manually' => $step['allow_manual_advance'] ?? false,
                'action'               => 'retry',
                'reason'               => 'poor_image_quality',
            ];
        }

        // Etapa claramente incompleta → pedir nova foto
        if ($result->stepStatus === VisionAnalysisResult::STEP_STATUS_INCOMPLETE && $result->confidence >= 0.75) {
            return [
                'can_advance'          => false,
                'can_advance_manually' => $step['allow_manual_advance'] ?? false,
                'action'               => 'retry',
                'reason'               => 'step_incomplete',
            ];
        }

        // Resultado incerto ou confiança baixa → oferecer confirmação manual se permitida
        $allowManual = $step['allow_manual_advance'] ?? false;
        if ($allowManual) {
            return [
                'can_advance'          => false,
                'can_advance_manually' => true,
                'action'               => 'ask_manual_confirmation',
                'reason'               => 'uncertain_result',
            ];
        }

        // Não permite confirmação manual e resultado incerto → nova tentativa
        return [
            'can_advance'          => false,
            'can_advance_manually' => false,
            'action'               => 'retry',
            'reason'               => 'uncertain_no_manual',
        ];
    }

    /**
     * Gera uma mensagem de feedback contextualizada com base na avaliação.
     */
    public function buildFeedbackMessage(
        VisionAnalysisResult $result,
        array                $evaluation,
        array                $step
    ): string {
        // Se o modelo deu um feedback, priorizar com complemento determinístico
        $modelFeedback = $result->feedback;

        $suffix = match ($evaluation['action']) {
            'advance' => '',
            'ask_manual_confirmation' => ' ' . ($step['manual_advance_reason'] ?? 'Avance quando estiver pronto.'),
            'retry'   => match ($evaluation['reason']) {
                'poor_image_quality' => ' Certifique-se de que a câmera está estável e com boa iluminação.',
                'step_incomplete'    => ' Revise os itens esperados e tente novamente.',
                default              => ' Tente novamente com uma foto mais clara.',
            },
            default => '',
        };

        return $modelFeedback . $suffix;
    }
}

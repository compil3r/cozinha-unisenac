<?php

namespace Tests\Unit;

use App\Services\StepEvaluationService;
use App\ValueObjects\VisionAnalysisResult;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StepEvaluationServiceTest extends TestCase
{
    private StepEvaluationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('vision.advance_rules.auto_advance_confidence', 0.75);
        Config::set('vision.advance_rules.require_good_image', true);
        Config::set('vision.advance_rules.require_complete_status', true);

        $this->service = new StepEvaluationService();
    }

    // ── Avanço automático ─────────────────────────────────────────────────────

    public function test_auto_advance_when_all_criteria_met(): void
    {
        $result = VisionAnalysisResult::mock('complete');
        $step   = $this->step(allow_manual: false);

        $evaluation = $this->service->evaluate($result, $step);

        $this->assertTrue($evaluation['can_advance']);
        $this->assertSame('advance',      $evaluation['action']);
        $this->assertSame('auto_approved', $evaluation['reason']);
    }

    // ── Imagem ruim → retry ───────────────────────────────────────────────────

    public function test_retry_when_image_quality_is_poor(): void
    {
        $result = VisionAnalysisResult::mock('poor_image');
        $step   = $this->step(allow_manual: false);

        $evaluation = $this->service->evaluate($result, $step);

        $this->assertFalse($evaluation['can_advance']);
        $this->assertSame('retry',            $evaluation['action']);
        $this->assertSame('poor_image_quality', $evaluation['reason']);
    }

    // ── Confiança baixa + sem manual → retry ──────────────────────────────────

    public function test_retry_when_confidence_below_threshold_and_no_manual(): void
    {
        $result = VisionAnalysisResult::mock('uncertain');
        $step   = $this->step(allow_manual: false);

        $evaluation = $this->service->evaluate($result, $step);

        $this->assertFalse($evaluation['can_advance']);
        $this->assertSame('retry', $evaluation['action']);
    }

    // ── Resultado incerto + manual permitido → ask_manual_confirmation ────────

    public function test_ask_manual_confirmation_when_uncertain_and_manual_allowed(): void
    {
        $result = VisionAnalysisResult::mock('uncertain');
        $step   = $this->step(allow_manual: true);

        $evaluation = $this->service->evaluate($result, $step);

        $this->assertFalse($evaluation['can_advance']);
        $this->assertTrue($evaluation['can_advance_manually']);
        $this->assertSame('ask_manual_confirmation', $evaluation['action']);
        $this->assertSame('uncertain_result',        $evaluation['reason']);
    }

    // ── Erro sempre → retry ───────────────────────────────────────────────────

    public function test_error_result_always_returns_retry(): void
    {
        $result = VisionAnalysisResult::error('Erro simulado.');
        $step   = $this->step(allow_manual: true); // mesmo com manual permitido

        $evaluation = $this->service->evaluate($result, $step);

        $this->assertFalse($evaluation['can_advance']);
        $this->assertSame('retry', $evaluation['action']);
        $this->assertSame('error', $evaluation['reason']);
    }

    // ── Etapa incompleta com alta confiança → retry ───────────────────────────

    public function test_retry_when_step_is_clearly_incomplete_with_high_confidence(): void
    {
        $result = VisionAnalysisResult::fromArray([
            'image_quality'              => 'good',
            'step_status'                => 'incomplete',
            'confidence'                 => 0.85,
            'detected_items'             => [],
            'missing_or_uncertain_items' => ['iogurte'],
            'feedback'                   => 'Parece incompleto.',
            'recommended_action'         => 'retry',
        ]);
        $step = $this->step(allow_manual: true);

        $evaluation = $this->service->evaluate($result, $step);

        $this->assertFalse($evaluation['can_advance']);
        $this->assertSame('retry',         $evaluation['action']);
        $this->assertSame('step_incomplete', $evaluation['reason']);
    }

    // ── buildFeedbackMessage ──────────────────────────────────────────────────

    public function test_build_feedback_message_appends_poor_image_suffix(): void
    {
        $result     = VisionAnalysisResult::mock('poor_image');
        $evaluation = ['action' => 'retry', 'reason' => 'poor_image_quality'];
        $step       = $this->step(allow_manual: false);

        $msg = $this->service->buildFeedbackMessage($result, $evaluation, $step);

        $this->assertStringContainsString('iluminação', $msg);
    }

    public function test_build_feedback_message_appends_manual_reason(): void
    {
        $result     = VisionAnalysisResult::mock('uncertain');
        $evaluation = ['action' => 'ask_manual_confirmation', 'reason' => 'uncertain_result'];
        $step       = $this->step(allow_manual: true, manual_reason: 'Avance quando pronto.');

        $msg = $this->service->buildFeedbackMessage($result, $evaluation, $step);

        $this->assertStringContainsString('Avance quando pronto.', $msg);
    }

    public function test_build_feedback_message_returns_model_feedback_on_advance(): void
    {
        $result     = VisionAnalysisResult::mock('complete');
        $evaluation = ['action' => 'advance', 'reason' => 'auto_approved'];
        $step       = $this->step(allow_manual: false);

        $msg = $this->service->buildFeedbackMessage($result, $evaluation, $step);

        // Sem sufixo adicional no caso de avanço
        $this->assertSame($result->feedback, $msg);
    }

    // ── fixture ──────────────────────────────────────────────────────────────

    private function step(bool $allow_manual = false, ?string $manual_reason = null): array
    {
        return [
            'index'                  => 0,
            'title'                  => 'Etapa de teste',
            'instruction'            => 'Instrução de teste.',
            'expected_items'         => ['item A'],
            'visual_criteria'        => ['critério X'],
            'allow_manual_advance'   => $allow_manual,
            'manual_advance_reason'  => $manual_reason,
        ];
    }
}

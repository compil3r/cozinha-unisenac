<?php

namespace Tests\Unit;

use App\ValueObjects\VisionAnalysisResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VisionAnalysisResultTest extends TestCase
{
    // ── fromArray: casos de sucesso ──────────────────────────────────────────

    public function test_from_array_creates_valid_result(): void
    {
        $data = [
            'image_quality'              => 'good',
            'step_status'                => 'complete',
            'confidence'                 => 0.92,
            'detected_items'             => ['taça', 'iogurte'],
            'missing_or_uncertain_items' => [],
            'feedback'                   => 'Ótimo! A etapa parece concluída.',
            'recommended_action'         => 'advance',
        ];

        $result = VisionAnalysisResult::fromArray($data);

        $this->assertSame('good',     $result->imageQuality);
        $this->assertSame('complete', $result->stepStatus);
        $this->assertEqualsWithDelta(0.92, $result->confidence, 0.001);
        $this->assertSame(['taça', 'iogurte'], $result->detectedItems);
        $this->assertSame([], $result->missingOrUncertainItems);
        $this->assertSame('Ótimo! A etapa parece concluída.', $result->feedback);
        $this->assertSame('advance',  $result->recommendedAction);
        $this->assertFalse($result->isError);
        $this->assertFalse($result->isMock);
    }

    public function test_from_array_accepts_all_valid_image_qualities(): void
    {
        foreach (['good', 'poor', 'uncertain'] as $quality) {
            $result = VisionAnalysisResult::fromArray($this->baseData(['image_quality' => $quality]));
            $this->assertSame($quality, $result->imageQuality);
        }
    }

    public function test_from_array_accepts_all_valid_step_statuses(): void
    {
        foreach (['complete', 'incomplete', 'uncertain'] as $status) {
            $result = VisionAnalysisResult::fromArray($this->baseData(['step_status' => $status]));
            $this->assertSame($status, $result->stepStatus);
        }
    }

    public function test_from_array_accepts_all_valid_actions(): void
    {
        foreach (['retry', 'advance', 'ask_manual_confirmation'] as $action) {
            $result = VisionAnalysisResult::fromArray($this->baseData(['recommended_action' => $action]));
            $this->assertSame($action, $result->recommendedAction);
        }
    }

    // ── fromArray: casos de erro ─────────────────────────────────────────────

    public function test_from_array_throws_on_invalid_image_quality(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/image_quality/');

        VisionAnalysisResult::fromArray($this->baseData(['image_quality' => 'excellent']));
    }

    public function test_from_array_throws_on_invalid_step_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/step_status/');

        VisionAnalysisResult::fromArray($this->baseData(['step_status' => 'done']));
    }

    public function test_from_array_throws_on_confidence_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VisionAnalysisResult::fromArray($this->baseData(['confidence' => 1.5]));
    }

    public function test_from_array_throws_on_negative_confidence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VisionAnalysisResult::fromArray($this->baseData(['confidence' => -0.1]));
    }

    public function test_from_array_throws_on_empty_feedback(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VisionAnalysisResult::fromArray($this->baseData(['feedback' => '   ']));
    }

    public function test_from_array_throws_on_invalid_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VisionAnalysisResult::fromArray($this->baseData(['recommended_action' => 'skip']));
    }

    // ── error() ──────────────────────────────────────────────────────────────

    public function test_error_creates_safe_fallback(): void
    {
        $result = VisionAnalysisResult::error('Erro de teste.');

        $this->assertTrue($result->isError);
        $this->assertFalse($result->isMock);
        $this->assertSame('retry',      $result->recommendedAction);
        $this->assertSame('uncertain',  $result->imageQuality);
        $this->assertSame('uncertain',  $result->stepStatus);
        $this->assertEqualsWithDelta(0.0, $result->confidence, 0.001);
        $this->assertSame('Erro de teste.', $result->feedback);
    }

    public function test_error_uses_default_message_when_empty(): void
    {
        $result = VisionAnalysisResult::error();
        $this->assertNotEmpty($result->feedback);
    }

    // ── mock() ───────────────────────────────────────────────────────────────

    public function test_mock_complete_scenario(): void
    {
        $result = VisionAnalysisResult::mock('complete', 'Etapa 1');

        $this->assertTrue($result->isMock);
        $this->assertFalse($result->isError);
        $this->assertSame('complete', $result->stepStatus);
        $this->assertSame('good',     $result->imageQuality);
        $this->assertSame('advance',  $result->recommendedAction);
        $this->assertGreaterThanOrEqual(0.75, $result->confidence);
    }

    public function test_mock_incomplete_scenario(): void
    {
        $result = VisionAnalysisResult::mock('incomplete');

        $this->assertTrue($result->isMock);
        $this->assertSame('incomplete', $result->stepStatus);
        $this->assertSame('retry',      $result->recommendedAction);
    }

    public function test_mock_poor_image_scenario(): void
    {
        $result = VisionAnalysisResult::mock('poor_image');

        $this->assertSame('poor',  $result->imageQuality);
        $this->assertSame('retry', $result->recommendedAction);
        $this->assertLessThan(0.75, $result->confidence);
    }

    public function test_mock_uncertain_scenario(): void
    {
        $result = VisionAnalysisResult::mock('uncertain');

        $this->assertSame('uncertain',              $result->stepStatus);
        $this->assertSame('ask_manual_confirmation', $result->recommendedAction);
    }

    public function test_mock_unknown_scenario_falls_back_to_complete(): void
    {
        $result = VisionAnalysisResult::mock('nonexistent');
        $this->assertSame('complete', $result->stepStatus);
    }

    // ── toArray() ────────────────────────────────────────────────────────────

    public function test_to_array_contains_all_expected_keys(): void
    {
        $result = VisionAnalysisResult::fromArray($this->baseData());
        $array  = $result->toArray();

        $this->assertArrayHasKey('image_quality',              $array);
        $this->assertArrayHasKey('step_status',                $array);
        $this->assertArrayHasKey('confidence',                 $array);
        $this->assertArrayHasKey('detected_items',             $array);
        $this->assertArrayHasKey('missing_or_uncertain_items', $array);
        $this->assertArrayHasKey('feedback',                   $array);
        $this->assertArrayHasKey('recommended_action',         $array);
        $this->assertArrayHasKey('is_error',                   $array);
        $this->assertArrayHasKey('is_mock',                    $array);
    }

    // ── helpers de estado ─────────────────────────────────────────────────────

    public function test_is_complete_returns_true_only_for_complete_status(): void
    {
        $this->assertTrue(VisionAnalysisResult::fromArray($this->baseData(['step_status' => 'complete']))->isComplete());
        $this->assertFalse(VisionAnalysisResult::fromArray($this->baseData(['step_status' => 'incomplete']))->isComplete());
        $this->assertFalse(VisionAnalysisResult::fromArray($this->baseData(['step_status' => 'uncertain']))->isComplete());
    }

    public function test_is_good_quality_returns_true_only_for_good(): void
    {
        $this->assertTrue(VisionAnalysisResult::fromArray($this->baseData(['image_quality' => 'good']))->isGoodQuality());
        $this->assertFalse(VisionAnalysisResult::fromArray($this->baseData(['image_quality' => 'poor']))->isGoodQuality());
    }

    public function test_should_advance_returns_true_only_for_advance_action(): void
    {
        $this->assertTrue(VisionAnalysisResult::fromArray($this->baseData(['recommended_action' => 'advance']))->shouldAdvance());
        $this->assertFalse(VisionAnalysisResult::fromArray($this->baseData(['recommended_action' => 'retry']))->shouldAdvance());
    }

    // ── fixture ──────────────────────────────────────────────────────────────

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'image_quality'              => 'good',
            'step_status'                => 'complete',
            'confidence'                 => 0.85,
            'detected_items'             => ['item 1'],
            'missing_or_uncertain_items' => [],
            'feedback'                   => 'Feedback de teste.',
            'recommended_action'         => 'advance',
        ], $overrides);
    }
}

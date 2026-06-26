<?php

namespace Tests\Feature;

use App\Contracts\VisionProvider;
use App\Services\MockVisionProvider;
use App\ValueObjects\VisionAnalysisResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class StepAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configura provider mock para todos os testes
        Config::set('vision.provider', 'mock');
        Config::set('vision.advance_rules.auto_advance_confidence', 0.75);
        Config::set('vision.advance_rules.require_good_image', true);
        Config::set('vision.advance_rules.require_complete_status', true);
        Config::set('vision.rate_limit.analyses_per_hour', 100);

        // Bind do mock no container
        $this->app->bind(VisionProvider::class, fn() => new MockVisionProvider('complete'));
    }

    // ── GET / ────────────────────────────────────────────────────────────────

    public function test_homepage_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Cozinha Guiada');
    }

    public function test_homepage_shows_mock_mode_indicator(): void
    {
        $response = $this->get('/');
        $response->assertSee('Modo simulado');
    }

    // ── POST /session/start ───────────────────────────────────────────────────

    public function test_start_session_with_default_recipe(): void
    {
        $response = $this->postJson(route('session.start'));

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['current_step_index', 'total_steps', 'step', 'recipe']);
    }

    public function test_start_session_with_explicit_recipe_id(): void
    {
        $response = $this->postJson(route('session.start'), [
            'recipe_id' => 'iogurte-frutas-granola',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_start_session_with_invalid_recipe_returns_404(): void
    {
        $response = $this->postJson(route('session.start'), [
            'recipe_id' => 'receita-inexistente',
        ]);

        $response->assertStatus(404)->assertJson(['success' => false]);
    }

    // ── POST /session/analyze-step ────────────────────────────────────────────

    public function test_analyze_requires_active_session(): void
    {
        Session::flush();

        $image    = UploadedFile::fake()->image('photo.jpg', 400, 300);
        $response = $this->postJson(route('session.analyze-step'), ['image' => $image]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_analyze_requires_image_file(): void
    {
        $this->postJson(route('session.start'));

        $response = $this->postJson(route('session.analyze-step'), []);

        $response->assertStatus(422);
    }

    public function test_analyze_rejects_non_image_file(): void
    {
        $this->postJson(route('session.start'));

        $file     = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $response = $this->postJson(route('session.analyze-step'), ['image' => $file]);

        $response->assertStatus(422);
    }

    public function test_analyze_rejects_oversized_image(): void
    {
        $this->postJson(route('session.start'));

        // 6 MB > 5 MB limite
        $file     = UploadedFile::fake()->image('big.jpg')->size(6144);
        $response = $this->postJson(route('session.analyze-step'), ['image' => $file]);

        $response->assertStatus(422);
    }

    public function test_analyze_returns_valid_response_structure(): void
    {
        $this->postJson(route('session.start'));

        $image    = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $response = $this->postJson(route('session.analyze-step'), ['image' => $image]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'current_step_index',
                     'analysis' => [
                         'image_quality',
                         'step_status',
                         'confidence',
                         'detected_items',
                         'missing_or_uncertain_items',
                         'feedback',
                         'recommended_action',
                         'is_mock',
                     ],
                     'evaluation' => [
                         'can_advance',
                         'can_advance_manually',
                         'action',
                     ],
                 ]);
    }

    public function test_analyze_with_complete_scenario_can_advance(): void
    {
        $this->app->bind(VisionProvider::class, fn() => new MockVisionProvider('complete'));
        $this->postJson(route('session.start'));

        $image    = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $response = $this->postJson(route('session.analyze-step'), ['image' => $image]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['evaluation']['can_advance']);
        $this->assertSame('advance', $data['evaluation']['action']);
    }

    public function test_analyze_with_incomplete_scenario_cannot_advance(): void
    {
        $this->app->bind(VisionProvider::class, fn() => new MockVisionProvider('incomplete'));
        $this->postJson(route('session.start'));

        $image    = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $response = $this->postJson(route('session.analyze-step'), ['image' => $image]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertFalse($data['evaluation']['can_advance']);
    }

    public function test_analyze_with_poor_image_scenario_suggests_retry(): void
    {
        $this->app->bind(VisionProvider::class, fn() => new MockVisionProvider('poor_image'));
        $this->postJson(route('session.start'));

        $image    = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $response = $this->postJson(route('session.analyze-step'), ['image' => $image]);

        $data = $response->json();
        $this->assertFalse($data['evaluation']['can_advance']);
        $this->assertSame('retry', $data['evaluation']['action']);
    }

    public function test_analyze_with_uncertain_scenario_allows_manual_on_eligible_step(): void
    {
        $this->app->bind(VisionProvider::class, fn() => new MockVisionProvider('uncertain'));
        $this->postJson(route('session.start'));

        // Avança para etapa 1 (que permite manual advance)
        $this->postJson(route('session.advance-manually'));

        $image    = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $response = $this->postJson(route('session.analyze-step'), ['image' => $image]);

        $data = $response->json();
        $this->assertTrue($data['evaluation']['can_advance_manually'] ?? false);
    }

    // ── POST /session/advance-manually ────────────────────────────────────────

    public function test_manual_advance_works_on_eligible_step(): void
    {
        $this->postJson(route('session.start'));

        // Etapa 0 não permite avanço manual; avança para etapa 1 via mock
        $this->app->bind(VisionProvider::class, fn() => new MockVisionProvider('complete'));
        $image = UploadedFile::fake()->image('photo.jpg');
        $this->postJson(route('session.analyze-step'), ['image' => $image]);

        // Etapa 1 permite avanço manual
        $response = $this->postJson(route('session.advance-manually'));
        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_manual_advance_fails_on_ineligible_step(): void
    {
        $this->postJson(route('session.start'));

        // Etapa 0 não permite avanço manual
        $response = $this->postJson(route('session.advance-manually'));
        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    // ── POST /session/restart ─────────────────────────────────────────────────

    public function test_restart_clears_session(): void
    {
        $this->postJson(route('session.start'));

        $response = $this->postJson(route('session.restart'));
        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ── POST /session/mock-scenario ───────────────────────────────────────────

    public function test_set_mock_scenario_returns_success(): void
    {
        $response = $this->postJson(route('session.mock-scenario'), ['scenario' => 'incomplete']);
        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'scenario' => 'incomplete']);
    }

    public function test_set_mock_scenario_rejects_invalid_value(): void
    {
        $response = $this->postJson(route('session.mock-scenario'), ['scenario' => 'fake']);
        $response->assertStatus(422);
    }

    public function test_set_mock_scenario_blocked_when_bedrock_active(): void
    {
        Config::set('vision.provider', 'bedrock');

        $response = $this->postJson(route('session.mock-scenario'), ['scenario' => 'complete']);
        $response->assertStatus(403);
    }

    // ── MockVisionProvider diretamente ────────────────────────────────────────

    public function test_mock_provider_returns_correct_scenario(): void
    {
        $provider = new MockVisionProvider('complete');
        $result   = $provider->analyzeRecipeStep([], ['title' => 'Teste'], 'fake_bytes', 'image/jpeg');

        $this->assertTrue($result->isMock);
        $this->assertSame('complete', $result->stepStatus);
        $this->assertTrue($result->isComplete());
        $this->assertTrue($result->isGoodQuality());
    }

    public function test_mock_provider_defaults_to_complete_for_unknown_scenario(): void
    {
        $provider = new MockVisionProvider('inexistente');
        $result   = $provider->analyzeRecipeStep([], [], '', 'image/jpeg');

        $this->assertSame('complete', $result->stepStatus);
    }
}

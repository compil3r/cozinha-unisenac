<?php

namespace App\Http\Controllers;

use App\Contracts\VisionProvider;
use App\Services\RecipeService;
use App\Services\StepEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StepAnalysisController extends Controller
{
    public function __construct(
        private RecipeService        $recipeService,
        private StepEvaluationService $evaluationService,
        private VisionProvider        $visionProvider,
    ) {}

    /**
     * Recebe imagem, analisa a etapa atual e retorna o resultado.
     *
     * POST /session/analyze-step
     */
    public function analyze(Request $request): JsonResponse
    {
        // 1. Valida a sessão
        $session = $this->recipeService->getSession();
        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma sessão de receita ativa. Inicie a receita primeiro.',
            ], 422);
        }

        if ($session['completed'] ?? false) {
            return response()->json([
                'success' => false,
                'message' => 'A receita já foi concluída.',
            ], 422);
        }

        // 2. Valida a imagem
        $request->validate([
            'image' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png',
                'max:' . config('vision.image.max_size_kb', 5120),
            ],
        ], [
            'image.required' => 'Nenhuma imagem foi enviada.',
            'image.mimes'    => 'A imagem deve ser JPEG ou PNG.',
            'image.max'      => 'A imagem é muito grande. Tamanho máximo: 5 MB.',
        ]);

        // 3. Lê a imagem em memória (nunca salva em disco)
        $file     = $request->file('image');
        $mimeType = $file->getMimeType() ?? 'image/jpeg';

        $imageBytes = file_get_contents($file->getRealPath());

        if ($imageBytes === false || strlen($imageBytes) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível ler o arquivo de imagem.',
            ], 422);
        }

        // 4. Busca estado atual
        $state  = $this->recipeService->getCurrentState();
        $recipe = $state['recipe'];
        $step   = $state['step'];

        if (! $step) {
            return response()->json([
                'success' => false,
                'message' => 'Etapa inválida.',
            ], 422);
        }

        // 5. Chama o VisionProvider
        try {
            $analysisResult = $this->visionProvider->analyzeRecipeStep(
                recipe:     $recipe,
                step:       $step,
                imageBytes: $imageBytes,
                mimeType:   $mimeType,
            );
        } catch (\Throwable $e) {
            Log::error('StepAnalysisController: erro ao chamar VisionProvider.', [
                'message' => $e->getMessage(),
                'step'    => $step['title'] ?? 'desconhecida',
                // Nunca logar imagem ou dados do usuário
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar a análise. Tente novamente.',
            ], 500);
        }

        // 6. Aplica regras determinísticas de avanço
        $evaluation = $this->evaluationService->evaluate($analysisResult, $step);

        $finalFeedback = $this->evaluationService->buildFeedbackMessage(
            $analysisResult,
            $evaluation,
            $step
        );

        // 7. Avança sessão automaticamente se aprovado
        if ($evaluation['can_advance'] && $evaluation['action'] === 'advance') {
            $this->recipeService->advanceStep($analysisResult->toArray());
            $newState   = $this->recipeService->getCurrentState();
            $nextIndex  = $newState['current_step_index'];
            $completed  = $newState['completed'];
        } else {
            $nextIndex = $state['current_step_index'];
            $completed = false;
        }

        // 8. Monta resposta
        $responseData = $analysisResult->toArray();
        $responseData['feedback'] = $finalFeedback;

        // Debug: guarda último resultado na sessão para visualização no painel
        if (config('app.debug')) {
            session(['debug_last_bedrock' => [
                'timestamp'  => now()->toIso8601String(),
                'step'       => $step['title'],
                'is_mock'    => $responseData['is_mock'] ?? false,
                'analysis'   => $responseData,
                'evaluation' => $evaluation,
            ]]);
        }

        return response()->json([
            'success'             => true,
            'current_step_index'  => $state['current_step_index'],
            'analysis'            => $responseData,
            'evaluation'          => [
                'can_advance'          => $evaluation['can_advance'],
                'can_advance_manually' => $evaluation['can_advance_manually'],
                'action'               => $evaluation['action'],
            ],
            'advanced_to'         => $evaluation['can_advance'] ? $nextIndex : null,
            'completed'           => $completed,
        ]);
    }
}

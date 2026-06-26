<?php

namespace App\Http\Controllers;

use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(private RecipeService $recipeService) {}

    /**
     * Inicia ou reinicia a sessão com uma receita específica.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipe_id' => ['sometimes', 'string', 'max:100'],
        ]);

        $recipeId = $validated['recipe_id'] ?? config('recipes.default');

        try {
            $session = $this->recipeService->startSession($recipeId);
            $state   = $this->recipeService->getCurrentState();

            return response()->json([
                'success'            => true,
                'current_step_index' => $state['current_step_index'],
                'total_steps'        => $state['total_steps'],
                'step'               => $state['step'],
                'recipe'             => [
                    'id'    => $state['recipe']['id'],
                    'title' => $state['recipe']['title'],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Receita não encontrada.',
            ], 404);
        }
    }

    /**
     * Avança manualmente para a próxima etapa (quando permitido pela etapa atual).
     */
    public function advanceManually(Request $request): JsonResponse
    {
        $state = $this->recipeService->getCurrentState();
        $step  = $state['step'];

        if (! ($step['allow_manual_advance'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta etapa não permite avanço manual.',
            ], 422);
        }

        $this->recipeService->advanceStep(['method' => 'manual']);
        $newState = $this->recipeService->getCurrentState();

        return response()->json([
            'success'            => true,
            'completed'          => $newState['completed'],
            'current_step_index' => $newState['current_step_index'],
            'step'               => $newState['step'],
        ]);
    }

    /**
     * Reinicia a receita completamente.
     */
    public function restart(Request $request): JsonResponse|RedirectResponse
    {
        $this->recipeService->restart();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect('/');
    }

    /**
     * Define o cenário de mock (apenas quando VISION_PROVIDER=mock).
     */
    public function setMockScenario(Request $request): JsonResponse
    {
        if (config('vision.provider') !== 'mock') {
            return response()->json(['success' => false, 'message' => 'Modo mock não está ativo.'], 403);
        }

        $validated = $request->validate([
            'scenario' => ['required', 'string', 'in:complete,incomplete,poor_image,uncertain'],
        ]);

        session(['mock_scenario' => $validated['scenario']]);

        return response()->json(['success' => true, 'scenario' => $validated['scenario']]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\RecipeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(private RecipeService $recipeService) {}

    /**
     * Página principal.
     * Sem sessão → tela de seleção de receita.
     * Com sessão → receita em andamento ou tela de conclusão.
     */
    public function index(Request $request): View
    {
        $isMockMode  = config('vision.provider') === 'mock';
        $isDebugMode = (bool) env('RECIPE_DEBUG', false);

        // Sem sessão ativa → tela de seleção
        if (! $this->recipeService->getSession()) {
            return view('recipes.selection', [
                'recipes'     => $this->recipeService->listRecipes(),
                'isMockMode'  => $isMockMode,
                'isDebugMode' => $isDebugMode,
            ]);
        }

        $state        = $this->recipeService->getCurrentState();
        $mockScenario = session('mock_scenario', 'complete');

        if ($state['completed']) {
            return view('recipes.complete', [
                'state'       => $state,
                'isMockMode'  => $isMockMode,
                'isDebugMode' => $isDebugMode,
            ]);
        }

        return view('recipes.show', [
            'state'        => $state,
            'isMockMode'   => $isMockMode,
            'isDebugMode'  => $isDebugMode,
            'mockScenario' => $mockScenario,
        ]);
    }
}

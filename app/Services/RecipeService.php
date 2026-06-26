<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;
use InvalidArgumentException;

/**
 * RecipeService
 *
 * Gerencia o estado da sessão de receita do usuário.
 * Usa Laravel Session para persistir o progresso sem banco de dados.
 */
class RecipeService
{
    private const SESSION_KEY = 'recipe_session';

    /**
     * Inicia ou reinicia uma sessão de receita.
     */
    public function startSession(string $recipeId): array
    {
        $recipe = $this->findRecipe($recipeId);

        $sessionData = [
            'recipe_id'         => $recipeId,
            'current_step_index' => 0,
            'started_at'        => now()->toIso8601String(),
            'completed'         => false,
            'steps_history'     => [],
        ];

        Session::put(self::SESSION_KEY, $sessionData);

        return $sessionData;
    }

    /**
     * Retorna o estado atual da sessão de receita.
     */
    public function getSession(): ?array
    {
        return Session::get(self::SESSION_KEY);
    }

    /**
     * Retorna a receita ativa e a etapa atual.
     */
    public function getCurrentState(): array
    {
        $session = $this->getSession();

        if (! $session) {
            // Sem sessão: o controller redireciona para seleção antes de chegar aqui.
            // Fallback de segurança: inicia a receita padrão.
            $session = $this->startSession(config('recipes.default'));
        }

        $recipe = $this->findRecipe($session['recipe_id']);
        $stepIndex = $session['current_step_index'];
        $step = $recipe['steps'][$stepIndex] ?? null;

        return [
            'recipe'             => $recipe,
            'step'               => $step,
            'current_step_index' => $stepIndex,
            'total_steps'        => count($recipe['steps']),
            'completed'          => $session['completed'],
            'is_last_step'       => $stepIndex >= (count($recipe['steps']) - 1),
        ];
    }

    /**
     * Avança para a próxima etapa e registra o histórico.
     */
    public function advanceStep(?array $analysisData = null): array
    {
        $session = $this->getSession();

        if (! $session) {
            throw new \RuntimeException('Nenhuma sessão de receita ativa.');
        }

        $recipe = $this->findRecipe($session['recipe_id']);
        $totalSteps = count($recipe['steps']);
        $currentIndex = $session['current_step_index'];

        // Registra o histórico da etapa
        if ($analysisData !== null) {
            $session['steps_history'][$currentIndex] = [
                'step_index'    => $currentIndex,
                'step_title'    => $recipe['steps'][$currentIndex]['title'] ?? '',
                'completed_at'  => now()->toIso8601String(),
                'analysis'      => array_diff_key($analysisData, array_flip(['detected_items'])),
            ];
        }

        $nextIndex = $currentIndex + 1;

        if ($nextIndex >= $totalSteps) {
            // Receita concluída
            $session['completed'] = true;
            $session['completed_at'] = now()->toIso8601String();
        } else {
            $session['current_step_index'] = $nextIndex;
        }

        Session::put(self::SESSION_KEY, $session);

        return $session;
    }

    /**
     * Reinicia completamente a sessão.
     */
    public function restart(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Retorna dados de uma receita pelo ID.
     */
    public function findRecipe(string $recipeId): array
    {
        $recipe = config("recipes.available.{$recipeId}");

        if (! $recipe) {
            throw new InvalidArgumentException("Receita '{$recipeId}' não encontrada.");
        }

        return $recipe;
    }

    /**
     * Retorna todas as receitas disponíveis.
     */
    public function listRecipes(): array
    {
        return config('recipes.available', []);
    }
}

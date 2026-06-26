<?php

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object que representa o resultado de uma análise visual de etapa.
 *
 * Valida rigorosamente os dados recebidos do modelo de visão e
 * expõe uma interface tipada e segura para o restante da aplicação.
 */
final class VisionAnalysisResult
{
    public const IMAGE_QUALITY_GOOD      = 'good';
    public const IMAGE_QUALITY_POOR      = 'poor';
    public const IMAGE_QUALITY_UNCERTAIN = 'uncertain';

    public const STEP_STATUS_COMPLETE   = 'complete';
    public const STEP_STATUS_INCOMPLETE = 'incomplete';
    public const STEP_STATUS_UNCERTAIN  = 'uncertain';

    public const ACTION_RETRY               = 'retry';
    public const ACTION_ADVANCE             = 'advance';
    public const ACTION_ASK_MANUAL_CONFIRM  = 'ask_manual_confirmation';

    private const VALID_IMAGE_QUALITIES = [
        self::IMAGE_QUALITY_GOOD,
        self::IMAGE_QUALITY_POOR,
        self::IMAGE_QUALITY_UNCERTAIN,
    ];

    private const VALID_STEP_STATUSES = [
        self::STEP_STATUS_COMPLETE,
        self::STEP_STATUS_INCOMPLETE,
        self::STEP_STATUS_UNCERTAIN,
    ];

    private const VALID_ACTIONS = [
        self::ACTION_RETRY,
        self::ACTION_ADVANCE,
        self::ACTION_ASK_MANUAL_CONFIRM,
    ];

    private function __construct(
        public readonly string $imageQuality,
        public readonly string $stepStatus,
        public readonly float  $confidence,
        public readonly array  $detectedItems,
        public readonly array  $missingOrUncertainItems,
        public readonly string $feedback,
        public readonly string $recommendedAction,
        public readonly bool   $isError = false,
        public readonly bool   $isMock  = false,
    ) {}

    /**
     * Cria um resultado a partir de um array validado (JSON decodificado do modelo).
     *
     * @throws InvalidArgumentException se algum campo obrigatório estiver inválido
     */
    public static function fromArray(array $data): self
    {
        $imageQuality = $data['image_quality'] ?? null;
        if (! in_array($imageQuality, self::VALID_IMAGE_QUALITIES, true)) {
            throw new InvalidArgumentException(
                "image_quality inválido: '{$imageQuality}'. Valores aceitos: " . implode(', ', self::VALID_IMAGE_QUALITIES)
            );
        }

        $stepStatus = $data['step_status'] ?? null;
        if (! in_array($stepStatus, self::VALID_STEP_STATUSES, true)) {
            throw new InvalidArgumentException(
                "step_status inválido: '{$stepStatus}'. Valores aceitos: " . implode(', ', self::VALID_STEP_STATUSES)
            );
        }

        $confidence = $data['confidence'] ?? null;
        if (! is_numeric($confidence) || $confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException(
                "confidence inválido: deve ser um número entre 0.0 e 1.0."
            );
        }

        $detectedItems = $data['detected_items'] ?? [];
        if (! is_array($detectedItems)) {
            throw new InvalidArgumentException('detected_items deve ser um array.');
        }

        $missingItems = $data['missing_or_uncertain_items'] ?? [];
        if (! is_array($missingItems)) {
            throw new InvalidArgumentException('missing_or_uncertain_items deve ser um array.');
        }

        $feedback = trim($data['feedback'] ?? '');
        if (empty($feedback)) {
            throw new InvalidArgumentException('feedback não pode estar vazio.');
        }

        $recommendedAction = $data['recommended_action'] ?? null;
        if (! in_array($recommendedAction, self::VALID_ACTIONS, true)) {
            throw new InvalidArgumentException(
                "recommended_action inválido: '{$recommendedAction}'. Valores aceitos: " . implode(', ', self::VALID_ACTIONS)
            );
        }

        return new self(
            imageQuality:            $imageQuality,
            stepStatus:              $stepStatus,
            confidence:              (float) $confidence,
            detectedItems:           array_values(array_filter($detectedItems, 'is_string')),
            missingOrUncertainItems: array_values(array_filter($missingItems, 'is_string')),
            feedback:                $feedback,
            recommendedAction:       $recommendedAction,
            isError:                 false,
            isMock:                  (bool) ($data['_mock'] ?? false),
        );
    }

    /**
     * Cria um resultado de erro seguro para quando o modelo falha.
     */
    public static function error(string $friendlyMessage = ''): self
    {
        return new self(
            imageQuality:            self::IMAGE_QUALITY_UNCERTAIN,
            stepStatus:              self::STEP_STATUS_UNCERTAIN,
            confidence:              0.0,
            detectedItems:           [],
            missingOrUncertainItems: [],
            feedback:                $friendlyMessage ?: 'Não foi possível analisar a imagem. Tente novamente.',
            recommendedAction:       self::ACTION_RETRY,
            isError:                 true,
            isMock:                  false,
        );
    }

    /**
     * Cria um resultado de mock para simulações.
     */
    public static function mock(
        string $scenario,
        string $stepTitle = ''
    ): self {
        $scenarios = [
            'complete' => [
                'image_quality'              => self::IMAGE_QUALITY_GOOD,
                'step_status'                => self::STEP_STATUS_COMPLETE,
                'confidence'                 => 0.92,
                'detected_items'             => ['taça', 'ingredientes visíveis', $stepTitle],
                'missing_or_uncertain_items' => [],
                'feedback'                   => '✓ Ótimo! A etapa parece estar concluída. Você pode avançar.',
                'recommended_action'         => self::ACTION_ADVANCE,
            ],
            'incomplete' => [
                'image_quality'              => self::IMAGE_QUALITY_GOOD,
                'step_status'                => self::STEP_STATUS_INCOMPLETE,
                'confidence'                 => 0.80,
                'detected_items'             => ['bancada'],
                'missing_or_uncertain_items' => ['item principal da etapa'],
                'feedback'                   => 'Parece que a etapa ainda não está completa. Verifique os itens esperados e tente novamente.',
                'recommended_action'         => self::ACTION_RETRY,
            ],
            'poor_image' => [
                'image_quality'              => self::IMAGE_QUALITY_POOR,
                'step_status'                => self::STEP_STATUS_UNCERTAIN,
                'confidence'                 => 0.30,
                'detected_items'             => [],
                'missing_or_uncertain_items' => ['imagem muito escura ou fora de foco'],
                'feedback'                   => 'A imagem está com pouca iluminação ou foco. Tente em um ambiente mais iluminado.',
                'recommended_action'         => self::ACTION_RETRY,
            ],
            'uncertain' => [
                'image_quality'              => self::IMAGE_QUALITY_GOOD,
                'step_status'                => self::STEP_STATUS_UNCERTAIN,
                'confidence'                 => 0.55,
                'detected_items'             => ['bancada', 'utensílio'],
                'missing_or_uncertain_items' => ['não foi possível confirmar todos os itens'],
                'feedback'                   => 'A imagem está boa, mas não foi possível confirmar todos os elementos esperados. Você pode tentar novamente ou avançar manualmente.',
                'recommended_action'         => self::ACTION_ASK_MANUAL_CONFIRM,
            ],
        ];

        $data = $scenarios[$scenario] ?? $scenarios['complete'];
        $data['_mock'] = true;

        return self::fromArray($data);
    }

    /**
     * Serializa para array (usado nas respostas JSON da API).
     */
    public function toArray(): array
    {
        return [
            'image_quality'              => $this->imageQuality,
            'step_status'                => $this->stepStatus,
            'confidence'                 => $this->confidence,
            'detected_items'             => $this->detectedItems,
            'missing_or_uncertain_items' => $this->missingOrUncertainItems,
            'feedback'                   => $this->feedback,
            'recommended_action'         => $this->recommendedAction,
            'is_error'                   => $this->isError,
            'is_mock'                    => $this->isMock,
        ];
    }

    public function isComplete(): bool
    {
        return $this->stepStatus === self::STEP_STATUS_COMPLETE;
    }

    public function isGoodQuality(): bool
    {
        return $this->imageQuality === self::IMAGE_QUALITY_GOOD;
    }

    public function shouldAdvance(): bool
    {
        return $this->recommendedAction === self::ACTION_ADVANCE;
    }

    public function shouldRetry(): bool
    {
        return $this->recommendedAction === self::ACTION_RETRY;
    }

    public function shouldAskManualConfirmation(): bool
    {
        return $this->recommendedAction === self::ACTION_ASK_MANUAL_CONFIRM;
    }
}

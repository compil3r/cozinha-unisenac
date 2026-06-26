<?php

namespace App\Services;

use App\Contracts\VisionProvider;
use App\ValueObjects\VisionAnalysisResult;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;

/**
 * BedrockVisionProvider
 *
 * Modos de autenticação (em ordem de prioridade):
 *
 * 1. IAM credentials explícitas (.env):
 *    AWS_ACCESS_KEY_ID=...
 *    AWS_SECRET_ACCESS_KEY=...
 *    AWS_DEFAULT_REGION=us-east-1
 *
 * 2. Credenciais do ambiente (IAM role, ~/.aws/credentials, etc.)
 *    — nenhuma variável necessária
 */
class BedrockVisionProvider implements VisionProvider
{
    private BedrockRuntimeClient $client;
    private string $modelId;

    public function __construct()
    {
        $this->modelId = config('vision.bedrock.model_id', 'amazon.nova-lite-v1:0');
        $this->client  = $this->buildClient();
    }

    private function buildClient(): BedrockRuntimeClient
    {
        $region = config('vision.bedrock.region', 'us-east-1');
        $iamKey = env('AWS_ACCESS_KEY_ID');
        $iamSec = env('AWS_SECRET_ACCESS_KEY');

        // Modo 1 — IAM credentials explícitas no .env
        if (! empty($iamKey) && ! empty($iamSec)) {
            $credentials = [
                'key'    => $iamKey,
                'secret' => $iamSec,
            ];

            // Session token (credenciais temporárias STS)
            $sessionToken = env('AWS_SESSION_TOKEN');
            if (! empty($sessionToken)) {
                $credentials['token'] = $sessionToken;
            }

            return new BedrockRuntimeClient([
                'version'     => 'latest',
                'region'      => $region,
                'credentials' => $credentials,
            ]);
        }

        // Modo 2 — Credenciais do ambiente (IAM role, ~/.aws/credentials, EC2 metadata)
        return new BedrockRuntimeClient([
            'version' => 'latest',
            'region'  => $region,
        ]);
    }

    public function analyzeRecipeStep(
        array  $recipe,
        array  $step,
        string $imageBytes,
        string $mimeType
    ): VisionAnalysisResult {
        $userPrompt   = $this->buildUserPrompt($recipe, $step);
        $systemPrompt = config('vision.system_prompt');

        $messages = [
            [
                'role'    => 'user',
                'content' => [
                    [
                        'image' => [
                            'format' => $this->formatFromMime($mimeType),
                            'source' => ['bytes' => $imageBytes],
                        ],
                    ],
                    ['text' => $userPrompt],
                ],
            ],
        ];

        try {
            $result = $this->client->converse([
                'modelId'  => $this->modelId,
                'system'   => [['text' => $systemPrompt]],
                'messages' => $messages,
                'inferenceConfig' => [
                    'maxTokens'   => config('vision.bedrock.max_tokens', 1024),
                    'temperature' => config('vision.bedrock.temperature', 0.1),
                    'topP'        => config('vision.bedrock.top_p', 0.9),
                ],
            ]);

            $responseText = $result['output']['message']['content'][0]['text'] ?? null;

            if (empty($responseText)) {
                Log::warning('BedrockVisionProvider: resposta vazia.', ['step' => $step['title'] ?? '?']);
                return VisionAnalysisResult::error('O modelo não retornou uma resposta. Tente novamente.');
            }

            // Log completo da resposta bruta (visível em storage/logs/laravel.log)
            if (config('app.debug')) {
                Log::debug('[Bedrock] Resposta do modelo', [
                    'step'          => $step['title'] ?? '?',
                    'model'         => $this->modelId,
                    'input_tokens'  => $result['usage']['inputTokens']  ?? 'N/A',
                    'output_tokens' => $result['usage']['outputTokens'] ?? 'N/A',
                    'raw_response'  => $responseText,
                ]);
            }

            return $this->parseModelResponse($responseText, $step);

        } catch (AwsException $e) {
            Log::error('BedrockVisionProvider: erro AWS.', [
                'code'    => $e->getAwsErrorCode(),
                'message' => $e->getAwsErrorMessage(),
                'step'    => $step['title'] ?? '?',
            ]);

            $userMsg = match ($e->getAwsErrorCode()) {
                'ExpiredTokenException', 'TokenExpiredException'
                    => 'A chave de acesso expirou. Gere uma nova Bedrock API Key e atualize o .env.',
                'AccessDeniedException', 'UnauthorizedException'
                    => 'Acesso negado ao Bedrock. Verifique a API key e as permissões do modelo.',
                'ThrottlingException'
                    => 'Muitas requisições. Aguarde um momento e tente novamente.',
                default
                    => 'Não foi possível conectar ao serviço de visão. Tente novamente.',
            };

            return VisionAnalysisResult::error($userMsg);

        } catch (\Throwable $e) {
            Log::error('BedrockVisionProvider: erro inesperado.', ['message' => $e->getMessage()]);
            return VisionAnalysisResult::error('Ocorreu um erro inesperado. Tente novamente.');
        }
    }

    private function buildUserPrompt(array $recipe, array $step): string
    {
        $expectedItems  = implode("\n- ", $step['expected_items']  ?? []);
        $visualCriteria = implode("\n- ", $step['visual_criteria'] ?? []);
        $rejectIf       = $step['reject_if'] ?? [];

        $rejectSection = '';
        if (! empty($rejectIf)) {
            $rejectList    = implode("\n- ", $rejectIf);
            $rejectSection = <<<REJECT

**REJEITAR IMEDIATAMENTE se qualquer condição abaixo for verdadeira (step_status="incomplete", confidence <= 0.1):**
- {$rejectList}

REJECT;
        }

        return <<<PROMPT
Analise a imagem abaixo no contexto da seguinte receita e etapa:

**RECEITA:** {$recipe['title']}
**Descrição:** {$recipe['description']}

**ETAPA ATUAL:** {$step['title']}
**Instrução:** {$step['instruction']}

**Itens esperados nesta etapa:**
- {$expectedItems}

**Critérios visuais — TODOS devem ser claramente atendidos para "complete":**
- {$visualCriteria}
{$rejectSection}
Com base SOMENTE no que é visível na imagem, responda EXCLUSIVAMENTE neste formato JSON:

{
  "image_quality": "good | poor | uncertain",
  "step_status": "complete | incomplete | uncertain",
  "confidence": 0.0,
  "detected_items": ["lista de itens claramente visíveis"],
  "missing_or_uncertain_items": ["lista de itens esperados não identificados ou incertos"],
  "feedback": "Mensagem curta, prática e acolhedora em português, com no máximo 2 frases.",
  "recommended_action": "retry | advance | ask_manual_confirmation"
}

Regras:
- "good": imagem clara, bem iluminada e com elementos identificáveis
- "poor": imagem escura, fora de foco ou sem elementos relacionados à etapa
- "uncertain": imagem razoável mas com ambiguidade
- "complete": TODOS os critérios visuais claramente atendidos E nenhum critério de rejeição presente
- "incomplete": qualquer critério de rejeição presente, OU critérios claramente não atendidos
- "uncertain": não é possível determinar com clareza
- confidence: número entre 0.0 e 1.0
- "advance": somente se complete + good + confidence >= 0.75
- "ask_manual_confirmation": se uncertain ou quantidade/ação não confirmável visualmente
- "retry": se poor ou incomplete
- Seja rigoroso: em caso de dúvida, prefira "incomplete" a "complete"
PROMPT;
    }

    private function formatFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png'  => 'png',
            'image/jpeg' => 'jpeg',
            'image/webp' => 'webp',
            default      => 'jpeg',
        };
    }

    private function parseModelResponse(string $responseText, array $step): VisionAnalysisResult
    {
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($responseText));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        try {
            $data = json_decode($cleaned, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('BedrockVisionProvider: JSON inválido.', [
                'step'  => $step['title'] ?? '?',
                'error' => $e->getMessage(),
            ]);
            return VisionAnalysisResult::error('A resposta não pôde ser interpretada. Tente novamente.');
        }

        try {
            return VisionAnalysisResult::fromArray($data);
        } catch (InvalidArgumentException $e) {
            Log::warning('BedrockVisionProvider: campos inválidos na resposta.', [
                'step'  => $step['title'] ?? '?',
                'error' => $e->getMessage(),
            ]);
            return VisionAnalysisResult::error('Não foi possível interpretar a análise. Tente novamente.');
        }
    }
}

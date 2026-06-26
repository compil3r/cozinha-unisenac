<?php

namespace App\Http\Controllers;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Polly\PollyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceChatController extends Controller
{
    private BedrockRuntimeClient $bedrock;
    private PollyClient $polly;

    public function __construct()
    {
        $config = [
            'version' => 'latest',
            'region'  => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ];

        $key    = env('AWS_ACCESS_KEY_ID');
        $secret = env('AWS_SECRET_ACCESS_KEY');
        $token  = env('AWS_SESSION_TOKEN');

        if ($key && $secret) {
            $config['credentials'] = array_filter([
                'key'    => $key,
                'secret' => $secret,
                'token'  => $token ?: null,
            ]);
        }

        $this->bedrock = new BedrockRuntimeClient($config);
        $this->polly   = new PollyClient($config);
    }

    public function index()
    {
        return view('poc.voice-chat');
    }

    public function clearHistory()
    {
        session()->forget('poc_voice_history');
        return response()->json(['ok' => true]);
    }

    public function chat(Request $request)
    {
        $userText = trim($request->input('text', ''));

        if (empty($userText)) {
            return response()->json(['error' => 'Texto vazio'], 400);
        }

        // Contexto da receita ativa (se houver)
        $recipeContext = '';
        $cgSession = session('cg_session');
        if ($cgSession && !empty($cgSession['recipe'])) {
            $recipe    = $cgSession['recipe'];
            $stepIndex = $cgSession['current_step_index'] ?? 0;
            $step      = $recipe['steps'][$stepIndex] ?? null;

            $recipeContext = "\n\nContexto atual: o usuário está cozinhando \"{$recipe['title']}\".";
            if ($step) {
                $etapa = $stepIndex + 1;
                $recipeContext .= " Etapa atual ({$etapa}): \"{$step['title']}\" — {$step['instruction']}";
            }
        }

        $systemPrompt = "Você é a Polly, uma cozinheira profissional formada pelo UniSenac, simpática e animada. "
            . "Sempre que se apresentar, mencione que é formada pelo UniSenac. "
            . "Seu único papel é ajudar com dúvidas de culinária: ingredientes, técnicas, substituições, tempos de cozimento, dicas práticas. "
            . "Responda SEMPRE em português brasileiro, de forma direta e acolhedora, como se fosse uma amiga experiente na cozinha. "
            . "Limite suas respostas a no máximo 2 frases curtas — elas serão lidas em voz alta. "
            . "Quando a pessoa tiver dúvidas mais aprofundadas ou quiser aprender mais, sugira ao final da resposta que ela conheça os cursos de cozinha do UniSenac. "
            . "Nunca saia do tema culinária. Se perguntarem algo fora do assunto, redirecione gentilmente para dúvidas de cozinha."
            . $recipeContext;

        // Histórico da conversa (guardado na sessão Laravel)
        $history = session('poc_voice_history', []);
        $history[] = ['role' => 'user', 'content' => [['text' => $userText]]];

        // Limita a 20 mensagens (10 turnos) pra não estourar tokens
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        try {
            $result = $this->bedrock->converse([
                'modelId' => 'meta.llama3-70b-instruct-v1:0',
                'system'  => [['text' => $systemPrompt]],
                'messages' => $history,
                'inferenceConfig' => [
                    'maxTokens'   => 256,
                    'temperature' => 0.7,
                ],
            ]);

            $responseText = $result['output']['message']['content'][0]['text'] ?? null;

            if (empty($responseText)) {
                return response()->json(['error' => 'Sem resposta do modelo'], 500);
            }

            // Polly → áudio
            $audio = $this->polly->synthesizeSpeech([
                'Text'         => mb_substr($responseText, 0, 3000),
                'OutputFormat' => 'mp3',
                'VoiceId'      => 'Camila',
                'Engine'       => 'neural',
                'LanguageCode' => 'pt-BR',
            ]);

            $audioData = base64_encode($audio['AudioStream']->getContents());

            // Salva resposta no histórico e persiste na sessão
            $history[] = ['role' => 'assistant', 'content' => [['text' => $responseText]]];
            session(['poc_voice_history' => $history]);

            return response()->json([
                'text'  => $responseText,
                'audio' => $audioData,
            ]);

        } catch (\Throwable $e) {
            Log::error('VoiceChatController: erro.', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao processar. Tente novamente.'], 500);
        }
    }
}

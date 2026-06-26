<?php

namespace App\Http\Controllers;

use Aws\Polly\PollyClient;
use Illuminate\Http\Request;

class TtsController extends Controller
{
    public function synthesize(Request $request)
    {
        $text = $request->input('text', '');

        if (empty(trim($text))) {
            return response()->json(['error' => 'Texto vazio'], 400);
        }

        $polly = new PollyClient([
            'version' => 'latest',
            'region'  => config('services.aws.region', env('AWS_DEFAULT_REGION', 'us-east-1')),
        ]);

        $result = $polly->synthesizeSpeech([
            'Text'         => mb_substr($text, 0, 3000),
            'OutputFormat' => 'mp3',
            'VoiceId'      => 'Camila',
            'Engine'       => 'neural',
            'LanguageCode' => 'pt-BR',
        ]);

        $audio = $result['AudioStream']->getContents();

        return response($audio, 200)
            ->header('Content-Type', 'audio/mpeg')
            ->header('Cache-Control', 'no-store');
    }
}

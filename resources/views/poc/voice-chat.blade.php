<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polly — Assistente de Cozinha</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(180deg, #0d1f08 0%, #111410 50%, #0a0d08 100%);
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #fff;
            overflow: hidden;
        }

        /* ── Topo: info da chamada ── */
        #call-header {
            width: 100%;
            padding: max(env(safe-area-inset-top, 20px), 20px) 24px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        #call-status {
            font-size: 12px;
            color: rgba(107,155,74,.8);
            letter-spacing: .05em;
        }
        #btn-back {
            background: rgba(255,255,255,.08);
            border: none;
            border-radius: 20px;
            padding: 6px 14px;
            color: rgba(255,255,255,.5);
            font-size: 12px;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        /* ── Avatar / robo ── */
        #avatar-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0;
            padding: 24px 0 0;
        }

        #robo {
            width: 200px;
            height: 200px;
            object-fit: contain;
            object-position: center;
            filter: drop-shadow(0 16px 48px rgba(0,0,0,.7));
            transition: opacity .25s ease;
            border-radius: 50%;
        }
        #robo.speaking { animation: roboFloat 1.4s ease-in-out infinite; }
        #robo.listening { animation: roboFloat 2.5s ease-in-out infinite; }
        #robo.idle { animation: none; }

        /* ── Nome / estado ── */
        #assistant-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.3px;
            margin-top: 16px;
        }
        #call-state {
            font-size: 13px;
            color: rgba(255,255,255,.4);
            margin-top: 4px;
            min-height: 20px;
            transition: opacity .3s;
        }

        /* ── Ondas de áudio (animação fala) ── */
        #wave {
            display: flex;
            align-items: center;
            gap: 4px;
            height: 28px;
            margin-top: 12px;
            opacity: 0;
            transition: opacity .3s;
        }
        #wave.active { opacity: 1; }
        #wave span {
            display: block;
            width: 4px;
            border-radius: 4px;
            background: rgba(107,155,74,.8);
            animation: wave 1s ease-in-out infinite;
        }
        #wave span:nth-child(1) { height: 8px;  animation-delay: 0s; }
        #wave span:nth-child(2) { height: 20px; animation-delay: .1s; }
        #wave span:nth-child(3) { height: 14px; animation-delay: .2s; }
        #wave span:nth-child(4) { height: 24px; animation-delay: .15s; }
        #wave span:nth-child(5) { height: 10px; animation-delay: .05s; }
        #wave span:nth-child(6) { height: 18px; animation-delay: .25s; }
        #wave span:nth-child(7) { height: 8px;  animation-delay: .1s; }

        /* ── Balão de resposta ── */
        #bubble {
            width: calc(100% - 48px);
            max-width: 380px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px;
            padding: 14px 16px;
            font-size: 14px;
            line-height: 1.55;
            color: rgba(255,255,255,.85);
            min-height: 56px;
            text-align: center;
            margin: 16px 0;
            transition: opacity .3s;
        }
        #bubble.empty { color: rgba(255,255,255,.25); font-style: italic; }

        /* ── Rodapé: controles ── */
        #controls {
            width: 100%;
            padding: 0 32px max(env(safe-area-inset-bottom, 32px), 32px);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        /* Transcrição discreta */
        #transcript-bar {
            font-size: 12px;
            color: rgba(255,255,255,.3);
            min-height: 18px;
            text-align: center;
        }

        /* Botões de ação */
        #action-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 32px;
        }

        .ctrl-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(255,255,255,.5);
            font-size: 11px;
            font-family: inherit;
        }
        .ctrl-btn-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(255,255,255,.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: background .15s;
        }
        .ctrl-btn:hover .ctrl-btn-icon { background: rgba(255,255,255,.18); }

        /* Botão falar — principal */
        #btn-talk {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, #3a7a1a 0%, #6b9b4a 100%);
            box-shadow: 0 6px 32px rgba(107,155,74,.45);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            transition: transform .12s, background .2s, box-shadow .2s;
            -webkit-tap-highlight-color: transparent;
        }
        #btn-talk.listening {
            background: linear-gradient(135deg, #b02020 0%, #e74c3c 100%);
            box-shadow: 0 6px 40px rgba(231,76,60,.55);
            animation: pulse 1.2s ease-in-out infinite;
        }
        #btn-talk:disabled { opacity: .35; cursor: not-allowed; }

        /* Encerrar */
        #btn-end {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            background: rgba(220,50,50,.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: background .15s;
        }
        #btn-end:hover { background: rgba(220,50,50,.45); }

        @keyframes roboFloat {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-12px); }
        }
        @keyframes pulse {
            0%,100% { box-shadow: 0 6px 40px rgba(231,76,60,.4); }
            50%      { box-shadow: 0 6px 64px rgba(231,76,60,.75); }
        }
        @keyframes wave {
            0%,100% { transform: scaleY(1); }
            50%      { transform: scaleY(2); }
        }
    </style>
</head>
<body>

    {{-- ── TOPO ── --}}
    <div id="call-header">
        <span id="call-status">● Em chamada</span>
        <button id="btn-back" onclick="window.location.href='{{ route('recipe.selection') }}'">
            ← Receitas
        </button>
    </div>

    {{-- ── AVATAR ── --}}
    <div id="avatar-area">
        <img id="robo" class="idle"
             src="{{ asset('images/robo/icone.png') }}" alt="Polly" />

        <div id="assistant-name">Polly</div>
        <div id="call-state">Assistente de cozinha</div>

        <div id="wave">
            <span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span>
        </div>
    </div>

    {{-- ── BALÃO ── --}}
    <div id="bubble" class="empty">
        Pressione o microfone e me faça uma pergunta 🍳
    </div>

    {{-- ── CONTROLES ── --}}
    <div id="controls">
        <div id="transcript-bar"></div>

        <div id="action-row">
            {{-- Limpar histórico --}}
            <button class="ctrl-btn" onclick="clearHistory()">
                <div class="ctrl-btn-icon">🗑</div>
                <span>Limpar</span>
            </button>

            {{-- Falar --}}
            <button id="btn-talk" title="Falar">🎤</button>

            {{-- Encerrar chamada --}}
            <button id="btn-end" onclick="window.location.href='{{ route('recipe.selection') }}'"
                title="Encerrar">
                📵
            </button>
        </div>
    </div>

<script>
const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
const robo       = document.getElementById('robo');
const bubble     = document.getElementById('bubble');
const callState  = document.getElementById('call-state');
const wave       = document.getElementById('wave');
const btnTalk    = document.getElementById('btn-talk');
const transcript = document.getElementById('transcript-bar');

const ROBO = {
    falando:    '{{ asset("images/robo/icone.png") }}',
    aguardando: '{{ asset("images/robo/icone.png") }}',
    deliciando: '{{ asset("images/robo/icone.png") }}',
    triste:     '{{ asset("images/robo/icone.png") }}',
};

let recognition  = null;
let isListening  = false;
let isProcessing = false;
let currentAudio = null;
let finalText    = '';

// ── Estado visual ──────────────────────────────────────────────────────────
function setState(state) {
    const states = {
        idle:       { img: 'falando',    cls: 'idle',      label: 'Assistente de cozinha', waveOn: false },
        listening:  { img: 'falando',    cls: 'listening', label: 'Ouvindo...', waveOn: false },
        thinking:   { img: 'aguardando', cls: 'idle',      label: 'Pensando...', waveOn: false },
        speaking:   { img: 'falando',    cls: 'speaking',  label: 'Falando...', waveOn: true },
        error:      { img: 'triste',     cls: 'idle',      label: 'Ops, algo deu errado', waveOn: false },
    };
    const s = states[state] || states.idle;

    robo.style.opacity = '0';
    setTimeout(() => { robo.src = ROBO[s.img]; robo.style.opacity = '1'; }, 180);
    robo.className = s.cls;
    callState.textContent = s.label;
    wave.classList.toggle('active', s.waveOn);
}

// ── Web Speech API ──────────────────────────────────────────────────────────
function setup() {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
        btnTalk.disabled = true;
        callState.textContent = 'Reconhecimento de voz não suportado. Use Chrome.';
        return;
    }
    recognition = new SR();
    recognition.lang = 'pt-BR';
    recognition.interimResults = true;
    recognition.continuous = false;

    recognition.onstart = () => {
        isListening = true;
        finalText = '';
        setState('listening');
        btnTalk.classList.add('listening');
        transcript.textContent = '';
    };

    recognition.onresult = (e) => {
        let interim = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
            const t = e.results[i][0].transcript;
            if (e.results[i].isFinal) finalText += t;
            else interim += t;
        }
        transcript.textContent = finalText || interim;
    };

    recognition.onend = () => {
        isListening = false;
        btnTalk.classList.remove('listening');
        const text = finalText.trim();
        if (text) {
            send(text);
        } else {
            setState('idle');
            transcript.textContent = '';
        }
    };

    recognition.onerror = (e) => {
        isListening = false;
        btnTalk.classList.remove('listening');
        if (e.error !== 'no-speech') setState('error');
        else setState('idle');
    };
}

btnTalk.addEventListener('click', () => {
    if (isProcessing) return;
    if (isListening) { recognition.stop(); return; }
    if (currentAudio) { currentAudio.pause(); currentAudio = null; }
    recognition.start();
});

// ── Envio ──────────────────────────────────────────────────────────────────
async function send(text) {
    isProcessing = true;
    btnTalk.disabled = true;
    setState('thinking');

    try {
        const res  = await fetch('/poc-voz', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ text }),
        });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'Erro');

        bubble.classList.remove('empty');
        bubble.textContent = data.text;
        transcript.textContent = '';

        setState('speaking');
        const audio = new Audio(URL.createObjectURL(b64toBlob(data.audio, 'audio/mpeg')));
        currentAudio = audio;
        audio.play().catch(() => {});
        audio.onended = audio.onerror = () => {
            URL.revokeObjectURL(audio.src);
            currentAudio = null;
            setState('idle');
            finish();
        };

    } catch (e) {
        bubble.textContent = 'Ops, algo deu errado. Tente novamente.';
        bubble.classList.remove('empty');
        setState('error');
        finish();
    }
}

function finish() {
    isProcessing = false;
    btnTalk.disabled = false;
}

async function clearHistory() {
    await fetch('/poc-voz/clear', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } }).catch(() => {});
    bubble.textContent = 'Conversa reiniciada. O que vamos preparar hoje? 🍳';
    bubble.classList.remove('empty');
    setState('idle');
}

function b64toBlob(b64, mime) {
    const bytes = atob(b64);
    const arr = new Uint8Array(bytes.length);
    for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
    return new Blob([arr], { type: mime });
}

setup();
</script>
</body>
</html>

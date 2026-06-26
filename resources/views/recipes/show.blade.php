@extends('layouts.camera')
@section('title', $state['recipe']['title'] . ' — Cozinha Guiada')

@section('content')
<script>
window.__CG__ = {
    csrfToken:    "{{ csrf_token() }}",
    isMockMode:   {{ $isMockMode ? 'true' : 'false' }},
    mockScenario: "{{ $mockScenario ?? 'complete' }}",
    currentStep:  {{ $state['current_step_index'] }},
    totalSteps:   {{ $state['total_steps'] }},
    steps:        {!! json_encode(array_values($state['recipe']['steps'])) !!},
    maxImageWidth: 1280,
    recipe: {
        id:          "{{ $state['recipe']['id'] }}",
        title:       "{{ addslashes($state['recipe']['title']) }}",
        description: "{{ addslashes($state['recipe']['description']) }}"
    },
    routes: {
        analyze:         "{{ route('session.analyze-step') }}",
        advanceManually: "{{ route('session.advance-manually') }}",
        restart:         "{{ route('session.restart') }}",
        mockScenario:    "{{ route('session.mock-scenario') }}",
        debug:           "{{ config('app.debug') ? route('debug.bedrock') : '' }}"
    },
    isDebug: {{ $isDebugMode ? 'true' : 'false' }},
    roboImages: {
        falando:    "{{ asset('images/robo/falando.png') }}",
        deliciando: "{{ asset('images/robo/deliciando.png') }}",
        triste:     "{{ asset('images/robo/triste.png') }}",
    },
};
</script>

{{-- ═══════════════════════════════════════════════════════════
     CONTAINER FULLSCREEN
═══════════════════════════════════════════════════════════ --}}
<div id="cg-app" style="position:fixed;inset:0;background:#000;overflow:hidden;">

    {{-- Fundo / câmera --}}
    <video id="camera-video"
        style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;"
        playsinline autoplay muted aria-label="Câmera ao vivo"></video>

    <canvas id="camera-canvas"
        style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;"
        aria-label="Foto capturada"></canvas>

    {{-- Gradientes de legibilidade --}}
    <div id="gradient-top"
        style="position:absolute;top:0;left:0;right:0;height:160px;
               background:linear-gradient(to bottom,rgba(0,0,0,.65) 0%,transparent 100%);
               pointer-events:none;display:none;"></div>
    <div id="gradient-bottom"
        style="position:absolute;bottom:0;left:0;right:0;height:280px;
               background:linear-gradient(to top,rgba(0,0,0,.75) 0%,transparent 100%);
               pointer-events:none;display:none;"></div>


    {{-- ─────────────────────────────────────────────────────────
         TELA INICIAL (antes de iniciar)
    ───────────────────────────────────────────────────────── --}}
    <div id="screen-start"
        style="position:absolute;inset:0;display:flex;flex-direction:column;
               align-items:center;justify-content:center;
               background:linear-gradient(160deg,#1a1a18 0%,#0d1a0a 100%);gap:0;">

        {{-- Logo --}}
        <div style="text-align:center;margin-bottom:48px;">
            <div style="font-size:48px;margin-bottom:16px;">🍴</div>
            <h1 style="color:#fff;font-size:26px;font-weight:600;letter-spacing:-.5px;margin-bottom:6px;">
                Cozinha Guiada
            </h1>
            <p style="color:rgba(255,255,255,.5);font-size:13px;line-height:1.4;max-width:240px;margin:0 auto;">
                {{ $state['recipe']['title'] }}
            </p>
        </div>

        {{-- Preview das etapas --}}
        <div style="display:flex;gap:8px;margin-bottom:48px;">
            @foreach($state['recipe']['steps'] as $i => $step)
                <div style="width:8px;height:8px;border-radius:50%;
                    background:{{ $i === 0 ? '#6b9b4a' : 'rgba(255,255,255,.2)' }};"></div>
            @endforeach
        </div>

        {{-- Botão Iniciar --}}
        <button id="btn-start" onclick="CozinhaGuiada.start()"
            style="background:#fff;color:#1a1a18;border:none;
                   font-family:inherit;font-size:16px;font-weight:600;
                   padding:16px 48px;border-radius:100px;cursor:pointer;
                   letter-spacing:-.2px;transition:transform .15s,opacity .15s;
                   box-shadow:0 8px 32px rgba(0,0,0,.4);"
            onmousedown="this.style.transform='scale(.97)'"
            onmouseup="this.style.transform=''"
            ontouchstart="this.style.transform='scale(.97)'"
            ontouchend="this.style.transform=''">
            Iniciar receita
        </button>

        {{-- Status do provider --}}
        <div style="position:absolute;bottom:32px;left:0;right:0;text-align:center;">
            @if($isMockMode)
                <span style="color:rgba(255,255,255,.35);font-size:12px;">● Modo simulado</span>
            @else
                <span style="color:rgba(107,155,74,.7);font-size:12px;">● IA conectada</span>
            @endif
        </div>
    </div>


    {{-- ─────────────────────────────────────────────────────────
         OVERLAYS (visíveis durante a câmera)
    ───────────────────────────────────────────────────────── --}}

    {{-- TOP-LEFT: etapa atual + progresso --}}
    <div id="overlay-step"
        style="display:none;position:absolute;top:env(safe-area-inset-top,16px);left:16px;
               padding-top:max(env(safe-area-inset-top,0px),16px);">
        <div style="background:rgba(0,0,0,.5);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
                    border-radius:14px;padding:10px 14px;min-width:0;max-width:200px;">
            <div style="color:rgba(255,255,255,.55);font-size:11px;font-weight:500;margin-bottom:3px;"
                 id="step-counter">Etapa 1 de {{ $state['total_steps'] }}</div>
            <div style="color:#fff;font-size:14px;font-weight:600;line-height:1.2;"
                 id="step-title-overlay">{{ $state['step']['title'] ?? '' }}</div>
        </div>

        {{-- Pontos de progresso --}}
        <div style="display:flex;gap:5px;margin-top:10px;padding-left:4px;" id="progress-dots">
            @foreach($state['recipe']['steps'] as $i => $step)
                <div class="dot" data-index="{{ $i }}"
                    style="width:6px;height:6px;border-radius:50%;transition:all .3s;
                           background:{{ $i === $state['current_step_index'] ? '#fff' : 'rgba(255,255,255,.3)' }};
                           transform:{{ $i === $state['current_step_index'] ? 'scale(1.3)' : 'scale(1)' }};"></div>
            @endforeach
        </div>
    </div>

    {{-- TOP-RIGHT: status + reiniciar --}}
    <div id="overlay-controls"
        style="display:none;position:absolute;right:16px;
               padding-top:max(env(safe-area-inset-top,0px),16px);
               display:none;flex-direction:column;align-items:flex-end;gap:8px;">
        @if($isMockMode)
            <div style="background:rgba(0,0,0,.5);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
                        border-radius:10px;padding:6px 10px;color:rgba(255,200,50,.85);font-size:11px;font-weight:500;">
                Simulado
            </div>
        @else
            <div style="background:rgba(0,0,0,.5);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
                        border-radius:10px;padding:6px 10px;color:rgba(107,155,74,.9);font-size:11px;font-weight:500;">
                IA ativa
            </div>
        @endif
        <button onclick="CozinhaGuiada.restart()"
            style="background:rgba(0,0,0,.45);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
                   border:1px solid rgba(255,255,255,.15);border-radius:10px;padding:6px 12px;
                   color:rgba(255,255,255,.7);font-size:11px;font-family:inherit;cursor:pointer;">
            ↩ Receitas
        </button>
        @if($isDebugMode)
        <button id="btn-debug" onclick="CozinhaGuiada.toggleDebug()"
            style="background:rgba(4,6,20,.6);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
                   border:1px solid rgba(80,100,255,.3);border-radius:10px;padding:6px 10px;
                   color:rgba(120,150,255,.7);font-size:11px;font-family:inherit;cursor:pointer;">
            🔍
        </button>
        @endif
    </div>

    {{-- BOTTOM: card único com instrução + feedback + botão --}}
    <div id="overlay-bottom"
        style="display:none;position:absolute;bottom:0;left:0;right:0;
               padding:0 16px max(env(safe-area-inset-bottom,20px),20px) 16px;">

        <div id="bottom-card"
            style="background:rgba(10,10,8,.72);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
                   border:1px solid rgba(255,255,255,.1);border-radius:22px;padding:18px 18px 14px 18px;
                   transition:background .35s ease,border-color .35s ease;">

            {{-- ── LINHA ROBÔ + CONTEÚDO ── --}}
            <div style="display:flex;align-items:flex-end;gap:10px;margin-bottom:11px;">

                {{-- Robozinha --}}
                <img id="robo-char"
                     src="{{ asset('images/robo/falando.png') }}"
                     alt=""
                     style="width:72px;height:108px;object-fit:contain;object-position:bottom center;
                            flex-shrink:0;transition:opacity .2s ease;" />

                {{-- Conteúdo: instrução ↔ feedback --}}
                <div style="flex:1;min-width:0;">

                    {{-- ── VISTA INSTRUÇÃO (padrão) ── --}}
                    <div id="view-instruction">
                        <p id="instruction-text"
                            style="color:#fff;font-size:14px;line-height:1.45;margin:0 0 10px 0;">
                            {{ $state['step']['instruction'] ?? '' }}
                        </p>

                        {{-- Erro inline (câmera, conexão, etc.) --}}
                        <div id="card-error"
                            style="display:none;background:rgba(140,20,20,.35);
                                   border:1px solid rgba(220,60,60,.3);border-radius:12px;
                                   padding:9px 12px;margin-bottom:10px;">
                            <div style="display:flex;align-items:flex-start;gap:8px;">
                                <span style="font-size:14px;flex-shrink:0;">⚠️</span>
                                <span id="card-error-text"
                                    style="color:#ffa0a0;font-size:13px;line-height:1.4;flex:1;"></span>
                                <button onclick="CozinhaGuiada.clearCardError()"
                                    style="color:rgba(255,255,255,.3);background:none;border:none;
                                           font-size:14px;cursor:pointer;flex-shrink:0;padding:0 0 0 4px;line-height:1;">✕</button>
                            </div>
                        </div>

                        {{-- Checklist de itens --}}
                        <div id="checklist-items" style="display:flex;flex-wrap:wrap;gap:5px;">
                            @foreach(($state['step']['expected_items'] ?? []) as $item)
                                <span style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.13);
                                             border-radius:20px;padding:3px 10px;color:rgba(255,255,255,.65);
                                             font-size:12px;white-space:nowrap;">{{ $item }}</span>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── VISTA FEEDBACK (pós-análise) ── --}}
                    <div id="view-feedback" style="display:none;">
                        <p id="fb-text"
                            style="color:#fff;font-size:14px;line-height:1.45;margin:0 0 10px 0;"></p>
                        {{-- Barra de confiança --}}
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                            <span style="color:rgba(255,255,255,.35);font-size:11px;white-space:nowrap;">Confiança</span>
                            <div style="flex:1;height:3px;border-radius:2px;background:rgba(255,255,255,.12);">
                                <div id="fb-conf-bar"
                                    style="height:3px;border-radius:2px;background:#6b9b4a;width:0%;transition:width .6s ease;"></div>
                            </div>
                            <span id="fb-conf-val"
                                style="color:rgba(255,255,255,.4);font-size:11px;min-width:30px;text-align:right;"></span>
                        </div>
                        {{-- Itens detectados --}}
                        <div id="fb-detected" style="display:none;margin-bottom:6px;">
                            <div style="color:rgba(255,255,255,.3);font-size:11px;margin-bottom:5px;">Detectado:</div>
                            <div id="fb-detected-list" style="display:flex;flex-wrap:wrap;gap:4px;"></div>
                        </div>
                    </div>

                </div>{{-- /conteúdo --}}

                {{-- Botão ouvir --}}
                <button id="btn-listen" onclick="CozinhaGuiada.speakInstruction()"
                    title="Ouvir instrução"
                    style="flex-shrink:0;align-self:flex-start;width:32px;height:32px;border-radius:50%;
                           background:rgba(255,255,255,.12);border:none;cursor:pointer;
                           display:flex;align-items:center;justify-content:center;
                           color:#fff;font-size:15px;transition:background .15s;"
                    onmouseenter="this.style.background='rgba(255,255,255,.22)'"
                    onmouseleave="this.style.background='rgba(255,255,255,.12)'">🔊</button>

            </div>{{-- /linha robô --}}

            {{-- ── SEPARADOR ── --}}
            <div style="height:1px;background:rgba(255,255,255,.07);margin:13px 0 13px;"></div>

            {{-- ── BOTÃO PRINCIPAL (criativo, colorido) ── --}}
            <div style="display:flex;justify-content:center;margin-bottom:10px;">
                <button id="btn-capture" onclick="CozinhaGuiada.captureOrVerify()" disabled
                    style="padding:14px 44px;border-radius:100px;
                           background:linear-gradient(135deg,#b85c08 0%,#e8941a 100%);
                           color:#fff;border:none;font-family:inherit;font-size:17px;font-weight:800;
                           cursor:not-allowed;opacity:.32;letter-spacing:-.1px;white-space:nowrap;
                           transition:opacity .3s,box-shadow .35s;box-shadow:none;"
                    aria-label="Capturar foto">
                    📸 Pronto!
                </button>
            </div>

            {{-- ── AÇÕES SECUNDÁRIAS ── --}}
            <div style="display:flex;align-items:center;gap:8px;">
                <div id="manual-advance-area"
                    style="{{ ($state['step']['allow_manual_advance'] ?? false) ? '' : 'visibility:hidden;' }}">
                    <button id="btn-advance-manually" onclick="CozinhaGuiada.advanceManually()"
                        style="background:none;border:none;color:rgba(255,255,255,.35);
                               font-size:12px;font-family:inherit;cursor:pointer;padding:0;
                               text-decoration:underline;text-underline-offset:3px;">
                        Avançar mesmo assim
                    </button>
                </div>
                <div style="flex:1;"></div>
                @if($isMockMode)
                <button onclick="CozinhaGuiada.toggleMockPanel()"
                    style="background:rgba(255,200,50,.12);border:1px solid rgba(255,200,50,.22);
                           border-radius:10px;padding:5px 10px;color:rgba(255,200,50,.65);
                           font-size:11px;font-family:inherit;cursor:pointer;">🔧</button>
                @endif
            </div>
        </div>
    </div>

    {{-- CENTRO: spinner de análise --}}
    <div id="overlay-loading"
        style="display:none;position:absolute;inset:0;
               background:rgba(0,0,0,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
               align-items:center;justify-content:center;flex-direction:column;gap:14px;">
        <div id="spinner"
            style="width:48px;height:48px;border-radius:50%;
                   border:3px solid rgba(255,255,255,.2);
                   border-top-color:#fff;
                   animation:spin .8s linear infinite;"></div>
        <p id="loading-msg"
            style="color:rgba(255,255,255,.8);font-size:14px;font-weight:500;">
            Analisando...
        </p>
    </div>

    {{-- PAINEL DEBUG (APP_DEBUG=true) --}}
    @if($isDebugMode)
    <div id="debug-panel"
        style="display:none;position:absolute;bottom:0;left:0;right:0;padding:16px 16px 32px 16px;z-index:60;">
        <div style="background:rgba(4,6,20,.93);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
                    border:1px solid rgba(80,100,255,.25);border-radius:20px;padding:16px;max-height:320px;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <span style="color:rgba(140,160,255,.85);font-weight:600;font-size:12px;">🔍 Último retorno Bedrock</span>
                <div style="display:flex;gap:6px;">
                    <button onclick="CozinhaGuiada.refreshDebug()"
                        style="background:rgba(80,100,255,.15);border:1px solid rgba(80,100,255,.3);
                               border-radius:7px;padding:3px 9px;color:rgba(140,160,255,.8);
                               font-size:11px;font-family:inherit;cursor:pointer;">↻ Atualizar</button>
                    <button onclick="CozinhaGuiada.toggleDebug()"
                        style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
                               border-radius:7px;padding:3px 9px;color:rgba(255,255,255,.4);
                               font-size:11px;font-family:inherit;cursor:pointer;">Fechar</button>
                </div>
            </div>
            <pre id="debug-content"
                style="color:rgba(180,210,255,.75);white-space:pre-wrap;margin:0;
                       font-size:10px;font-family:monospace;line-height:1.55;"></pre>
        </div>
    </div>
    @endif

    {{-- PAINEL MOCK (toggle) --}}
    @if($isMockMode)
    <div id="mock-panel"
        style="display:none;position:absolute;bottom:0;left:0;right:0;
               padding:16px 16px 32px 16px;">
        <div style="background:rgba(20,16,0,.85);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
                    border:1px solid rgba(255,200,50,.2);border-radius:20px;padding:16px;">
            <div style="color:rgba(255,200,50,.7);font-size:11px;font-weight:600;text-transform:uppercase;
                        letter-spacing:.05em;margin-bottom:12px;">
                🔧 Cenários simulados
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                @foreach([
                    ['complete',   '✓ Etapa concluída',  '#1a2e12','#6b9b4a'],
                    ['incomplete', '✗ Item ausente',      '#2e1212','#e05252'],
                    ['poor_image', '📷 Imagem ruim',      '#1e1e1e','#888'],
                    ['uncertain',  '? Incerto',           '#121830','#6090e0'],
                ] as [$s, $label, $bg, $color])
                    <button onclick="CozinhaGuiada.setMockScenario('{{ $s }}')"
                        data-scenario="{{ $s }}"
                        class="mock-btn"
                        style="background:{{ $bg }};border:1px solid {{ $color }}40;border-radius:12px;
                               padding:10px 12px;color:{{ $color }};font-size:13px;
                               font-family:inherit;cursor:pointer;text-align:left;
                               {{ $mockScenario === $s ? 'box-shadow:0 0 0 2px '.$color.';' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <p style="color:rgba(255,255,255,.3);font-size:11px;text-align:center;">
                A análise não depende da foto neste modo.
            </p>
            <button onclick="CozinhaGuiada.toggleMockPanel()"
                style="display:block;width:100%;margin-top:12px;background:rgba(255,255,255,.06);
                       border:none;border-radius:10px;padding:8px;color:rgba(255,255,255,.4);
                       font-family:inherit;font-size:12px;cursor:pointer;">
                Fechar
            </button>
        </div>
    </div>
    @endif

</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes feedbackIn {
    from { transform: translateY(20px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
</style>

@endsection

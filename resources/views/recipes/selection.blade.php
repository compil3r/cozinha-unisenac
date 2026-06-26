@extends('layouts.camera')
@section('title', 'Escolha uma receita — Cozinha Guiada')

@section('content')
<script>
window.__CGS__ = {
    csrfToken: "{{ csrf_token() }}",
    routes: {
        start: "{{ route('session.start') }}",
        tts:   "/tts",
    }
};
</script>

{{-- ── OVERLAY DE CARREGAMENTO ─────────────────────────────────────────────── --}}
<div id="loading-overlay"
    style="position:fixed;inset:0;z-index:100;
           background:linear-gradient(170deg,#0f1c0a 0%,#1a1a16 60%,#0a0f08 100%);
           display:flex;flex-direction:column;align-items:center;justify-content:center;gap:28px;
           transition:opacity .5s ease;">

    <img src="{{ asset('images/robo/icone.png') }}"
         style="width:130px;height:130px;object-fit:contain;
                animation:roboFloat 2s ease-in-out infinite;" />

    <div style="text-align:center;">
        <p id="loading-msg-text"
           style="color:rgba(255,255,255,.85);font-size:16px;font-weight:600;
                  margin:0 0 6px;transition:opacity .3s ease;"></p>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:10px;">
            <div style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.4);animation:dot 1.2s .0s ease-in-out infinite;"></div>
            <div style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.4);animation:dot 1.2s .2s ease-in-out infinite;"></div>
            <div style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.4);animation:dot 1.2s .4s ease-in-out infinite;"></div>
        </div>
    </div>
</div>

<style>
@keyframes roboFloat {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-10px); }
}
@keyframes dot {
    0%,80%,100% { opacity: .2; transform: scale(.8); }
    40%          { opacity: 1;  transform: scale(1.2); }
}
</style>

<div style="position:fixed;inset:0;overflow-y:auto;
            background:linear-gradient(170deg,#0f1c0a 0%,#1a1a16 60%,#0a0f08 100%);">

    {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
    <div style="display:flex;align-items:flex-end;gap:0;
                padding:48px 24px 0;max-width:480px;margin:0 auto;">

        {{-- Robozinha grande --}}
        <img id="robo-greeting"
             src="{{ asset('images/robo/icone.png') }}"
             style="width:150px;height:150px;object-fit:contain;object-position:center;
                    flex-shrink:0;margin-bottom:4px;
                    filter:drop-shadow(0 12px 32px rgba(0,0,0,.6));
                    transition:opacity .3s;" />

        {{-- Balão de fala --}}
        <div style="flex:1;min-width:0;margin-left:12px;margin-bottom:28px;">
            <div style="background:rgba(255,255,255,.08);
                        border:1px solid rgba(255,255,255,.12);
                        border-radius:18px;
                        padding:14px 16px;">
                <p style="color:#fff;font-size:14px;line-height:1.5;margin:0 0 4px;font-weight:600;">
                    Olá! Eu sou a Polly 👋
                </p>
                <p style="color:rgba(255,255,255,.6);font-size:13px;line-height:1.45;margin:0;">
                    Cozinheira formada pelo UniSenac. O que vamos preparar hoje?
                </p>
            </div>
        </div>
    </div>

    {{-- separador visual entre header e lista --}}
    <div style="max-width:480px;margin:20px auto 0;padding:0 24px;">
        <div style="height:1px;background:rgba(255,255,255,.07);"></div>
        <p style="color:rgba(255,255,255,.3);font-size:11px;
                  text-transform:uppercase;letter-spacing:.08em;
                  margin:14px 0 0;">Escolha uma receita</p>
    </div>

    {{-- ── GRADE DE RECEITAS ───────────────────────────────────────────────── --}}
    <div style="padding:0 16px 48px;display:flex;flex-direction:column;gap:14px;max-width:480px;margin:0 auto;">

        @foreach($recipes as $recipeId => $recipe)
        @php
            $isDebug   = ($recipe['difficulty'] ?? '') === 'debug';
            $imagePath = public_path($recipe['image'] ?? '');
            $hasImage  = !empty($recipe['image']) && file_exists($imagePath);
            $stepCount = count($recipe['steps'] ?? []);

            // cores por receita
            $accent = match($recipeId) {
                'iogurte-frutas-granola'       => ['border'=>'#6aaa30','glow'=>'rgba(106,170,48,.25)','badge'=>'rgba(106,170,48,.18)','badgeText'=>'#a8e070'],
                'sanduiche-queijo-presunto'    => ['border'=>'#e8941a','glow'=>'rgba(232,148,26,.22)','badge'=>'rgba(232,148,26,.18)','badgeText'=>'#f0b860'],
                default /* debug */            => ['border'=>'#6090e0','glow'=>'rgba(90,140,224,.2)','badge'=>'rgba(90,140,224,.18)','badgeText'=>'#90b8ff'],
            };
        @endphp

        <button onclick="CGSelection.start('{{ $recipeId }}')"
            style="width:100%;text-align:left;background:rgba(255,255,255,.05);
                   border:1px solid rgba(255,255,255,.1);border-radius:20px;
                   padding:0;cursor:pointer;font-family:inherit;
                   transition:transform .15s,background .2s,box-shadow .2s;
                   overflow:hidden;"
            onmouseenter="this.style.background='rgba(255,255,255,.09)';this.style.boxShadow='0 0 0 1px {{ $accent['border'] }}40,0 8px 32px {{ $accent['glow'] }}'"
            onmouseleave="this.style.background='rgba(255,255,255,.05)';this.style.boxShadow='none'"
            ontouchstart="this.style.transform='scale(.98)'"
            ontouchend="this.style.transform='scale(1)'"
            aria-label="Iniciar: {{ $recipe['title'] }}">

            <div style="display:flex;align-items:stretch;">

                {{-- Imagem ou emoji --}}
                <div style="width:110px;min-height:110px;flex-shrink:0;
                            background:rgba(255,255,255,.05);
                            display:flex;align-items:center;justify-content:center;
                            border-right:1px solid rgba(255,255,255,.07);
                            position:relative;overflow:hidden;">
                    @if($hasImage)
                        <img src="{{ asset($recipe['image']) }}"
                             alt="{{ $recipe['title'] }}"
                             style="width:100%;height:100%;object-fit:cover;">
                    @else
                        <span style="font-size:48px;line-height:1;">{{ $recipe['emoji'] ?? '🍽️' }}</span>
                    @endif
                    {{-- barra colorida lateral --}}
                    <div style="position:absolute;left:0;top:0;bottom:0;width:3px;
                                background:{{ $accent['border'] }};"></div>
                </div>

                {{-- Info --}}
                <div style="flex:1;padding:14px 16px;display:flex;flex-direction:column;justify-content:space-between;gap:8px;">
                    <div>
                        <div style="color:#fff;font-size:15px;font-weight:700;
                                    line-height:1.25;margin-bottom:5px;letter-spacing:-.2px;">
                            {{ $recipe['title'] }}
                        </div>
                        <div style="color:rgba(255,255,255,.48);font-size:12px;line-height:1.4;">
                            {{ $recipe['description'] }}
                        </div>
                    </div>

                    {{-- Badges --}}
                    <div style="display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
                        <span style="background:{{ $accent['badge'] }};color:{{ $accent['badgeText'] }};
                                     border-radius:20px;padding:3px 9px;font-size:11px;font-weight:600;">
                            {{ ucfirst($recipe['difficulty'] ?? 'fácil') }}
                        </span>
                        @if(!empty($recipe['time']))
                        <span style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);
                                     border-radius:20px;padding:3px 9px;font-size:11px;">
                            ⏱ {{ $recipe['time'] }}
                        </span>
                        @endif
                        <span style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);
                                     border-radius:20px;padding:3px 9px;font-size:11px;">
                            {{ $stepCount }} {{ $stepCount === 1 ? 'etapa' : 'etapas' }}
                        </span>
                        @if(!empty($recipe['cold']) && $recipe['cold'])
                        <span style="background:rgba(100,180,255,.1);color:rgba(150,210,255,.7);
                                     border-radius:20px;padding:3px 9px;font-size:11px;">
                            ❄️ sem fogo
                        </span>
                        @endif
                    </div>
                </div>

                {{-- Seta --}}
                <div style="display:flex;align-items:center;padding-right:16px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="rgba(255,255,255,.3)" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </div>

            {{-- Loading overlay (aparece ao clicar) --}}
            <div class="recipe-loading" data-id="{{ $recipeId }}"
                style="display:none;position:absolute;inset:0;background:rgba(0,0,0,.6);
                       align-items:center;justify-content:center;">
                <div style="width:22px;height:22px;border-radius:50%;
                            border:2px solid rgba(255,255,255,.2);
                            border-top-color:#fff;animation:spin .7s linear infinite;"></div>
            </div>
        </button>
        @endforeach

    </div>

    {{-- ── BOTÃO FALAR COM A POLLY ─────────────────────────────────────────── --}}
    <div style="max-width:480px;margin:0 auto;padding:0 16px 24px;">
        <a href="{{ route('poc.voice') }}"
            style="display:flex;align-items:center;gap:14px;
                   background:rgba(107,155,74,.12);border:1px solid rgba(107,155,74,.3);
                   border-radius:18px;padding:16px 20px;text-decoration:none;
                   transition:background .2s;"
            onmouseenter="this.style.background='rgba(107,155,74,.2)'"
            onmouseleave="this.style.background='rgba(107,155,74,.12)'">
            <div style="width:44px;height:44px;border-radius:50%;flex-shrink:0;
                        background:rgba(107,155,74,.25);
                        display:flex;align-items:center;justify-content:center;font-size:20px;">
                📞
            </div>
            <div>
                <div style="color:#a8e070;font-size:14px;font-weight:600;margin-bottom:2px;">
                    Falar com a Polly
                </div>
                <div style="color:rgba(255,255,255,.4);font-size:12px;">
                    Tire dúvidas sobre culinária por voz
                </div>
            </div>
            <div style="margin-left:auto;color:rgba(255,255,255,.2);font-size:18px;">›</div>
        </a>
    </div>

    {{-- ── FOOTER ──────────────────────────────────────────────────────────── --}}
    <div style="text-align:center;padding:0 24px 40px;">
        @if($isMockMode)
            <span style="color:rgba(255,200,50,.5);font-size:11px;">● Modo simulado ativo</span>
        @else
            <span style="color:rgba(106,170,48,.6);font-size:11px;">● IA conectada</span>
        @endif
        @if($isDebugMode)
            <span style="color:rgba(90,140,224,.5);font-size:11px;margin-left:12px;">● Debug ativo</span>
        @endif
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
// ─── Saudação via Polly ───────────────────────────────────────────────────────
(async function greet() {
    const messages = [
        'Lendo as receitas...',
        'Picando os ingredientes...',
        'Organizando a cozinha...',
        'Aquecendo os fornos...',
        'Separando os temperos...',
        'Lavando as mãos...',
    ];
    const msgEl   = document.getElementById('loading-msg-text');
    const overlay = document.getElementById('loading-overlay');

    let idx = 0;
    msgEl.textContent = messages[0];
    const interval = setInterval(() => {
        idx = (idx + 1) % messages.length;
        msgEl.style.opacity = '0';
        setTimeout(() => {
            msgEl.textContent  = messages[idx];
            msgEl.style.opacity = '1';
        }, 300);
    }, 1800);

    function hideOverlay() {
        clearInterval(interval);
        overlay.style.opacity = '0';
        setTimeout(() => overlay.style.display = 'none', 500);
    }

    const text = 'Olá, eu sou a Polly, cozinheira formada pelo UniSenac e sua parceira na cozinha. O que vamos preparar hoje?';
    try {
        const res = await fetch(window.__CGS__.routes.tts, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.__CGS__.csrfToken,
            },
            body: JSON.stringify({ text }),
        });
        if (!res.ok) { hideOverlay(); return; }
        const blob  = await res.blob();
        const url   = URL.createObjectURL(blob);
        const audio = new Audio(url);
        hideOverlay();
        audio.play().catch(() => {});
        audio.onended = () => URL.revokeObjectURL(url);
    } catch (e) {
        console.warn('TTS saudação falhou:', e);
        hideOverlay();
    }
})();

const CGSelection = {
    loading: false,

    async start(recipeId) {
        if (this.loading) return;
        this.loading = true;

        // Mostra spinner no card clicado
        const overlay = document.querySelector(`.recipe-loading[data-id="${recipeId}"]`);
        if (overlay) overlay.style.display = 'flex';

        try {
            const r = await fetch(window.__CGS__.routes.start, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.__CGS__.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ recipe_id: recipeId }),
            });
            const data = await r.json();
            if (data.success) {
                window.location.href = '{{ route("recipe.index") }}';
            } else {
                alert(data.message || 'Erro ao iniciar a receita.');
                if (overlay) overlay.style.display = 'none';
                this.loading = false;
            }
        } catch (e) {
            alert('Erro de conexão. Tente novamente.');
            if (overlay) overlay.style.display = 'none';
            this.loading = false;
        }
    }
};
</script>
@endsection

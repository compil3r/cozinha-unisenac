@extends('layouts.camera')
@section('title', 'Escolha uma receita — Cozinha Guiada')

@section('content')
<script>
window.__CGS__ = {
    csrfToken: "{{ csrf_token() }}",
    routes: { start: "{{ route('session.start') }}" }
};
</script>

<div style="position:fixed;inset:0;overflow-y:auto;
            background:linear-gradient(170deg,#0f1c0a 0%,#1a1a16 60%,#0a0f08 100%);">

    {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
    <div style="text-align:center;padding:52px 24px 32px;">
        <div style="font-size:40px;margin-bottom:12px;">🍴</div>
        <h1 style="color:#fff;font-size:24px;font-weight:700;letter-spacing:-.5px;margin:0 0 6px;">
            Cozinha Guiada
        </h1>
        <p style="color:rgba(255,255,255,.45);font-size:14px;margin:0;">
            Escolha uma receita para começar
        </p>
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
                window.location.href = '/';
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

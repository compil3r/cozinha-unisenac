@extends('layouts.camera')
@section('title', 'Receita concluída — Cozinha Guiada')

@section('content')
<script>
window.__CG__ = {
    csrfToken: "{{ csrf_token() }}",
    routes: { restart: "{{ route('session.restart') }}" }
};
</script>

<div style="position:fixed;inset:0;background:linear-gradient(160deg,#0d1a0a 0%,#1a1a18 100%);
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:32px 24px;text-align:center;overflow:auto;">

    {{-- Ícone --}}
    <div style="font-size:56px;margin-bottom:24px;">🎉</div>

    <h2 style="color:#fff;font-size:24px;font-weight:600;margin-bottom:8px;letter-spacing:-.3px;">
        Receita concluída!
    </h2>
    <p style="color:rgba(255,255,255,.55);font-size:14px;line-height:1.5;max-width:280px;margin-bottom:40px;">
        Você completou o <strong style="color:rgba(255,255,255,.8);">{{ $state['recipe']['title'] }}</strong>. Bom apetite!
    </p>

    {{-- Resumo das etapas --}}
    <div style="width:100%;max-width:340px;margin-bottom:40px;text-align:left;">
        <div style="color:rgba(255,255,255,.3);font-size:11px;font-weight:600;text-transform:uppercase;
                    letter-spacing:.06em;margin-bottom:12px;">Etapas concluídas</div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($state['recipe']['steps'] as $step)
            <div style="display:flex;align-items:center;gap:12px;
                        background:rgba(255,255,255,.06);border-radius:12px;padding:10px 14px;">
                <div style="width:22px;height:22px;border-radius:50%;background:#547a38;flex-shrink:0;
                            display:flex;align-items:center;justify-content:center;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                         stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <span style="color:rgba(255,255,255,.8);font-size:13px;">{{ $step['title'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Ações --}}
    <div style="display:flex;flex-direction:column;gap:10px;width:100%;max-width:280px;">
        <button onclick="CozinhaGuiada.restart()"
            style="background:#fff;color:#1a1a18;border:none;border-radius:100px;
                   padding:15px 32px;font-size:15px;font-weight:600;font-family:inherit;
                   cursor:pointer;box-shadow:0 6px 24px rgba(0,0,0,.4);">
            ↺ Recomeçar esta receita
        </button>
        <button onclick="CozinhaGuiada.goToSelection()"
            style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);
                   border:1px solid rgba(255,255,255,.15);border-radius:100px;
                   padding:14px 32px;font-size:14px;font-family:inherit;cursor:pointer;">
            🍴 Escolher outra receita
        </button>
    </div>

    @if($isMockMode)
    <p style="color:rgba(255,255,255,.25);font-size:11px;margin-top:32px;">
        Sessão concluída em modo simulado.
    </p>
    @endif
</div>

<script>
async function postJson(url, data) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.__CG__.csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify(data),
    });
    return r.json();
}
window.CozinhaGuiada = {
    restart: async () => {
        if (!confirm('Recomeçar esta receita do início?')) return;
        try { await postJson(window.__CG__.routes.restart, {}); } catch (_) {}
        window.location.href = '/';
    },
    goToSelection: async () => {
        try { await postJson(window.__CG__.routes.restart, {}); } catch (_) {}
        window.location.href = '/';
    },
};
</script>
@endsection

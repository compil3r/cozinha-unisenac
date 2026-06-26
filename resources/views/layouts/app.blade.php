<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Cozinha Guiada — Experiência educacional de visão computacional aplicada à Gastronomia.">

    <title>@yield('title', 'Cozinha Guiada')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased" style="background-color: #faf9f0; color: #292524; font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif;">

    <!-- Cabeçalho -->
    <header class="bg-white border-b border-stone-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">

            <!-- Logo e subtítulo -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex-shrink-0">
                    <span class="text-2xl" aria-hidden="true">🍴</span>
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-stone-800 leading-tight tracking-tight">
                        Cozinha Guiada
                    </h1>
                    <p class="text-xs text-stone-500 hidden sm:block leading-tight">
                        Uma receita acompanhada por visão computacional
                    </p>
                </div>
            </div>

            <!-- Status do ambiente + botão reiniciar -->
            <div class="flex items-center gap-3 flex-shrink-0">
                @if(config('vision.provider') === 'mock')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border" style="background:#fffbeb;color:#b45309;border-color:#fde68a;">
                        <span class="w-1.5 h-1.5 rounded-full inline-block" style="background:#f59e0b;"></span>
                        Modo simulado
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border" style="background:#f3f7f0;color:#3f5c2a;border-color:#c6d9b2;">
                        <span class="w-1.5 h-1.5 rounded-full inline-block animate-pulse" style="background:#547a38;"></span>
                        IA conectada
                    </span>
                @endif

                <button
                    id="btn-restart"
                    onclick="CozinhaGuiada.restart()"
                    class="text-sm text-stone-500 hover:text-stone-700 border border-stone-200 hover:border-stone-300 rounded-lg px-3 py-1.5 transition-colors bg-white"
                    title="Reiniciar receita"
                >
                    ↺ Reiniciar
                </button>
            </div>
        </div>
    </header>

    <!-- Conteúdo principal -->
    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        @yield('content')
    </main>

    <!-- Rodapé de privacidade -->
    <footer class="mt-auto border-t border-stone-200 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 text-center space-y-1">
            <p class="text-xs text-stone-400">
                A foto é usada apenas para verificar visualmente a etapa atual. Ela não é armazenada por padrão.
            </p>
            <p class="text-xs text-stone-400">
                A Cozinha Guiada oferece apoio visual e educativo. Não avalia sabor, aroma, textura, higiene, segurança alimentar ou qualidade técnica completa da receita.
            </p>
        </div>
    </footer>

</body>
</html>

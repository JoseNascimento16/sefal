<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Marca o tema ANTES da primeira pintura, para não haver lampejo claro
             em quem usa o tema escuro. Sem cookie vale o padrão do sistema —
             CLARO —, o mesmo do `HandleAppearance` e do `use-appearance.tsx`: os
             três precisam concordar, senão a primeira pintura sai de um tema e a
             segunda de outro. Marca os DOIS marcadores a partir do mesmo
             booleano — a classe `.dark` (que as utilidades do Tailwind leem) e o
             atributo `data-theme` (que os tokens do Design System também aceitam)
             —, exatamente como faz o `applyTheme` depois que o JavaScript assume.
             Um decidindo, dois marcadores: eles não podem discordar em momento
             nenhum, nem neste instante antes da hidratação. --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';
                const escuro = appearance === 'dark'
                    || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', escuro);
                document.documentElement.dataset.theme = escuro ? 'dark' : 'light';
            })();
        </script>

        {{-- Cor de fundo da PRIMEIRA pintura, antes de o CSS carregar: são os
             mesmos valores de `--sm-app` (claro e escuro) do Design System. Sem
             isto, quem usa o tema escuro vê um lampejo branco a cada visita. --}}
        <style>
            html {
                background-color: #f2f5fa;
            }

            html.dark {
                background-color: #0a1628;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

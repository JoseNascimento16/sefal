<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        {{-- Aplicativo de rua: a tela não dá zoom por engano no meio de um toque,
             e a área segura do aparelho (entalhe, barra de gestos) é respeitada. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#14477e" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0a1628" media="(prefers-color-scheme: dark)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="SEFAL">
        <meta name="robots" content="noindex, nofollow">

        <title>SEFAL · Aplicativo do Fiscal</title>

        {{-- O tema é escolhido antes da primeira pintura: sem isto, quem trabalha
             à noite leva um clarão branco a cada abertura. --}}
        <script>
            (function () {
                // Padrão CLARO (quem usa está na rua, sob o sol); só a escolha explícita
                // do fiscal, guardada no aparelho, pinta escuro já na primeira tela — sem
                // isto haveria um lampejo branco a cada abertura para quem escolheu escuro.
                let salvo = null;
                try { salvo = window.localStorage.getItem('sefal-tema'); } catch (e) {}
                document.documentElement.dataset.tema = salvo === 'escuro' ? 'escuro' : 'claro';
            })();
        </script>

        <style>
            html { background-color: #eef1f7; }
            html[data-tema='escuro'] { background-color: #0a1628; }
        </style>

        <link rel="manifest" href="/manifest.json">
        <link rel="icon" href="/pwa-icone.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/pwa.css', 'resources/js/pwa/main.tsx'])
    </head>
    <body>
        <div id="pwa"></div>
    </body>
</html>

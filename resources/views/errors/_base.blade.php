{{--
    Página de erro amigável, AUTOSSUFICIENTE: HTML e CSS embutidos, sem banco, sem
    Vite, sem arquivo externo — ela precisa abrir justamente quando algo não está
    de pé (banco fora do ar, build ausente, manutenção). Nunca mostra rastro de
    pilha: isso só aparece com APP_DEBUG=true, que não existe em homologação nem
    em produção.

    A identidade é a mesma do sistema (petróleo + âmbar), com as cores escritas
    aqui na mão de propósito: nenhum token de CSS externo pode ser exigido para
    esta tela desenhar.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Erro' }} — Fiscalização de Permissionários</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font-family: 'Segoe UI', system-ui, -apple-system, Roboto, Arial, sans-serif;
            color: #15292b; background: linear-gradient(140deg, #0a474d 0%, #0d5c63 55%, #124e52 100%);
        }
        .cartao {
            width: 100%; max-width: 540px; background: #fff; border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .32); padding: 40px 34px; text-align: center;
            border-bottom: 4px solid #f4a300;
        }
        .marca {
            display: inline-flex; align-items: center; justify-content: center;
            width: 74px; height: 74px; border-radius: 18px; margin: 0 auto 18px;
            background: #e7f1f1; color: #0d5c63; font-size: 34px; line-height: 1;
        }
        .codigo { font-size: 12.5px; font-weight: 700; letter-spacing: 1px; color: #6b8082; text-transform: uppercase; }
        h1 { font-size: 23px; font-weight: 800; margin: 6px 0 12px; }
        p { font-size: 15px; line-height: 1.6; color: #35494b; }
        .acoes { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 26px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: 10px;
            font-size: 14px; font-weight: 700; text-decoration: none; border: 1px solid transparent;
        }
        .btn.principal { background: #0d5c63; color: #fff; box-shadow: inset 0 -2px 0 #f4a300; }
        .btn.secundario { background: #fff; color: #0d5c63; border-color: #c3cfcf; }
        .rodape { margin-top: 28px; font-size: 12px; color: #6b8082; }
        .referencia { margin-top: 22px; font-size: 12px; color: #6b8082; }
        .referencia strong { font-family: monospace; color: #35494b; letter-spacing: .5px; }
    </style>
</head>
<body>
    <main class="cartao">
        <div class="marca">{{ $emoji ?? '!' }}</div>
        <div class="codigo">Erro {{ $codigo ?? '' }}</div>
        <h1>{{ $titulo ?? 'Algo não saiu como esperado' }}</h1>
        <p>{!! $mensagem ?? 'Tente novamente em alguns instantes.' !!}</p>

        <div class="acoes">
            <a class="btn principal" href="/retaguarda/inicio">Ir para o início</a>
            <a class="btn secundario" href="/login">Entrar no sistema</a>
        </div>

        @if(! empty($referencia))
            <div class="referencia">
                Código deste erro: <strong>{{ $referencia }}</strong>
                <div style="margin-top:4px;">Informe este código ao suporte para agilizar o atendimento.</div>
            </div>
        @endif

        <div class="rodape">SEMOP · Fiscalização de Permissionários — Prefeitura de Salvador</div>
    </main>
</body>
</html>

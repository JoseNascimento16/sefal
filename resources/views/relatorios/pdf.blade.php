{{--
    Template PDF de relatório (dompdf).

    Recebe $r (ResultadoRelatorio->toArray()) e $graficos (HTML/SVG já gerado por
    GraficoSvg). Não conhece relatório nenhum por dentro: desenha cabeçalho,
    recorte, seções, gráficos e rodapé a partir do resultado neutro.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 32px; }

        /* 'DejaVu Sans' (embutida no dompdf) e NÃO o alias `sans-serif`: o alias cai na core font
           Helvetica, limitada à tabela Windows-1252, e aí seta (→), separador de breadcrumb (›) e
           travessão (—) saem como "?" no arquivo entregue. A DejaVu é Unicode e cobre esses glifos. */
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { margin: 0; color: #15292b; font-size: 11px; line-height: 1.4; }

        /* Paisagem com muitas colunas: células mais compactas para a tabela não estourar. */
        body.landscape table.dados th { padding: 4px 2px; font-size: 8px; }
        body.landscape table.dados td { padding: 3px 2px; font-size: 8px; }
        /* Número e valor (à direita) nunca quebram no meio; o CABEÇALHO pode quebrar. */
        body.landscape table.dados td.ar { white-space: nowrap; }

        .topo { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .topo td { border: none; vertical-align: middle; }
        .marca-titulo { font-size: 22px; font-weight: bold; color: #0d5c63; letter-spacing: -0.5px; line-height: 1; }
        .marca-sub { font-size: 8.5px; color: #35494b; font-weight: bold; }
        .rel-titulo { font-size: 15px; font-weight: bold; color: #0a474d; text-align: right; }
        .rel-emitido { font-size: 9px; color: #6b8082; text-align: right; margin-top: 3px; }
        .hr { border: none; border-top: 1px solid #dfe5e5; margin: 8px 0 14px; }

        .recorte-label { font-size: 10px; font-weight: bold; color: #6b8082; letter-spacing: 0.5px; margin-bottom: 6px; }
        .recorte-card { border: 0.5px solid #dfe5e5; border-left: 3px solid #f4a300; border-radius: 8px; padding: 8px 12px; margin-bottom: 18px; }
        .recorte-card .val { font-size: 11px; font-weight: bold; color: #15292b; }

        .secao-titulo { font-size: 12.5px; font-weight: bold; color: #15292b; border-left: 3px solid #0d5c63; padding: 3px 0 3px 8px; margin: 18px 0 8px; }

        table.dados { width: 100%; border-collapse: collapse; }
        table.dados th { background: #0d5c63; color: #ffffff; font-size: 9px; font-weight: bold; padding: 7px 10px; border: 0.5px solid #0a474d; text-transform: uppercase; }
        table.dados td { padding: 6px 10px; font-size: 10px; color: #15292b; border-left: 0.5px solid #dfe5e5; border-right: 0.5px solid #dfe5e5; border-bottom: 0.5px solid #eef1f1; }
        table.dados tr.total td { background: #f7f8f8; font-weight: bold; border-top: 0.5px solid #dfe5e5; border-bottom: 0.5px solid #dfe5e5; }
        table.dados tr.total-cheia td { background: #e7efef; color: #0a474d; font-weight: bold; font-size: 11px; padding: 8px 10px; border: 0.5px solid #c3cfcf; }

        .ac { text-align: center; }
        .ar { text-align: right; }

        .rodape { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8px; color: #6b8082; border-top: 1px solid #dfe5e5; padding-top: 5px; }
    </style>
</head>
<body class="{{ ($r['metadados']['orientacao'] ?? '') === 'landscape' ? 'landscape' : '' }}">
    @php
        $emitido = 'EMITIDO EM: '.($r['metadados']['gerado_em'] ?? '')
            .(! empty($r['metadados']['emitido_por']) ? '  ·  POR: '.mb_strtoupper($r['metadados']['emitido_por']) : '');
    @endphp

    <table class="topo">
        <tr>
            <td style="width: 42%;">
                <div class="marca-titulo">SEFAL</div>
                <div class="marca-sub">Sistema de Fiscalização de Ambulantes<br>SEMOP · Prefeitura de Salvador</div>
            </td>
            <td style="width: 58%;">
                <div class="rel-titulo">{{ mb_strtoupper($r['metadados']['titulo'] ?? 'Relatório') }}</div>
                <div class="rel-emitido">{{ $emitido }}</div>
            </td>
        </tr>
    </table>
    <hr class="hr">

    {{-- O recorte por escrito: sem ele, semanas depois ninguém sabe o que estas linhas representavam. --}}
    @if (! empty($r['metadados']['filtros_resumo']))
        <div class="recorte-label">RECORTE DA CONSULTA</div>
        <div class="recorte-card">
            <div class="val">{{ $r['metadados']['filtros_resumo'] }}</div>
        </div>
    @endif

    @foreach ($r['secoes'] as $secao)
        @if (! empty($secao['titulo']))
            <div class="secao-titulo">{{ mb_strtoupper($secao['titulo']) }}</div>
        @endif

        <table class="dados">
            <thead>
                <tr>
                    @foreach ($secao['colunas'] as $c)
                        <th class="{{ $c['alinhar'] === 'right' ? 'ar' : ($c['alinhar'] === 'center' ? 'ac' : '') }}">{{ $c['titulo'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($secao['linhas'] as $linha)
                    <tr>
                        @foreach ($secao['colunas'] as $c)
                            <td class="{{ $c['alinhar'] === 'right' ? 'ar' : ($c['alinhar'] === 'center' ? 'ac' : '') }}">{{ $linha[$c['chave']] ?? '' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ max(count($secao['colunas']), 1) }}" class="ac">Nenhum registro no recorte informado.</td></tr>
                @endforelse

                @foreach ($secao['totais'] as $t)
                    @if (empty($t['celulas']) && ($t['valor'] ?? '') === '')
                        <tr class="total-cheia"><td colspan="{{ max(count($secao['colunas']), 1) }}">{{ mb_strtoupper($t['rotulo']) }}</td></tr>
                    @else
                        <tr class="total">
                            <td>{{ mb_strtoupper($t['rotulo']) }}</td>
                            @foreach (array_slice($secao['colunas'], 1) as $idx => $c)
                                @php $celula = $t['celulas'][$c['chave']] ?? ($idx === 0 ? $t['valor'] : ''); @endphp
                                <td class="{{ $c['alinhar'] === 'right' ? 'ar' : ($c['alinhar'] === 'center' ? 'ac' : '') }}">{{ $celula }}</td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endforeach

    @foreach ($graficos as $svg)
        {!! $svg !!}
    @endforeach

    <div class="rodape">SEFAL · SEMOP — Prefeitura de Salvador</div>
</body>
</html>

<?php

/*
|--------------------------------------------------------------------------
| `dataBR` do front — a data com HORA não pode sair corrompida
|--------------------------------------------------------------------------
|
| Lei do projeto: data sempre em `dd/mm/aaaa`. O helper do front que a escreve é
| `resources/js/lib/datas.ts`, e ele recebe as duas formas em que a data com hora
| chega do servidor — `2026-08-25T14:30` e `2026-08-25 14:30`.
|
| A forma com ESPAÇO passava pelo corte no `T`, o `split('-')` devolvia
| dia = "25 14:30" e a função escrevia `25 14:30/08/2026`. Data corrompida, sem
| erro em lugar nenhum: só apareceu ao medir o DOM depois de uma coluna de grade
| trocar `dataHoraBR` (que já normalizava) por `dataBR`.
|
| ⚠️ Este teste lê o FONTE, o que é o último recurso deste projeto — e está aqui
| pela razão que o autoriza: o gate não executa JavaScript, e não há runner de
| teste de front instalado (ver PENDÊNCIAS). Ele confere a normalização, não a
| forma de escrever a função: qualquer implementação que trate o espaço passa.
|
*/

it('normaliza o espaço antes de cortar a hora, nas duas funções de data do front', function () {
    $fonte = (string) file_get_contents(base_path('resources/js/lib/datas.ts'));

    // Uma ocorrência por função (`dataBR` e `dataHoraBR`): as duas recebem a
    // data com hora, e as duas têm de aceitar o espaço.
    expect(substr_count($fonte, "replace(' ', 'T')"))->toBeGreaterThanOrEqual(2);
});

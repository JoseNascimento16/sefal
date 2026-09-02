<?php

/*
|--------------------------------------------------------------------------
| PROTÓTIPO — o vocabulário dos DOCUMENTOS DE CAMPO (o que está no papel)
|--------------------------------------------------------------------------
|
| Nada aqui é invenção nossa: cada lista foi transcrita dos blocos de papel que
| o SEFAL usa em rua — a **Notificação Preliminar** e o **Auto de Apreensão** —,
| mais o "Manual para o NTI" que o cliente entregou junto. Quando o módulo de
| fiscalização sair do protótipo, os textos continuam valendo: é o formulário
| legal, não um rascunho de tela.
|
| ── Por que este arquivo existe SEPARADO das denúncias ─────────────────────────
|
| Porque o documento não pertence à denúncia: ele nasce na VISTORIA, e a mesma
| Notificação sai de uma denúncia dirigida, de uma operação ou de uma
| fiscalização avulsa. Escrever a redação das 20 caixas dentro do arquivo de
| denúncias faria a próxima tela que precisasse delas copiá-las — e um dia só uma
| das cópias ganharia a caixa nova do bloco novo.
|
| ⚠️ ATENÇÃO — DUAS CÓPIAS DESTE VOCABULÁRIO EXISTEM HOJE, e isso é dívida
| conhecida: o protótipo do aplicativo do fiscal (branch `feature/pwa-prototipo`,
| `resources/js/pwa/dados-documentos.ts`) tem as MESMAS listas, porque lá o
| fiscal preenche o documento sem servidor no meio. Quando os dois protótipos se
| encontrarem, **uma** delas tem de morrer, e a que fica é esta: o servidor serve
| os dois lados, e a redação de um formulário legal não pode divergir entre a
| Retaguarda e o aplicativo. Registrado como pendência no doc de regra.
|
| ── Fidelidade ao papel ──────────────────────────────────────────────────────
|
| O bloco de motivos da Notificação tem 20 caixas, das quais a última é "Outros"
| (campo livre) e a primeira pede complemento ("no Setor ____"). Não
| arredondamos para um número redondo nem reescrevemos a redação do impresso — se
| o texto soa estranho, é porque está assim no papel.
|
| As chaves (`puxada`, `mesas`, `autuacao`…) existem para quem REFERENCIA o
| motivo: a denúncia semeada declara `['puxada', 'padrao']` e quem monta o
| documento expande para o texto. Guardar o texto na denúncia daria dois donos à
| redação, e a caixa marcada num documento antigo deixaria de acompanhar a
| correção de uma vírgula do impresso.
|
*/

return [

    /*
     * NOTIFICAÇÃO PRELIMINAR — a coluna de motivos do impresso, na ordem do
     * papel. `complemento` marca a caixa que deixa uma linha para completar à
     * mão. A 20ª caixa ("Outros") não está aqui porque é campo LIVRE, e não uma
     * opção de lista: quem lavra escreve o texto, e ele viaja no documento.
     */
    'motivos_np' => [
        'comparecer' => ['texto' => 'Comparecer a SEMOP no Setor', 'complemento' => 'Qual setor'],
        'preco-publico' => ['texto' => 'Falta de pagamento do Preço Público'],
        'dam' => ['texto' => 'Apresentar DAM quitado do ano em exercício'],
        'horario' => ['texto' => 'Descumprimento do horário de funcionamento'],
        'animal' => ['texto' => 'Manter animal no equipamento'],
        'higiene' => ['texto' => 'Não zelar pela conservação, asseio e higiene'],
        'padrao' => ['texto' => 'Alterar o padrão do equipamento'],
        'mesas' => ['texto' => 'Retirar mesas e cadeiras do logradouro público'],
        'equipamento' => ['texto' => 'Retirar equipamentos do logradouro público'],
        'alvara-mesas' => [
            'texto' => 'Apresentar Alvará para colocação de mesas e cadeiras no logradouro público',
        ],
        'ceder' => ['texto' => 'Ceder / locar o equipamento a terceiro'],
        'ramo' => ['texto' => 'Alterar ou mudar o ramo de atividade'],
        'produtos-fora' => ['texto' => 'Manter produtos e objetos fora do equipamento'],
        'cota' => ['texto' => 'Falta de pagamento da cota de despesa (água e luz)'],
        'bebida' => ['texto' => 'Desativar a venda de bebida alcoólica'],
        'puxada' => ['texto' => 'Retirar puxada, sanitário e depósito'],
        'desativar' => ['texto' => 'Desativar suas atividades'],
        'realocar' => ['texto' => 'Realocar equipamento'],
        'carcaca' => ['texto' => 'Retirar carcaça de veículo da via pública'],
    ],

    /** As penalidades da faixa de baixo do impresso — exatamente as cinco. */
    'sancoes_np' => [
        'autuacao' => 'Autuação',
        'apreensao' => 'Apreensão',
        'perdas' => 'Perdas de bens e mercadorias',
        'embargo' => 'Embargo administrativo',
        'multa' => 'Pagamento de multa',
    ],

    /*
     * Os prazos que o manual do cliente lista, na ordem em que ele os escreve.
     * `dias` é o que faz a data de vencimento ser CALCULADA a partir da hora da
     * lavratura — data de vencimento escrita à mão no dado de protótipo
     * envelhece, e uma semana depois da demonstração toda notificação apareceria
     * vencida.
     */
    'prazos_np' => [
        'imediato' => ['rotulo' => 'Imediato', 'dias' => 0, 'nota' => 'Para quem não possui cadastro'],
        '24h' => ['rotulo' => '24 horas', 'dias' => 1],
        '48h' => ['rotulo' => '48 horas', 'dias' => 2],
        '72h' => ['rotulo' => '72 horas', 'dias' => 3],
        '5d' => ['rotulo' => '05 dias', 'dias' => 5],
        '10d' => ['rotulo' => '10 dias', 'dias' => 10],
    ],

    /*
     * AUTO DE APREENSÃO — a fundamentação legal impressa no formulário. O papel
     * tem quatro linhas (lei, decreto, artigos, portaria) e o manual lista a lei
     * e os cinco decretos.
     */
    'fundamentacao' => [
        'lei' => 'Lei Nº 5.503/1999',
        'decretos' => [
            'Decreto Nº 11.754/1997',
            'Decreto Nº 12.016/1998',
            'Decreto Nº 24.422/2013',
            'Decreto Nº 26.804/2015',
            'Decreto Nº 26.849/2015',
        ],
        'artigos_padrao' => 'Art. 21, Art. 24 e Art. 31',
        'portaria_padrao' => 'Portaria SEMOP Nº 014/2026',
    ],

    /*
     * O destino dos bens está impresso no formulário, endereço inclusive. Não é
     * destruição: é APREENSÃO com GUARDA — os bens ficam no SEGUB por um prazo, e
     * só depois dele se decide o destino.
     */
    'segub' => [
        'nome' => 'Setor de Guarda de Bens — SEGUB',
        'endereco' => 'Av. San Martins, s/n',
    ],

    'prazos_guarda' => [
        '30' => ['rotulo' => '30 dias', 'extenso' => 'trinta dias'],
        '60' => ['rotulo' => '60 dias', 'extenso' => 'sessenta dias'],
        '90' => ['rotulo' => '90 dias', 'extenso' => 'noventa dias'],
    ],

    /** "quando serão ______ de acordo com a Legislação Municipal." */
    'destinacoes_guarda' => [
        'devolucao' => 'devolvidos ao proprietário, mediante regularização e quitação',
        'doacao' => 'doados a instituições de assistência social',
        'leilao' => 'levados a leilão público',
        'destruicao' => 'destruídos, por se tratar de material perecível',
    ],

    /*
     * O rodapé impresso nos dois blocos — o endereço do setor. Fica aqui porque é
     * do PAPEL, e não da tela que o mostra.
     */
    'rodape' => 'Rua 28 de Setembro, nº 26 — Baixa dos Sapateiros — CEP 40020-240 — Salvador/BA',

    /*
     * O título em caixa alta de cada bloco, como ele está impresso no alto da
     * folha. É o que a leitura na Retaguarda repete — o documento se apresenta
     * pelo nome que tem no papel, não por uma sigla nossa.
     */
    'titulos' => [
        'np' => 'NOTIFICAÇÃO PRELIMINAR',
        'aa' => 'AUTO DE APREENSÃO',
    ],

];

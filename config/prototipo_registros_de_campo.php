<?php

/*
|--------------------------------------------------------------------------
| PROTÓTIPO — Registros de fiscalização AVULSOS (sem denúncia)
|--------------------------------------------------------------------------
|
| ⚠️ DADO DE PROTÓTIPO. Nada aqui é gravado: a tela **Fiscalizações** lê daqui —
| nas duas abas, "A decidir" e "Acervo" — e guarda o que o Chefe de Setor decide
| na SESSÃO do navegador (ver `App\Support\Prototipo\FiscalizacoesFicticias`).
|
| ── Por que este arquivo tem SÓ as avulsas ──────────────────────────────────
|
| A fila do Chefe de Setor é "todo registro de fiscalização concluído da minha
| área", e esses registros nascem de dois lugares:
|
|   1. de uma DENÚNCIA dirigida — e essa vistoria já está descrita, passo a
|      passo, no trâmite da própria denúncia (`config/prototipo_denuncias.php`).
|      Copiá-la para cá daria dois donos à MESMA vistoria: um dia o trâmite
|      diria "regularizado no local" e a fila continuaria dizendo "notificado".
|      Então a fila DERIVA esses registros do trâmite, na leitura;
|   2. de uma OPERAÇÃO planejada ou da ronda da equipe, sem denúncia nenhuma
|      atrás. Essas não existem em lugar nenhum ainda — e são estas que moram
|      aqui.
|
| A distinção não é técnica: é o que o Chefe de Setor precisa ver. Metade do
| trabalho da equipe não vem de reclamação de cidadão, e uma fila que só
| mostrasse o que veio de denúncia desenharia um setor que só reage.
|
| ── As datas são RELATIVAS de propósito ─────────────────────────────────────
|
| `concluida_ha_horas` vira data e hora na hora de servir a tela. Data fixa
| envelhece: uma semana depois da demonstração a fila apareceria inteira como
| trabalho antigo, e o dono leria isso como comportamento do sistema.
|
| ── O que cada registro declara ─────────────────────────────────────────────
|
| `equipe` liga o registro à ÁREA e ao FISCAL: a área e o nome de quem assinou
| saem de `config/prototipo_estrutura.php` na hora de servir, pelo mesmo motivo
| que valem no trâmite das denúncias — nome de gente escrito aqui daria dois
| donos ao mesmo cadastro, e um fiscal removido da equipe continuaria assinando
| vistoria. `fiscal` diz QUAL da equipe (1, 2, 3…).
|
| `consideracoes` e `recomendacoes` são o CONTRATO com o aplicativo do fiscal —
| os mesmos nomes que o passo do trâmite usa. As recomendações são as CHAVES do
| catálogo `prototipo_denuncias.recomendacoes_do_fiscal` (`retorno`, `sgci`,
| `passagem`…), que é a lista fechada que o aplicativo oferece — e chave, não a
| frase, porque é ela que o aplicativo grava e é por ela que o relatório soma. A
| frase que a tela mostra sai da mesma chave, na redação `explicito`; escrita à
| mão aqui, seria recomendação que a Retaguarda não sabe ler nem contar.
|
| `alvo`, `equipamento` e `fotos` são o que a equipe registrou NO PONTO, e é o que
| a aba **Acervo** usa para provar o que foi feito. `alvo` NULO é caso previsto, e
| não dado faltando: "nada encontrado no local" é desfecho legítimo, e a foto do
| ponto vazio é justamente a prova da ida. As fotos entram como NOME de arquivo —
| o protótipo não guarda imagem, e inventar miniatura falsa prometeria o que a
| tela não entrega.
|
| O `documento` da NOTIFICAÇÃO PRELIMINAR declara `notificado` e `prazo` no MESMO
| formato do trâmite das denúncias (`"5d"`): é dele que sai o vencimento do
| retorno, que a aba Acervo mostra porque é a única informação da fila que
| continua correndo depois de a chefia dar ciência. Dois formatos para a mesma
| informação (horas aqui, dias lá) dariam duas contas do mesmo prazo.
|
| ⚠️ A proporção EDUCATIVA vale aqui também: a maioria termina sem documento. Ao
| acrescentar casos, mantenha isso — uma amostra em que todo mundo é autuado
| desenha um sistema punitivo que não é o do cliente.
|
*/

return [

    /*
     * De onde a equipe saiu para o ponto, quando não foi denúncia. Lista fechada
     * porque é o que o relatório soma ("quanto do trabalho é planejado?"), e
     * porque é a informação que separa ronda de operação na leitura da fila.
     */
    'origens' => [
        'Operação planejada',
        'Ronda da equipe',
        'Pedido de outro órgão',
    ],

    'registros' => [

        [
            'id' => 1,
            'equipe' => 'C1',
            'fiscal' => 1,
            'origem' => 'Operação planejada',
            'referencia' => 'Operação Verão — Orla',
            'concluida_ha_horas' => 9,
            'logradouro' => 'Avenida Otávio Mangabeira',
            'numero' => '1980',
            'bairro' => 'Costa Azul',
            'ponto_de_referencia' => 'em frente ao posto salva-vidas',
            'desfecho' => 'Regularizado no local',
            'documento' => null,
            'gps' => '-12.9977, -38.4356',
            'precisao_m' => 9,
            'alvo' => 'Três permissionários de barraca de praia, todos com permissão regular no trecho',
            'equipamento' => 'Barracas de chapa com toldo',
            'fotos' => ['vistoria-barracas-faixa.jpg', 'vistoria-recuo-concluido.jpg'],
            'consideracoes' => 'Três barracas avançavam sobre a faixa de areia liberada. As três '
                .'recuaram na presença da equipe, sem resistência, e os permissionários têm ponto '
                .'autorizado no trecho. O avanço acontece todo fim de semana de sol.',
            'recomendacoes' => [
                'passagem',
            ],
        ],

        [
            'id' => 2,
            'equipe' => 'C1',
            'fiscal' => 3,
            'origem' => 'Ronda da equipe',
            'referencia' => 'Ronda diária da Área 5',
            'concluida_ha_horas' => 27,
            'logradouro' => 'Rua Doutor Nestor Duarte',
            'numero' => null,
            'bairro' => 'Itapuã',
            'ponto_de_referencia' => 'altura da praça, calçada ímpar',
            'desfecho' => 'Nada encontrado no local',
            'documento' => null,
            'gps' => '-12.9484, -38.3591',
            'precisao_m' => 14,
            // Nada encontrado: o alvo é NULO de propósito, e o registro vale — a
            // ida ao ponto é o fato, e a foto do ponto vazio é a prova dela.
            'alvo' => null,
            'equipamento' => null,
            'fotos' => ['vistoria-ponto-vazio.jpg'],
            'consideracoes' => 'O carrinho de milho que a equipe vinha encontrando ali não estava no '
                .'ponto. Comerciantes vizinhos informaram que ele passou a montar depois das 19h.',
            'recomendacoes' => [
                'reprogramar',
            ],
        ],

        [
            'id' => 3,
            'equipe' => 'C2',
            'fiscal' => 2,
            'origem' => 'Operação planejada',
            'referencia' => 'Rotina Centro',
            'concluida_ha_horas' => 15,
            'logradouro' => 'Rua Chile',
            'numero' => '44',
            'bairro' => 'Centro Histórico',
            'ponto_de_referencia' => 'esquina com a Praça Castro Alves',
            'desfecho' => 'Notificação Preliminar emitida',
            // Só o tipo e o número: a leitura do documento inteiro é do trâmite
            // da denúncia (a Retaguarda não emite documento de campo — ver o doc
            // de regra das Denúncias, RN-18). Aqui a fila só precisa dizer QUE
            // houve papel, e qual.
            'documento' => [
                'tipo' => 'np',
                'numero' => '194906',
                'notificado' => 'Ocupante não identificado — recusou-se a informar o documento',
                // O prazo é a CHAVE do catálogo do impresso
                // (`prototipo_documentos_campo.prazos_np`), a mesma que o documento
                // do trâmite declara. A DURAÇÃO dela mora lá, e num lugar só: escrita
                // aqui em dias, mudar "48 horas" no catálogo deixaria este registro
                // contando o prazo antigo, e a chefia voltaria ao ponto no dia errado.
                'prazo' => '48h',
            ],
            'gps' => '-12.9738, -38.5122',
            'precisao_m' => 7,
            'alvo' => 'Ocupante sem cadastro, presente no local',
            'equipamento' => 'Banca de bijuteria sobre a calçada',
            'fotos' => ['vistoria-calcada-estreita.jpg', 'vistoria-via-assinada.jpg'],
            'consideracoes' => 'Banca de bijuteria montada sobre a calçada estreita, obrigando o '
                .'pedestre a andar na pista, em rua de grande circulação. O ocupante não tem '
                .'cadastro e recusou-se a desmontar; notificado com prazo de 48 horas. A via foi '
                .'assinada por ele e por uma testemunha.',
            'recomendacoes' => [
                'retorno',
                'sgci',
            ],
        ],

        [
            'id' => 4,
            'equipe' => 'A2',
            'fiscal' => 4,
            'origem' => 'Ronda da equipe',
            'referencia' => 'Ronda semanal da Área 3',
            'concluida_ha_horas' => 34,
            'logradouro' => 'Avenida Dom João VI',
            'numero' => '312',
            'bairro' => 'Brotas',
            'ponto_de_referencia' => 'em frente ao mercado',
            'desfecho' => 'Regularizado no local',
            'documento' => null,
            'gps' => '-12.9799, -38.4922',
            'precisao_m' => 11,
            'alvo' => 'Josivaldo Menezes da Paz — permissão 2019/033.410, regular',
            'equipamento' => 'Carrinho de água de coco',
            'fotos' => ['vistoria-rampa-ocupada.jpg', 'vistoria-rampa-liberada.jpg'],
            'consideracoes' => 'O ponto avançava sobre a rampa de acesso do mercado. O permissionário '
                .'recuou o equipamento e liberou a rampa na hora. Ele pediu orientação sobre a '
                .'renovação da permissão, que vence no mês que vem.',
            'recomendacoes' => [
                'sgci',
            ],
        ],

        [
            'id' => 5,
            'equipe' => 'A2',
            'fiscal' => 1,
            'origem' => 'Pedido de outro órgão',
            'referencia' => 'Pedido da Transalvador — faixa de ônibus',
            'concluida_ha_horas' => 52,
            'logradouro' => 'Avenida Vasco da Gama',
            'numero' => '765',
            'bairro' => 'Engenho Velho de Brotas',
            'ponto_de_referencia' => 'junto à parada de ônibus',
            'desfecho' => 'Nada encontrado no local',
            'documento' => null,
            'gps' => '-12.9866, -38.4993',
            'precisao_m' => 12,
            'alvo' => null,
            'equipamento' => null,
            'fotos' => ['vistoria-faixa-onibus-livre.jpg'],
            'consideracoes' => 'A equipe foi ao trecho indicado no pedido e não encontrou ocupação '
                .'na faixa nem na parada. O ponto que motivou o pedido foi desmontado, segundo os '
                .'permissionários vizinhos, na semana passada.',
            'recomendacoes' => [
                'nada',
            ],
        ],

        /*
         * Área 2 (equipe A1) NÃO tem conta de Chefe de Setor na demonstração, e
         * isto é de propósito: é o registro que o Coordenador e o administrador
         * enxergam e nenhum dos três chefes com conta vê — o recorte por área em
         * funcionamento, do lado da fila.
         */
        [
            'id' => 6,
            'equipe' => 'A1',
            'fiscal' => 2,
            'origem' => 'Ronda da equipe',
            'referencia' => 'Ronda semanal da Área 2',
            'concluida_ha_horas' => 41,
            'logradouro' => 'Rua da Ribeira',
            'numero' => '108',
            'bairro' => 'Ribeira',
            'ponto_de_referencia' => 'junto ao largo',
            'desfecho' => 'Regularizado no local',
            'documento' => null,
            'gps' => '-12.9218, -38.5081',
            'precisao_m' => 10,
            'alvo' => 'Antônia Ferreira dos Anjos — permissão 2021/007.118, regular',
            'equipamento' => 'Trailer com mesas no largo',
            'fotos' => ['vistoria-largo-mesas.jpg', 'vistoria-largo-liberado.jpg'],
            'consideracoes' => 'Mesas de um trailer ocupavam a passagem do largo em dia de movimento. '
                .'Foram recolhidas na presença da equipe. O permissionário alegou desconhecer o '
                .'limite do ponto e recebeu a orientação por escrito.',
            'recomendacoes' => [
                'passagem',
            ],
        ],

    ],

];

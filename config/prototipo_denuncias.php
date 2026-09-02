<?php

/*
|--------------------------------------------------------------------------
| PROTÓTIPO — Denúncias recebidas das ouvidorias, por INTEGRAÇÃO
|--------------------------------------------------------------------------
|
| ⚠️ DADO DE PROTÓTIPO. O módulo existe para o dono percorrer o fluxo e aprovar
| a forma antes de virar tabela, migration e contrato de API. Nada aqui é
| gravado em banco: as telas leem daqui e guardam o que a pessoa decide na
| SESSÃO (ver `App\Support\Prototipo\DenunciasFicticias`).
|
| ── O que este módulo NÃO é ─────────────────────────────────────────────────
|
| Não é a Caixa de Entrada. Lá o administrativo DIGITA o papel que chegou ao
| balcão; aqui a denúncia chega SOZINHA, por integração com a ouvidoria, e
| ninguém a cadastra — por isso as telas não têm botão de incluir. A distinção
| é deliberada e visível: cada denúncia carrega o carimbo de recebimento
| automático e o número que o canal de origem lhe deu, para que ninguém a
| confunda com registro interno.
|
| ── Os dois canais, e por que eles não são o mesmo formulário ───────────────
|
|   e-Salvador     — portal na internet. O cidadão se autentica para abrir, então
|                    o requerente vem SEMPRE identificado (nome, CPF, e-mail e
|                    telefone), o endereço vem estruturado (logradouro, número,
|                    referência) e o cidadão pode anexar foto e documento.
|   Fala Salvador  — atendimento por telefone (Disque 156). Pode ser ANÔNIMA, o
|                    relato é a transcrição do que o atendente ouviu (texto mais
|                    solto, às vezes sem número nem ponto de referência), a
|                    categoria é a que o atendente escolheu, e não há anexo:
|                    ninguém anexa foto por telefone.
|
| Um formulário só para os dois faria a tela pedir CPF de denúncia anônima e
| oferecer anexo a quem ligou — e faria o sistema mentir sobre a qualidade do
| endereço, que é justamente o que decide se dá para mandar equipe ao local.
|
| ── As datas são RELATIVAS de propósito ─────────────────────────────────────
|
| `recebida_ha_horas` e `prazo_em_dias` viram data na hora de servir a tela.
| Datas fixas envelhecem: uma semana depois da demonstração a lista apareceria
| inteira com prazo vencido, e o dono leria isso como comportamento do sistema.
|
| ── O TRÂMITE tem duas formas aqui, e cada denúncia usa UMA ────────────────────
|
| 1. **Derivado da situação** (o caso simples). A denúncia declara só a
|    `situacao`, e `DenunciasFicticias::tramitesDePartida()` monta o histórico que
|    aquela situação implica: recebimento → triagem → direcionamento. Serve para
|    as denúncias que ainda não foram a campo, onde cada passo produziu uma
|    DECISÃO e nada mais.
|
| 2. **Declarado passo a passo** (`'tramites' => [...]`, o caso avançado). A
|    denúncia que já andou até a vistoria, o desfecho e o documento não cabe numa
|    derivação: cada passo tem conteúdo próprio — o que o fiscal encontrou, as
|    fotos, o documento lavrado com número, motivos e prazo. Esses passos são
|    escritos um a um, com `ha_horas` RELATIVO ao recebimento (mesma razão das
|    datas relativas: data fixa envelhece) e `quem` em forma de PAPEL
|    (`fiscal`, `gestor`, `encarregado`…), resolvido contra
|    `config/prototipo_estrutura.php` na hora de servir. Nome de pessoa escrito
|    aqui daria dois donos ao mesmo cadastro, e um fiscal removido da equipe
|    continuaria assinando vistoria.
|
| ⚠️ Quem declara `tramites` declara TAMBÉM a `situacao`, e as duas têm de
| combinar: a `situacao` da denúncia é a do ÚLTIMO passo. Isso é conferido por
| teste (`tests/Feature/DenunciasTramiteTest.php`) em vez de confiado à
| revisão — as duas informações vizinhas num arquivo grande divergem no primeiro
| ajuste, e a tela mostraria "Concluída" com o trâmite parando em campo.
|
| ── Fiscalização é EDUCATIVA antes de punitiva ────────────────────────────────
|
| A maioria dos casos termina SEM documento: o fiscal chega, orienta, o ambulante
| desmonta e a irregularidade cessa ali. Os documentos (Notificação Preliminar e
| Auto de Apreensão) aparecem nesta amostra porque o dono precisa VER a leitura
| deles na Retaguarda — não porque o caminho normal seja autuar. Ao acrescentar
| casos, mantenha essa proporção: uma amostra em que todo mundo é autuado
| desenharia um sistema punitivo que não é o do cliente.
|
*/

return [

    /*
     * Os dois canais. Cada tela é UM canal, e é daqui que ela tira o próprio
     * nome, o texto de apresentação e o que o formato do canal permite —
     * escrito na tela, um dia discordaria do que o servidor valida.
     */
    'canais' => [

        'e-salvador' => [
            'slug' => 'e-salvador',
            'nome' => 'e-Salvador',
            'sistema' => 'Portal e-Salvador — Ouvidoria Geral do Município',
            /*
             * O artigo que combina com `sistema`. Existe porque a frase da tela
             * põe o nome do sistema no meio dela ("Denúncias que O portal… / que A
             * central… entrega ao SEFAL"), e um artigo fixo no texto erra em um
             * dos dois canais. Concordância é do dado, não do molde.
             */
            'artigo' => 'o',
            'prefixo_origem' => 'ESL',
            /*
             * O canal admite denúncia anônima? O e-Salvador exige conta
             * (gov.br), então não: quem abre está identificado. É esta chave que
             * faz a tela deixar de perguntar o que não existe naquele canal.
             */
            'admite_anonima' => false,
            'tem_anexo' => true,
            'endereco_estruturado' => true,
            'prazo_em_dias' => 10,
            'como_chega' => 'Recebida por integração automática com o portal e-Salvador. '
                .'O cidadão abre a denúncia autenticado, então nome, CPF e contato vêm do canal.',
        ],

        'fala-salvador' => [
            'slug' => 'fala-salvador',
            'nome' => 'Fala Salvador',
            'sistema' => 'Central de atendimento Fala Salvador — Disque 156',
            'artigo' => 'a',
            'prefixo_origem' => '156',
            'admite_anonima' => true,
            'tem_anexo' => false,
            'endereco_estruturado' => false,
            'prazo_em_dias' => 10,
            'como_chega' => 'Recebida por integração automática com a central telefônica. '
                .'O relato é a transcrição do atendimento, e a denúncia pode ser anônima.',
        ],

    ],

    /*
     * As situações, na ordem do fluxo. Duas etapas com dois donos, e depois delas
     * a vida da denúncia EM CAMPO:
     *
     *   Recebida            → chegou por integração e espera a TRIAGEM (administrativo);
     *   Encaminhada à área  → triada; espera o DIRECIONAMENTO do gestor daquela área;
     *   Direcionada à equipe| Em operação → o gestor decidiu como o trabalho acontece;
     *   Em campo            → a equipe recebeu no aplicativo e foi ao local;
     *   Aguardando regularização → foi lavrada Notificação Preliminar e o PRAZO dela
     *                         está correndo: a bola está com o notificado, não com o
     *                         SEFAL. Sem este estado, uma notificação em prazo ficaria
     *                         indistinguível de vistoria que ninguém fez;
     *   Retorno vencido     → o prazo da notificação venceu e o retorno encontrou o
     *                         ponto na MESMA situação: a denúncia volta ao gestor para
     *                         a próxima medida (apreensão). É o estado que cobra
     *                         decisão de gente, e por isso ele é vermelho na tela;
     *   Concluída           → a vistoria teve desfecho e a denúncia se encerrou;
     *   Devolvida / Arquivada → as duas saídas da triagem, sempre com justificativa.
     */
    'situacoes' => [
        'Recebida',
        'Encaminhada à área',
        'Direcionada à equipe',
        'Em operação',
        'Em campo',
        'Aguardando regularização',
        'Retorno vencido',
        'Concluída',
        'Devolvida',
        'Arquivada',
    ],

    /*
     * COMO a vistoria terminou. Lista fechada porque é o que o relatório soma —
     * "quantas denúncias se resolveram sem documento" é a pergunta que mede se a
     * fiscalização está sendo educativa, e ela não se responde com texto livre.
     *
     * O desfecho é declarado no PASSO do trâmite que o produziu, e a denúncia
     * herda o do último passo que tiver um. Gravado também na denúncia, ele seria
     * a mesma informação com dois donos: um dia o trâmite diria "regularizado no
     * local" e o resumo continuaria dizendo "notificado".
     */
    'desfechos' => [
        'Regularizado no local',
        'Nada encontrado no local',
        'Notificação Preliminar emitida',
        'Regularizado após notificação',
        'Retorno com a situação mantida',
        'Auto de Apreensão lavrado',
    ],

    /*
     * Por que uma denúncia é devolvida ao canal ou arquivada na triagem. A
     * escolha é de lista para o relatório poder somar por motivo; o texto livre
     * continua obrigatório ao lado, porque o motivo genérico não conta o caso.
     */
    'motivos_de_devolucao' => [
        'Endereço insuficiente para localizar o ponto',
        'Fora da competência da SEFAL',
        'Denúncia duplicada — já existe registro do mesmo fato',
        'Situação já regularizada em vistoria anterior',
        'Relato sem elementos mínimos para vistoria',
    ],

    'destinos_de_retorno' => [
        'Devolvida ao canal de origem',
        'Arquivada',
    ],

    /*
     * As operações abertas a que o gestor pode ANEXAR uma denúncia, em vez de
     * direcionar avulso à equipe. É a segunda saída do direcionamento: quando já
     * existe trabalho planejado naquela região, a denúncia entra nele em vez de
     * gerar uma ida isolada.
     */
    'operacoes' => [
        [
            'id' => 1,
            'nome' => 'Operação Verão — Orla',
            'area' => 'Área 5',
            'equipe' => 'C1',
            'periodo' => 'até o fim de março',
            'foco' => 'Orla de Itapuã a Boca do Rio, com ênfase em barracas de praia.',
        ],
        [
            'id' => 2,
            'nome' => 'Rotina Centro',
            'area' => 'Área 1',
            'equipe' => 'C2',
            'periodo' => 'permanente',
            'foco' => 'Varredura semanal do Centro Histórico, Comércio e Barris.',
        ],
        [
            'id' => 3,
            'nome' => 'Operação Feira de São Joaquim',
            'area' => 'Área 2',
            'equipe' => 'A1',
            'periodo' => 'próximas duas semanas',
            'foco' => 'Entorno da feira e acesso da Calçada.',
        ],
        [
            'id' => 4,
            'nome' => 'Operação Volta às Aulas — Cajazeiras',
            'area' => 'Área 6',
            'equipe' => 'B1',
            'periodo' => 'próximos dez dias',
            'foco' => 'Entorno de escolas em Cajazeiras, Sussuarana e Tancredo Neves.',
        ],
        [
            'id' => 5,
            'nome' => 'Operação Noturna — Corredor da Vitória',
            'area' => 'Noturna',
            'equipe' => 'N1',
            'periodo' => 'sextas e sábados',
            'foco' => 'Som alto e mesas no logradouro depois das 22h.',
        ],
    ],

    /*
     * As denúncias como elas chegaram das duas ouvidorias. Cada linha é um caso
     * que o dono reconhece — inclusive os que não dão certo: anônima sem
     * endereço, duplicada, prazo já vencido, endereço que não localiza ponto.
     *
     * `bairro` é sempre um bairro REAL da estrutura de áreas
     * (`config/prototipo_estrutura.php`): é dele que sai a área sugerida. Dois
     * casos usam bairro COMPARTILHADO entre áreas (Mussurunga em 5 e 6,
     * Comércio na Área 1 e na Itinerante), porque o vínculo bairro↔área não é
     * 1:1 e a tela precisa mostrar essa escolha em vez de esconder.
     */
    'denuncias' => [

        // ── e-Salvador ──────────────────────────────────────────────────────

        [
            'id' => 1,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114872',
            'recebida_ha_horas' => 3,
            'prazo_em_dias' => 10,
            'anonima' => false,
            'requerente' => 'Marina Coelho Sampaio',
            'documento' => '024.556.318-40',
            'email' => 'marina.sampaio@exemplo.com.br',
            'telefone' => '(71) 98812-4470',
            'assunto' => 'Barraca em calçada impedindo passagem de pedestre',
            'relato' => 'Há uma barraca de lanches montada sobre a calçada em frente ao número 1180, '
                .'ocupando toda a largura e obrigando quem passa a descer para a rua. Funciona todos os '
                .'dias a partir das 17h.',
            'logradouro' => 'Rua Marquês de Caravelas',
            'numero' => '1180',
            'referencia' => 'em frente à agência bancária',
            'bairro' => 'Barra',
            'endereco_impreciso' => false,
            'anexos' => ['foto-calcada-barra.jpg', 'foto-barraca-lateral.jpg'],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 2,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114855',
            'recebida_ha_horas' => 9,
            'prazo_em_dias' => 10,
            'anonima' => false,
            'requerente' => 'Otávio Bandeira Nunes',
            'documento' => '571.204.887-11',
            'email' => 'otavio.nunes@exemplo.com.br',
            'telefone' => '(71) 99304-1187',
            'assunto' => 'Venda de bebida alcoólica em ponto sem autorização',
            'relato' => 'O trailer instalado na esquina passou a vender cerveja e destilados, com mesas '
                .'na via. O movimento avança pela madrugada e o barulho impede o sono dos moradores.',
            'logradouro' => 'Avenida Otávio Mangabeira',
            'numero' => '4270',
            'referencia' => 'esquina com a Rua Ceará',
            'bairro' => 'Itapuã',
            'endereco_impreciso' => false,
            'anexos' => ['foto-trailer-itapua.jpg'],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 3,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114803',
            'recebida_ha_horas' => 27,
            'prazo_em_dias' => 9,
            'anonima' => false,
            'requerente' => 'Condomínio Edifício Aurora (síndica Lúcia Bastos)',
            'documento' => '13.884.207/0001-56',
            'email' => 'sindico.aurora@exemplo.com.br',
            'telefone' => '(71) 3245-7788',
            'assunto' => 'Mesas e cadeiras no logradouro sem alvará',
            'relato' => 'O bar do térreo espalha mesas por toda a calçada e pela faixa de estacionamento, '
                .'todos os fins de semana. Os moradores não conseguem acessar a garagem.',
            'logradouro' => 'Rua Rio Grande do Sul',
            'numero' => '212',
            'referencia' => 'ao lado da praça',
            'bairro' => 'Pituba',
            'endereco_impreciso' => false,
            'anexos' => ['foto-mesas-calcada.jpg', 'ata-assembleia-condominio.pdf'],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 4,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114790',
            'recebida_ha_horas' => 33,
            'prazo_em_dias' => 8,
            'anonima' => false,
            'requerente' => 'Reginaldo Sacramento Lima',
            'documento' => '318.771.026-90',
            'email' => 'reginaldo.lima@exemplo.com.br',
            'telefone' => '(71) 98177-3390',
            'assunto' => 'Comércio ambulante em área de embarque de ônibus',
            'relato' => 'Vendedores se instalaram na plataforma de embarque, entre os pontos, e os '
                .'passageiros têm de circular pela pista para pegar o ônibus.',
            'logradouro' => 'Praça da Sé',
            'numero' => 's/n',
            'referencia' => 'plataforma de embarque, lado do elevador',
            'bairro' => 'Centro Histórico',
            'endereco_impreciso' => false,
            'anexos' => ['foto-plataforma-se.jpg'],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 5,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114721',
            'recebida_ha_horas' => 52,
            'prazo_em_dias' => 6,
            'anonima' => false,
            'requerente' => 'Tereza Cristina do Vale',
            'documento' => '905.442.117-32',
            'email' => 'tereza.vale@exemplo.com.br',
            'telefone' => '(71) 99671-2204',
            'assunto' => 'Equipamento alterado além do padrão autorizado',
            'relato' => 'O box do mercado foi ampliado com uma puxada de madeira que invade o corredor '
                .'central e bloqueia a saída de emergência.',
            'logradouro' => 'Rua da Feira',
            'numero' => '77',
            'referencia' => 'corredor central do mercado',
            'bairro' => 'Comércio',
            'endereco_impreciso' => false,
            'anexos' => ['foto-box-ampliado.jpg'],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 6,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114688',
            'recebida_ha_horas' => 76,
            'prazo_em_dias' => 3,
            'anonima' => false,
            'requerente' => 'Alfredo Peixoto Guimarães',
            'documento' => '447.019.552-08',
            'email' => 'alfredo.guimaraes@exemplo.com.br',
            'telefone' => '(71) 98450-6612',
            'assunto' => 'Ponto de venda de água de coco em canteiro central',
            'relato' => 'A banca foi montada no canteiro central da avenida, e os clientes atravessam a '
                .'pista correndo para comprar. Já houve quase atropelamento.',
            'logradouro' => 'Avenida Luís Viana Filho',
            'numero' => 's/n',
            'referencia' => 'canteiro central, altura do viaduto',
            'bairro' => 'Mussurunga',
            'endereco_impreciso' => false,
            'anexos' => ['foto-canteiro-central.jpg'],
            // Já encaminhada à Área 5, que é a do `gestor1`: cada gestor com conta
            // de demonstração precisa de fila NOS DOIS canais, senão a etapa de
            // direcionamento abre vazia e não há o que demonstrar. Mussurunga é
            // bairro compartilhado (Área 5 e Área 6), então esta linha também
            // mostra a escolha que a triagem tomou.
            'situacao' => 'Encaminhada à área',
            'area' => 'Área 5',
        ],

        [
            'id' => 7,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114602',
            'recebida_ha_horas' => 121,
            'prazo_em_dias' => -2,
            'anonima' => false,
            'requerente' => 'Sílvia Regina Andrade Matos',
            'documento' => '660.318.294-75',
            'email' => 'silvia.matos@exemplo.com.br',
            'telefone' => '(71) 99012-8845',
            'assunto' => 'Animal preso em equipamento de venda',
            'relato' => 'O vendedor mantém um cachorro amarrado dentro do carrinho durante todo o dia, '
                .'sem água e exposto ao sol.',
            'logradouro' => 'Rua Barão de Cotegipe',
            'numero' => '640',
            'referencia' => 'em frente ao posto de saúde',
            'bairro' => 'Calçada',
            'endereco_impreciso' => false,
            'anexos' => ['foto-carrinho-animal.jpg'],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 8,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114571',
            'recebida_ha_horas' => 145,
            'prazo_em_dias' => 4,
            'anonima' => false,
            'requerente' => 'Jaqueline Ferreira do Espírito Santo',
            'documento' => '210.883.647-19',
            'email' => 'jaqueline.santo@exemplo.com.br',
            'telefone' => '(71) 98338-0021',
            'assunto' => 'Ocupação de vaga de carga e descarga por ponto fixo',
            'relato' => 'A vaga de carga e descarga em frente ao comércio virou ponto fixo de venda de '
                .'frutas, e os caminhões descarregam em fila dupla.',
            'logradouro' => 'Rua Chile',
            'numero' => '31',
            'referencia' => null,
            'bairro' => 'Centro Histórico',
            'endereco_impreciso' => false,
            'anexos' => [],
            'situacao' => 'Encaminhada à área',
            'area' => 'Área 1',
        ],

        [
            'id' => 9,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114520',
            'recebida_ha_horas' => 168,
            'prazo_em_dias' => 3,
            'anonima' => false,
            'requerente' => 'Wellington Barreto de Jesus',
            'documento' => '733.560.981-24',
            'email' => 'wellington.jesus@exemplo.com.br',
            'telefone' => '(71) 99745-3318',
            'assunto' => 'Churrasqueira em via pública com risco de incêndio',
            'relato' => 'A churrasqueira improvisada fica a menos de um metro do medidor de gás do '
                .'prédio vizinho.',
            'logradouro' => 'Rua Silveira Martins',
            'numero' => '2211',
            'referencia' => 'ao lado da entrada do prédio azul',
            'bairro' => 'Cabula',
            'endereco_impreciso' => false,
            'anexos' => ['foto-churrasqueira.jpg'],
            'situacao' => 'Encaminhada à área',
            'area' => 'Área 3',
        ],

        [
            'id' => 10,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114488',
            'recebida_ha_horas' => 196,
            'prazo_em_dias' => 1,
            'anonima' => false,
            'requerente' => 'Ana Beatriz Carvalho Pinto',
            'documento' => '155.907.436-88',
            'email' => 'anabeatriz.pinto@exemplo.com.br',
            'telefone' => '(71) 98290-5567',
            'assunto' => 'Venda de alimentos sem condição de higiene',
            'relato' => 'O ponto manipula salgados sem água corrente e descarta óleo na boca de lobo.',
            'logradouro' => 'Avenida Vasco da Gama',
            'numero' => '480',
            'referencia' => 'próximo ao acesso do estádio',
            'bairro' => 'Vasco da Gama',
            'endereco_impreciso' => false,
            'anexos' => ['foto-manipulacao.jpg', 'video-descarte.mp4'],
            'situacao' => 'Direcionada à equipe',
            'area' => 'Área 1',
            'equipe' => 'C2',
        ],

        [
            'id' => 11,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114402',
            'recebida_ha_horas' => 244,
            'prazo_em_dias' => 2,
            'anonima' => false,
            'requerente' => 'Márcio Vinícius Torres',
            'documento' => '822.114.075-63',
            'email' => 'marcio.torres@exemplo.com.br',
            'telefone' => '(71) 99188-7742',
            'assunto' => 'Barracas de praia além da faixa autorizada',
            'relato' => 'As barracas avançaram sobre a área de banho e não deixam corredor de acesso ao '
                .'mar.',
            'logradouro' => 'Avenida Otávio Mangabeira',
            'numero' => 's/n',
            'referencia' => 'trecho entre os postos 4 e 5',
            'bairro' => 'Piatã',
            'endereco_impreciso' => false,
            'anexos' => ['foto-barracas-piata.jpg'],
            'situacao' => 'Em operação',
            'area' => 'Área 5',
            'equipe' => 'C1',
            'operacao' => 'Operação Verão — Orla',
        ],

        [
            'id' => 12,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114377',
            'recebida_ha_horas' => 268,
            'prazo_em_dias' => 1,
            'anonima' => false,
            'requerente' => 'Heloísa Prates Damasceno',
            'documento' => '408.662.719-05',
            'email' => 'heloisa.damasceno@exemplo.com.br',
            'telefone' => '(71) 98622-9910',
            'assunto' => 'Ponto de venda bloqueando rampa de acessibilidade',
            'relato' => 'A banca ficou exatamente sobre a rampa da esquina, e cadeirantes não conseguem '
                .'subir a calçada.',
            'logradouro' => 'Rua Ruy Barbosa',
            'numero' => '95',
            'referencia' => 'esquina com a Ladeira da Praça',
            'bairro' => 'Nazaré',
            'endereco_impreciso' => false,
            'anexos' => ['foto-rampa.jpg'],
            'situacao' => 'Em campo',
            'area' => 'Área 1',
            'equipe' => 'C2',
        ],

        [
            'id' => 13,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114290',
            'recebida_ha_horas' => 340,
            'prazo_em_dias' => -6,
            'anonima' => false,
            'requerente' => 'Cláudia Menezes Vilar',
            'documento' => '699.230.541-77',
            'email' => 'claudia.vilar@exemplo.com.br',
            'telefone' => '(71) 99433-1120',
            'assunto' => 'Equipamento abandonado ocupando ponto',
            'relato' => 'O quiosque está fechado há meses, tomado por lixo, e ninguém retira a estrutura.',
            'logradouro' => 'Praça Nossa Senhora da Luz',
            'numero' => 's/n',
            'referencia' => 'canto da praça, perto do coreto',
            'bairro' => 'Rio Vermelho',
            'endereco_impreciso' => false,
            'anexos' => ['foto-quiosque-abandonado.jpg'],
            /*
             * ── CASO AVANÇADO: AUTO DE APREENSÃO ──────────────────────────────
             *
             * Equipamento abandonado é o caso em que não há a quem orientar: não
             * existe ocupante para desmontar a estrutura, então a medida
             * educativa não tem endereço e o caminho é recolher e guardar. É por
             * isso que este é o único Auto de Apreensão da amostra — e note que
             * apreensão é GUARDA (os bens vão ao SEGUB por um prazo), não
             * destruição.
             */
            'situacao' => 'Concluída',
            'area' => 'Área 1',
            'equipe' => 'C2',
            'tramites' => [
                ['ha_horas' => 0, 'quem' => 'integracao'],
                [
                    'ha_horas' => 5,
                    'quem' => 'administrativo',
                    'o_que' => 'Triada e encaminhada à área',
                    'detalhe' => 'Encaminhada à Área 1 para direcionamento do gestor.',
                    'situacao' => 'Encaminhada à área',
                    'campos' => [
                        ['rotulo' => 'Bairro que sugeriu a área', 'valor' => 'Rio Vermelho'],
                        ['rotulo' => 'Área de destino', 'valor' => 'Área 1 — Centro'],
                        ['rotulo' => 'Orientação ao gestor', 'valor' => 'Estrutura fechada há meses; verificar se há permissionário vinculado.'],
                    ],
                ],
                [
                    'ha_horas' => 9,
                    'quem' => 'gestor',
                    'o_que' => 'Direcionada à equipe',
                    'detalhe' => 'Direcionada à Equipe C2 para vistoria.',
                    'situacao' => 'Direcionada à equipe',
                    'campos' => [
                        ['rotulo' => 'Saída escolhida', 'valor' => 'Vistoria dirigida à equipe da própria área'],
                        ['rotulo' => 'Por que não entrou em operação', 'valor' => 'Ponto isolado, sem trabalho planejado na praça no período.'],
                    ],
                ],
                [
                    'ha_horas' => 33,
                    'quem' => 'fiscal',
                    'o_que' => 'Vistoria em campo',
                    'detalhe' => 'A equipe foi ao local e encontrou a estrutura fechada e abandonada.',
                    'situacao' => 'Em campo',
                    'campo' => [
                        'encontrado' => 'Ponto irregular, sem ocupante no local',
                        'relato' => 'Quiosque de alvenaria leve fechado com tapume, sem qualquer atividade. '
                            .'Acúmulo de lixo e entulho no entorno, com foco de água parada. Nenhum '
                            .'responsável apareceu durante a vistoria, e os comerciantes vizinhos '
                            .'informaram que o ponto está fechado há mais de seis meses. Não há '
                            .'permissão afixada no equipamento.',
                        'fotos' => [
                            'vistoria-quiosque-frente.jpg',
                            'vistoria-quiosque-entulho.jpg',
                            'vistoria-quiosque-lateral.jpg',
                        ],
                        'gps' => '-13.0106, -38.4917',
                        'precisao_m' => 7,
                    ],
                ],
                [
                    'ha_horas' => 34,
                    'quem' => 'fiscal',
                    'o_que' => 'Auto de Apreensão lavrado',
                    'detalhe' => 'Sem ocupante a quem notificar, a estrutura foi recolhida e encaminhada à guarda de bens.',
                    'situacao' => 'Em campo',
                    'documento' => [
                        'tipo' => 'aa',
                        'numero' => '160051',
                        'notificado' => 'Não identificado — equipamento abandonado',
                        'cpf' => null,
                        'equipamento' => 'Quiosque',
                        'atividade' => 'Comércio Informal',
                        'local' => 'Praça Nossa Senhora da Luz, s/n — canto da praça, perto do coreto — Rio Vermelho',
                        'decretos' => ['Decreto Nº 26.849/2015'],
                        'prazo_guarda' => '90',
                        'destinacao' => 'leilao',
                        'itens' => [
                            ['quantidade' => 1, 'unidade' => 'un', 'descricao' => 'Estrutura de quiosque desmontável, em chapa metálica'],
                            ['quantidade' => 1, 'unidade' => 'un', 'descricao' => 'Freezer horizontal, fora de funcionamento'],
                            ['quantidade' => 2, 'unidade' => 'un', 'descricao' => 'Mesa plástica'],
                            ['quantidade' => 4, 'unidade' => 'un', 'descricao' => 'Cadeira plástica'],
                            ['quantidade' => 1, 'unidade' => 'un', 'descricao' => 'Guarda-sol, com estrutura danificada'],
                        ],
                        // Sem ocupante, ninguém assina o recebimento: o impresso
                        // sai com a linha em branco, e a tela diz isso em vez de
                        // esconder o campo — via não entregue é o que decide se o
                        // prazo corre.
                        'assinaturas' => [['rotulo' => 'Notificado', 'estado' => 'pendente']],
                    ],
                ],
                [
                    'ha_horas' => 38,
                    'quem' => 'encarregado',
                    'o_que' => 'Bens entregues ao SEGUB',
                    'detalhe' => 'Volumes recolhidos e entregues à guarda de bens, com o auto de apreensão anexado.',
                    'situacao' => 'Concluída',
                    'desfecho' => 'Auto de Apreensão lavrado',
                    'campos' => [
                        ['rotulo' => 'Volumes entregues', 'valor' => '9'],
                        ['rotulo' => 'Guia de recolhimento', 'valor' => 'SEGUB 2026/0417'],
                        ['rotulo' => 'Recebido por', 'valor' => 'Plantão do SEGUB — Av. San Martins, s/n'],
                    ],
                ],
            ],
        ],

        [
            'id' => 14,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114255',
            'recebida_ha_horas' => 364,
            'prazo_em_dias' => -7,
            'anonima' => false,
            'requerente' => 'Fernando Aguiar Bittencourt',
            'documento' => '271.448.309-16',
            'email' => 'fernando.bittencourt@exemplo.com.br',
            'telefone' => '(71) 98074-5583',
            'assunto' => 'Obra em imóvel sem licença',
            'relato' => 'Estão levantando um segundo pavimento no imóvel da esquina, sem placa de obra '
                .'nem licença à vista.',
            'logradouro' => 'Rua Aristides Novis',
            'numero' => '58',
            'referencia' => 'esquina com a Politécnica',
            'bairro' => 'Federação',
            'endereco_impreciso' => false,
            'anexos' => [],
            'situacao' => 'Devolvida',
            'motivo' => 'Fora da competência da SEFAL',
            'justificativa' => 'Obra em imóvel privado é atribuição da SEDUR, não da fiscalização de '
                .'ambulantes. Devolvida ao canal de origem com a indicação do órgão competente para que '
                .'o cidadão não perca o prazo.',
            'destino' => 'Devolvida ao canal de origem',
        ],

        // ── Fala Salvador (156) ─────────────────────────────────────────────

        [
            'id' => 15,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-889034',
            'recebida_ha_horas' => 2,
            'prazo_em_dias' => 10,
            'anonima' => true,
            'requerente' => null,
            'documento' => null,
            'email' => null,
            'telefone' => null,
            'assunto' => 'Som alto em ponto de venda depois das 22h',
            'relato' => 'Cidadã relata que o ponto liga caixa de som todas as noites e o barulho segue '
                .'depois das 22h. Não quis se identificar por medo de represália. Diz que é "na rua de '
                .'trás da praça, perto da banca de revista".',
            'logradouro' => 'Rua sem indicação',
            'numero' => null,
            'referencia' => 'atrás da praça, perto da banca de revista',
            'bairro' => 'Curuzu',
            'endereco_impreciso' => true,
            'atendente' => 'Central 156 — atendente 4471',
            'categoria' => 'Perturbação do sossego',
            'anexos' => [],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 16,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-889011',
            'recebida_ha_horas' => 5,
            'prazo_em_dias' => 10,
            'anonima' => false,
            'requerente' => 'Josivaldo Ramos Cerqueira',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 98811-2034',
            'assunto' => 'Carrinho de lanche em ponto de ônibus',
            'relato' => 'Morador informa que o carrinho para exatamente no abrigo do ponto de ônibus no '
                .'fim da tarde, e quem espera fica na chuva. Deu o endereço da esquina e o horário: das '
                .'17h às 23h.',
            'logradouro' => 'Rua Doutor José Peroba',
            'numero' => '275',
            'referencia' => 'abrigo do ponto de ônibus',
            'bairro' => 'Stiep',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4402',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            // Fila do `gestor1` (Área 5) neste canal — ver a nota da denúncia 6.
            'situacao' => 'Encaminhada à área',
            'area' => 'Área 5',
        ],

        [
            'id' => 17,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888970',
            'recebida_ha_horas' => 11,
            'prazo_em_dias' => 9,
            'anonima' => true,
            'requerente' => null,
            'documento' => null,
            'email' => null,
            'telefone' => null,
            'assunto' => 'Venda de gás em residência',
            'relato' => 'Denúncia anônima de revenda de botijões de gás no quintal de uma casa, com '
                .'botijões empilhados junto ao muro. O denunciante não soube dizer o número e descreveu '
                .'"a casa do portão verde, subindo a ladeira".',
            'logradouro' => 'Ladeira sem indicação',
            'numero' => null,
            'referencia' => 'casa de portão verde, subindo a ladeira',
            'bairro' => 'Sussuarana',
            'endereco_impreciso' => true,
            'atendente' => 'Central 156 — atendente 4418',
            'categoria' => 'Atividade de risco',
            'anexos' => [],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 18,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888944',
            'recebida_ha_horas' => 20,
            'prazo_em_dias' => 9,
            'anonima' => false,
            'requerente' => 'Maria de Lourdes Passos',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 3611-4409',
            'assunto' => 'Mercadoria estendida na calçada da feira',
            'relato' => 'Comerciante com box regular reclama que os vizinhos estendem lona e mercadoria '
                .'na calçada, fechando a entrada do box dela. Pede vistoria em dia de feira, pela manhã.',
            'logradouro' => 'Avenida Engenheiro Oscar Pontes',
            'numero' => '410',
            'referencia' => 'entrada lateral da feira',
            'bairro' => 'Calçada',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4390',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 19,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888901',
            'recebida_ha_horas' => 29,
            'prazo_em_dias' => 8,
            'anonima' => true,
            'requerente' => null,
            'documento' => null,
            'email' => null,
            'telefone' => null,
            'assunto' => 'Ponto de venda cedido a terceiro',
            'relato' => 'Anônimo afirma que o permissionário não aparece há mais de um ano e alugou o '
                .'ponto para outra pessoa, que hoje trabalha no lugar dele.',
            'logradouro' => 'Rua da Independência',
            'numero' => '128',
            'referencia' => null,
            'bairro' => 'Comércio',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4455',
            'categoria' => 'Irregularidade de permissão',
            'anexos' => [],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 20,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888860',
            'recebida_ha_horas' => 44,
            'prazo_em_dias' => 7,
            'anonima' => false,
            'requerente' => 'Edvaldo Nascimento Rocha',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 99201-7734',
            'assunto' => 'Fogão a lenha em barraca junto a poste de energia',
            'relato' => 'Relata fumaça e fagulha subindo em direção à fiação. Diz que já avisou o '
                .'vendedor e não houve mudança.',
            'logradouro' => 'Rua Direta de Periperi',
            'numero' => '318',
            'referencia' => 'perto da estação de trem',
            'bairro' => 'Periperi',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4407',
            'categoria' => 'Atividade de risco',
            'anexos' => [],
            'situacao' => 'Recebida',
        ],

        [
            'id' => 21,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888812',
            'recebida_ha_horas' => 62,
            'prazo_em_dias' => -1,
            'anonima' => true,
            'requerente' => null,
            'documento' => null,
            'email' => null,
            'telefone' => null,
            'assunto' => 'Barraca em área de escola no horário de saída',
            'relato' => 'Denúncia anônima sobre venda de doces e refrigerante no portão da escola, com '
                .'aglomeração no horário de saída das crianças.',
            'logradouro' => 'Rua Álvaro Adorno',
            'numero' => '77',
            'referencia' => 'portão principal da escola municipal',
            'bairro' => 'Pernambués',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4433',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            // Fila do `gestor3` (Área 3) neste canal, e com o PRAZO JÁ VENCIDO: o
            // gestor precisa ver na própria lista dele que há coisa atrasada.
            'situacao' => 'Encaminhada à área',
            'area' => 'Área 3',
        ],

        [
            'id' => 22,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888744',
            'recebida_ha_horas' => 90,
            'prazo_em_dias' => 5,
            'anonima' => false,
            'requerente' => 'Nilza Batista de Souza',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 98567-3311',
            'assunto' => 'Ambulante em canteiro de avenida movimentada',
            'relato' => 'Informa que a venda acontece no meio do canteiro e que os clientes param o '
                .'carro na faixa da direita para comprar.',
            'logradouro' => 'Avenida Paralela',
            'numero' => 's/n',
            'referencia' => 'altura do acesso a Mussurunga',
            'bairro' => 'Mussurunga',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4412',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            'situacao' => 'Encaminhada à área',
            'area' => 'Área 6',
        ],

        [
            'id' => 23,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888702',
            'recebida_ha_horas' => 118,
            'prazo_em_dias' => 4,
            'anonima' => true,
            'requerente' => null,
            'documento' => null,
            'email' => null,
            'telefone' => null,
            'assunto' => 'Bebida alcoólica vendida a menor de idade',
            'relato' => 'Anônimo relata venda de cerveja a adolescentes no ponto da praça, à noite.',
            'logradouro' => 'Praça Conselheiro Almeida Couto',
            'numero' => 's/n',
            'referencia' => 'ponto próximo ao chafariz',
            'bairro' => 'Barbalho',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4426',
            'categoria' => 'Irregularidade de permissão',
            'anexos' => [],
            'situacao' => 'Encaminhada à área',
            'area' => 'Área 1',
        ],

        [
            'id' => 24,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888655',
            'recebida_ha_horas' => 142,
            'prazo_em_dias' => 3,
            'anonima' => false,
            'requerente' => 'Antônio Sérgio Barbosa Filho',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 99655-0092',
            'assunto' => 'Ocupação da via em dia de evento',
            'relato' => 'Pede fiscalização no fim de semana do evento, quando a rua inteira é tomada por '
                .'vendedores e nem ambulância consegue passar.',
            'logradouro' => 'Rua Chile',
            'numero' => 's/n',
            'referencia' => 'trecho fechado para o evento',
            'bairro' => 'Comércio',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4441',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            'situacao' => 'Em operação',
            'area' => 'Área 1',
            'equipe' => 'C2',
            'operacao' => 'Rotina Centro',
        ],

        [
            'id' => 25,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888590',
            'recebida_ha_horas' => 190,
            'prazo_em_dias' => 2,
            'anonima' => false,
            'requerente' => 'Rosângela Muniz de Almeida',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 98122-6650',
            'assunto' => 'Depósito de mercadoria em área comum de mercado',
            'relato' => 'Relata caixas empilhadas no corredor do mercado, impedindo a circulação e a '
                .'limpeza.',
            'logradouro' => 'Mercado do Rio Vermelho',
            'numero' => 's/n',
            'referencia' => 'corredor dos fundos',
            'bairro' => 'Rio Vermelho',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4398',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            'situacao' => 'Direcionada à equipe',
            'area' => 'Área 1',
            'equipe' => 'N1',
            'justificativa_equipe' => 'O corredor só esvazia depois do fechamento, então a vistoria foi '
                .'passada à equipe Noturna, que trabalha no turno em que o flagrante é possível.',
        ],

        [
            'id' => 26,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888511',
            'recebida_ha_horas' => 238,
            'prazo_em_dias' => -3,
            'anonima' => true,
            'requerente' => null,
            'documento' => null,
            'email' => null,
            'telefone' => null,
            'assunto' => 'Venda de produto de origem desconhecida',
            'relato' => 'Anônimo diz que a banca vende celular usado sem nota, mas não soube informar '
                .'rua, número nem ponto de referência — apenas "no bairro".',
            'logradouro' => 'Sem indicação',
            'numero' => null,
            'referencia' => null,
            'bairro' => 'São Marcos',
            'endereco_impreciso' => true,
            'atendente' => 'Central 156 — atendente 4460',
            'categoria' => 'Irregularidade de permissão',
            'anexos' => [],
            'situacao' => 'Arquivada',
            'motivo' => 'Endereço insuficiente para localizar o ponto',
            'justificativa' => 'A denúncia é anônima e não traz rua, número nem ponto de referência: não '
                .'há como mandar equipe ao local. Arquivada com o registro do motivo, e volta ao fluxo se '
                .'o canal complementar o endereço.',
            'destino' => 'Arquivada',
        ],

        [
            'id' => 27,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888470',
            'recebida_ha_horas' => 286,
            'prazo_em_dias' => -5,
            'anonima' => false,
            'requerente' => 'Gilmar Teixeira dos Anjos',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 99870-4412',
            'assunto' => 'Ponto instalado em faixa de pedestre',
            'relato' => 'Relata que a banca ficou sobre a faixa de pedestre da esquina e ninguém '
                .'atravessa com segurança.',
            'logradouro' => 'Rua Cônego Pereira',
            'numero' => '199',
            'referencia' => 'esquina da faixa de pedestre',
            'bairro' => 'São Caetano',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4383',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            /*
             * ── CASO AVANÇADO: REGULARIZADO NO LOCAL, SEM DOCUMENTO ───────────
             *
             * O caminho COMUM da fiscalização, e o que o dono pediu para ver: o
             * fiscal chega, manda deslocar a banca, o ambulante desloca, e a
             * irregularidade cessa na presença dele. Nenhum papel é lavrado — e o
             * trâmite tem de dizer isso com todas as letras, senão quem ler
             * depois vai achar que a vistoria não terminou.
             *
             * Área 4 não tem conta de gestor na demonstração de propósito: é o
             * caso que o administrativo e o administrador enxergam e nenhum dos
             * três gestores com conta vê — o recorte por área em funcionamento.
             */
            'situacao' => 'Concluída',
            'area' => 'Área 4',
            'equipe' => 'B2',
            'tramites' => [
                ['ha_horas' => 0, 'quem' => 'integracao'],
                [
                    'ha_horas' => 6,
                    'quem' => 'administrativo',
                    'o_que' => 'Triada e encaminhada à área',
                    'detalhe' => 'Encaminhada à Área 4 para direcionamento do gestor.',
                    'situacao' => 'Encaminhada à área',
                    'campos' => [
                        ['rotulo' => 'Bairro que sugeriu a área', 'valor' => 'São Caetano'],
                        ['rotulo' => 'Área de destino', 'valor' => 'Área 4 — Liberdade'],
                        ['rotulo' => 'Orientação ao gestor', 'valor' => 'Faixa de pedestre ocupada: risco a quem atravessa, priorizar.'],
                    ],
                ],
                [
                    'ha_horas' => 10,
                    'quem' => 'gestor',
                    'o_que' => 'Direcionada à equipe',
                    'detalhe' => 'Direcionada à Equipe B2 para vistoria.',
                    'situacao' => 'Direcionada à equipe',
                    'campos' => [
                        ['rotulo' => 'Saída escolhida', 'valor' => 'Vistoria dirigida à equipe da própria área'],
                    ],
                ],
                [
                    'ha_horas' => 30,
                    'quem' => 'fiscal',
                    'o_que' => 'Vistoria em campo',
                    'detalhe' => 'A equipe encontrou a banca sobre a faixa de pedestre, com o ocupante presente.',
                    'situacao' => 'Em campo',
                    'campo' => [
                        'encontrado' => 'Ponto irregular, com o ocupante presente',
                        'ambulante' => 'Josenilda Barros da Conceição — permissão 2014/0882, regular',
                        'relato' => 'Banca de frutas montada sobre a faixa de pedestre da esquina, avançando '
                            .'cerca de um metro sobre a travessia. A permissionária tem permissão '
                            .'regular e ponto autorizado a oito metros dali, e havia deslocado a banca '
                            .'por causa de uma obra na calçada. Orientada quanto ao Art. 24 e à '
                            .'obrigação de manter a travessia livre.',
                        'fotos' => ['vistoria-faixa-antes.jpg', 'vistoria-faixa-depois.jpg'],
                        'gps' => '-12.9394, -38.4831',
                        'precisao_m' => 11,
                    ],
                ],
                [
                    'ha_horas' => 31,
                    'quem' => 'fiscal',
                    'o_que' => 'Regularizado no local, sem documento',
                    'detalhe' => 'A banca foi recuada para o ponto autorizado na presença da equipe. '
                        .'Nenhum documento foi lavrado.',
                    'situacao' => 'Concluída',
                    'desfecho' => 'Regularizado no local',
                    'campos' => [
                        ['rotulo' => 'Providência do ocupante', 'valor' => 'Recuou a banca para o ponto autorizado, liberando a travessia'],
                        ['rotulo' => 'Documento lavrado', 'valor' => 'Nenhum — a irregularidade cessou na presença da equipe'],
                        ['rotulo' => 'Orientação registrada', 'valor' => 'Comunicar a SEMOP antes de deslocar o ponto por obra na calçada'],
                    ],
                ],
            ],
        ],

        [
            'id' => 28,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888402',
            'recebida_ha_horas' => 334,
            'prazo_em_dias' => -6,
            'anonima' => true,
            'requerente' => null,
            'documento' => null,
            'email' => null,
            'telefone' => null,
            'assunto' => 'Mesa e cadeira na ciclovia',
            'relato' => 'Anônimo informa que as mesas do bar ocupam a ciclovia nos fins de semana.',
            'logradouro' => 'Avenida Sete de Setembro',
            'numero' => '1420',
            'referencia' => 'trecho da ciclovia',
            'bairro' => 'Avenida Sete de Setembro',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4471',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            'situacao' => 'Em campo',
            'area' => 'Itinerante',
            'equipe' => 'I1',
        ],

        /*
         * ── OS ESTÁGIOS AVANÇADOS DA FISCALIZAÇÃO ────────────────────────────
         *
         * Daqui para baixo estão as denúncias que já FORAM A CAMPO — pedido do
         * dono (02/09/2026): ele precisa ver o que a equipe recebeu no
         * aplicativo, o que encontrou, o desfecho que voltou e o documento,
         * quando houve. Cada uma declara o trâmite passo a passo (ver o cabeçalho
         * deste arquivo).
         *
         * A amostra é distribuída de propósito: os três gestores com conta de
         * demonstração (`gestor1` = Área 5, `gestor2` = Área 1, `gestor3` =
         * Área 3) têm caso avançado NOS DOIS canais. Sem isso, a demonstração
         * abriria vazia para dois deles e pareceria sistema quebrado.
         */

        [
            'id' => 29,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114930',
            'recebida_ha_horas' => 96,
            'prazo_em_dias' => 4,
            'anonima' => false,
            'requerente' => 'Danielle Xavier Sampaio',
            'documento' => '512.664.870-29',
            'email' => 'danielle.sampaio@exemplo.com.br',
            'telefone' => '(71) 98554-1192',
            'assunto' => 'Barraca de praia com puxada de madeira e depósito',
            'relato' => 'A barraca construiu uma puxada de madeira nos fundos, usada como depósito, e '
                .'ampliou a área de mesas para além do que era antes. A passagem para a areia ficou '
                .'estreita.',
            'logradouro' => 'Avenida Otávio Mangabeira',
            'numero' => '2140',
            'referencia' => 'em frente ao acesso da praia, ao lado do quiosque azul',
            'bairro' => 'Costa Azul',
            'endereco_impreciso' => false,
            'anexos' => ['foto-puxada-madeira.jpg', 'foto-passagem-estreita.jpg'],
            /*
             * ── CASO AVANÇADO: NOTIFICAÇÃO PRELIMINAR COM O PRAZO CORRENDO ────
             *
             * A bola está com o notificado: o prazo de 5 dias corre, e o SEFAL só
             * volta ao ponto depois dele. É o caso que justifica a situação
             * própria "Aguardando regularização" — sem ela, uma notificação em
             * prazo ficaria com a mesma cara de vistoria que ninguém fez.
             *
             * Chegou a campo por OPERAÇÃO, e não por vistoria avulsa: a Operação
             * Verão — Orla já cobre este trecho, e é assim que o gestor evita uma
             * ida isolada. A leitura do trâmite mostra as duas saídas do gestor em
             * funcionamento na mesma amostra.
             */
            'situacao' => 'Aguardando regularização',
            'area' => 'Área 5',
            'equipe' => 'C1',
            'operacao' => 'Operação Verão — Orla',
            'tramites' => [
                ['ha_horas' => 0, 'quem' => 'integracao'],
                [
                    'ha_horas' => 7,
                    'quem' => 'administrativo',
                    'o_que' => 'Triada e encaminhada à área',
                    'detalhe' => 'Encaminhada à Área 5 para direcionamento do gestor.',
                    'situacao' => 'Encaminhada à área',
                    'campos' => [
                        ['rotulo' => 'Bairro que sugeriu a área', 'valor' => 'Costa Azul'],
                        ['rotulo' => 'Área de destino', 'valor' => 'Área 5 — Boca do Rio'],
                        ['rotulo' => 'Orientação ao gestor', 'valor' => 'Cidadã anexou foto da puxada; trecho já coberto pela operação da orla.'],
                    ],
                ],
                [
                    'ha_horas' => 11,
                    'quem' => 'gestor',
                    'o_que' => 'Incluída em operação',
                    'detalhe' => 'Anexada à Operação Verão — Orla, executada pela Equipe C1.',
                    'situacao' => 'Em operação',
                    'campos' => [
                        ['rotulo' => 'Saída escolhida', 'valor' => 'Operação já planejada, em vez de ida isolada ao local'],
                        ['rotulo' => 'Operação', 'valor' => 'Operação Verão — Orla · até o fim de março'],
                        ['rotulo' => 'Foco da operação', 'valor' => 'Orla de Itapuã a Boca do Rio, com ênfase em barracas de praia.'],
                    ],
                ],
                [
                    'ha_horas' => 30,
                    'quem' => 'fiscal',
                    'o_que' => 'Vistoria em campo',
                    'detalhe' => 'A equipe vistoriou o ponto dentro da varredura da operação.',
                    'situacao' => 'Em campo',
                    'campo' => [
                        'encontrado' => 'Ponto irregular, com o permissionário presente',
                        'ambulante' => 'Jailson Pereira dos Santos — permissão 2018/041.887, regular',
                        'equipamento' => 'Barraca de chapa, com puxada de madeira nos fundos',
                        'relato' => 'Puxada de madeira de aproximadamente 2 m × 3 m nos fundos da barraca, '
                            .'usada como depósito de bebidas e botijão, fora do padrão autorizado. '
                            .'Área de mesas avançando sobre a passagem de acesso à areia, que ficou '
                            .'com pouco mais de um metro. Permissionário presente, com permissão '
                            .'regular e DAM do exercício quitado, apresentado no local.',
                        'fotos' => [
                            'vistoria-puxada-fundos.jpg',
                            'vistoria-deposito-botijao.jpg',
                            'vistoria-acesso-areia.jpg',
                        ],
                        'gps' => '-12.9821, -38.4306',
                        'precisao_m' => 6,
                    ],
                ],
                [
                    'ha_horas' => 31,
                    'quem' => 'fiscal',
                    'o_que' => 'Notificação Preliminar emitida',
                    'detalhe' => 'Notificação lavrada e via entregue ao permissionário, com prazo de 5 dias '
                        .'para retirar a puxada e recuar as mesas.',
                    'situacao' => 'Aguardando regularização',
                    'desfecho' => 'Notificação Preliminar emitida',
                    'documento' => [
                        'tipo' => 'np',
                        'numero' => '194903',
                        'notificado' => 'Jailson Pereira dos Santos',
                        'endereco' => 'Avenida Otávio Mangabeira, 2140 — Costa Azul',
                        'inscricao' => '2018/041.887',
                        'atividade' => 'Barraca de Chapa',
                        'local' => 'Faixa de areia da Avenida Otávio Mangabeira, altura do nº 2140 — Costa Azul',
                        'equipamento' => 'Barraca 12',
                        'motivos' => ['puxada', 'padrao', 'mesas'],
                        'sancoes' => ['autuacao', 'apreensao'],
                        'prazo' => '5d',
                        'assinaturas' => [
                            ['rotulo' => 'Notificado', 'estado' => 'assinada'],
                            ['rotulo' => '1ª testemunha', 'estado' => 'assinada', 'nome' => 'Marivalda Souza Lima'],
                            ['rotulo' => '2ª testemunha', 'estado' => 'assinada', 'nome' => 'Edson Ribeiro Costa'],
                        ],
                    ],
                ],
            ],
        ],

        [
            'id' => 30,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-889120',
            'recebida_ha_horas' => 340,
            'prazo_em_dias' => -4,
            'anonima' => false,
            'requerente' => 'Cleide Santana de Oliveira',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 98330-7715',
            'assunto' => 'Mesas e som em ponto de praia depois do horário',
            'relato' => 'Moradora informa que o ponto mantém mesas na calçada e caixa de som ligada bem '
                .'depois do horário autorizado, e que já reclamou com o vendedor sem resultado. '
                .'Deu o endereço e o horário: das 18h até depois da meia-noite.',
            'logradouro' => 'Rua Doutor Nestor Duarte',
            'numero' => '96',
            'referencia' => 'esquina com a orla, perto do posto salva-vidas',
            'bairro' => 'Itapuã',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4429',
            'categoria' => 'Perturbação do sossego',
            'anexos' => [],
            /*
             * ── CASO AVANÇADO: RETORNO VENCIDO, SITUAÇÃO MANTIDA ──────────────
             *
             * O prazo da notificação venceu, a equipe voltou e o ponto continuava
             * igual. A denúncia NÃO se conclui aqui: ela volta ao gestor para a
             * próxima medida (apreensão), e é esse o significado da situação
             * "Retorno vencido". Concluir neste ponto esconderia a única coisa que
             * a tela precisa cobrar — que alguém decida o próximo passo.
             */
            'situacao' => 'Retorno vencido',
            'area' => 'Área 5',
            'equipe' => 'C1',
            'tramites' => [
                ['ha_horas' => 0, 'quem' => 'integracao'],
                [
                    'ha_horas' => 6,
                    'quem' => 'administrativo',
                    'o_que' => 'Triada e encaminhada à área',
                    'detalhe' => 'Encaminhada à Área 5 para direcionamento do gestor.',
                    'situacao' => 'Encaminhada à área',
                    'campos' => [
                        ['rotulo' => 'Bairro que sugeriu a área', 'valor' => 'Itapuã'],
                        ['rotulo' => 'Área de destino', 'valor' => 'Área 5 — Boca do Rio'],
                        ['rotulo' => 'Orientação ao gestor', 'valor' => 'Reclamação reiterada; a moradora diz já ter falado com o vendedor.'],
                    ],
                ],
                [
                    'ha_horas' => 10,
                    'quem' => 'gestor',
                    'o_que' => 'Direcionada à equipe',
                    'detalhe' => 'Direcionada à Equipe C1 para vistoria.',
                    'situacao' => 'Direcionada à equipe',
                    'campos' => [
                        ['rotulo' => 'Saída escolhida', 'valor' => 'Vistoria dirigida à equipe da própria área'],
                        ['rotulo' => 'Orientação à equipe', 'valor' => 'Ir no fim da tarde: o horário reclamado começa às 18h.'],
                    ],
                ],
                [
                    'ha_horas' => 34,
                    'quem' => 'fiscal',
                    'o_que' => 'Vistoria em campo',
                    'detalhe' => 'A equipe encontrou as mesas na calçada e o som ligado, com o ocupante presente.',
                    'situacao' => 'Em campo',
                    'campo' => [
                        'encontrado' => 'Ponto irregular, com o ocupante presente',
                        'ambulante' => 'Roberto Cerqueira da Paixão — permissão 2011/007.412, regular',
                        'equipamento' => 'Barraca de chapa, com mesas e cadeiras na calçada',
                        'relato' => 'Seis mesas e vinte e quatro cadeiras dispostas na calçada, sem Alvará '
                            .'para colocação de mesas e cadeiras em logradouro público. Caixa de som '
                            .'de grande porte ligada no equipamento. Ocupante presente, orientado '
                            .'quanto ao horário autorizado e à necessidade do alvará.',
                        'fotos' => ['vistoria-mesas-calcada.jpg', 'vistoria-som-barraca.jpg'],
                        'gps' => '-12.9506, -38.3608',
                        'precisao_m' => 9,
                    ],
                ],
                [
                    'ha_horas' => 35,
                    'quem' => 'fiscal',
                    'o_que' => 'Notificação Preliminar emitida',
                    'detalhe' => 'Notificação lavrada com prazo de 48 horas para retirar as mesas e apresentar o alvará.',
                    'situacao' => 'Aguardando regularização',
                    'desfecho' => 'Notificação Preliminar emitida',
                    'documento' => [
                        'tipo' => 'np',
                        'numero' => '194902',
                        'notificado' => 'Roberto Cerqueira da Paixão',
                        'endereco' => 'Rua Doutor Nestor Duarte, 96 — Itapuã',
                        'inscricao' => '2011/007.412',
                        'atividade' => 'Barraca de Chapa',
                        'local' => 'Calçada da Rua Doutor Nestor Duarte, esquina com a orla — Itapuã',
                        'equipamento' => 'Barraca 04',
                        'motivos' => ['mesas', 'alvara-mesas', 'horario'],
                        'sancoes' => ['autuacao', 'apreensao', 'multa'],
                        'prazo' => '48h',
                        // Recusar assinar é fato jurídico corriqueiro, e o
                        // documento registra a recusa — não a esconde. É o caso
                        // que explica por que a leitura mostra o estado de cada
                        // assinatura em vez de só o nome.
                        'assinaturas' => [
                            ['rotulo' => 'Notificado', 'estado' => 'recusada'],
                            ['rotulo' => '1ª testemunha', 'estado' => 'assinada', 'nome' => 'Ana Paula Trindade'],
                            ['rotulo' => '2ª testemunha', 'estado' => 'pendente'],
                        ],
                    ],
                ],
                [
                    'ha_horas' => 130,
                    'quem' => 'fiscal2',
                    'o_que' => 'Retorno de fiscalização — situação mantida',
                    'detalhe' => 'Prazo vencido e ponto na mesma situação. A denúncia volta ao gestor da área '
                        .'para a próxima medida.',
                    'situacao' => 'Retorno vencido',
                    'desfecho' => 'Retorno com a situação mantida',
                    'campo' => [
                        'encontrado' => 'Ponto na mesma situação, com o ocupante presente',
                        'relato' => 'Retorno após o vencimento das 48 horas. As mesas continuam na calçada, '
                            .'no mesmo número, e o alvará não foi apresentado. O ocupante informou '
                            .'que "não vai tirar". Registrada a reincidência para instruir a medida '
                            .'seguinte.',
                        'fotos' => ['retorno-mesas-mantidas.jpg', 'retorno-panorama.jpg'],
                        'gps' => '-12.9507, -38.3607',
                        'precisao_m' => 8,
                    ],
                    'campos' => [
                        ['rotulo' => 'Documento de referência', 'valor' => 'Notificação Preliminar nº 194902'],
                        ['rotulo' => 'Próxima medida sugerida pela equipe', 'valor' => 'Apreensão das mesas e cadeiras, com apoio da guarda'],
                    ],
                ],
            ],
        ],

        [
            'id' => 31,
            'canal' => 'e-salvador',
            'protocolo_origem' => 'ESL-2026-114865',
            'recebida_ha_horas' => 220,
            'prazo_em_dias' => -1,
            'anonima' => false,
            'requerente' => 'Ubirajara Lopes de Andrade',
            'documento' => '380.114.652-07',
            'email' => 'ubirajara.andrade@exemplo.com.br',
            'telefone' => '(71) 99177-2280',
            'assunto' => 'Venda de bebida em ponto improvisado na orla',
            'relato' => 'Todos os fins de semana aparece um ponto improvisado com isopor e caixa de som na '
                .'calçada da orla, vendendo bebida até de madrugada.',
            'logradouro' => 'Rua João Gomes',
            'numero' => '415',
            'referencia' => 'calçada da orla, altura da praça',
            'bairro' => 'Amaralina',
            'endereco_impreciso' => false,
            'anexos' => ['foto-ponto-improvisado.jpg'],
            /*
             * ── CASO AVANÇADO: NADA ENCONTRADO NO LOCAL ───────────────────────
             *
             * Denúncia procedente na origem e improcedente na vistoria: o ponto
             * era de fim de semana, e a equipe foi num dia útil. Registrar "nada
             * encontrado" com a data e a hora da ida é o que permite ao gestor
             * mandar de novo no dia certo em vez de arquivar por engano — e é por
             * isso que o desfecho é de lista, não texto livre.
             */
            'situacao' => 'Concluída',
            'area' => 'Área 3',
            'equipe' => 'A2',
            'tramites' => [
                ['ha_horas' => 0, 'quem' => 'integracao'],
                [
                    'ha_horas' => 5,
                    'quem' => 'administrativo',
                    'o_que' => 'Triada e encaminhada à área',
                    'detalhe' => 'Encaminhada à Área 3 para direcionamento do gestor.',
                    'situacao' => 'Encaminhada à área',
                    'campos' => [
                        ['rotulo' => 'Bairro que sugeriu a área', 'valor' => 'Amaralina'],
                        ['rotulo' => 'Área de destino', 'valor' => 'Área 3 — Brotas'],
                        ['rotulo' => 'Orientação ao gestor', 'valor' => 'O cidadão diz que o ponto só aparece em fim de semana.'],
                    ],
                ],
                [
                    'ha_horas' => 8,
                    'quem' => 'gestor',
                    'o_que' => 'Direcionada à equipe',
                    'detalhe' => 'Direcionada à Equipe A2 para vistoria.',
                    'situacao' => 'Direcionada à equipe',
                    'campos' => [
                        ['rotulo' => 'Saída escolhida', 'valor' => 'Vistoria dirigida à equipe da própria área'],
                    ],
                ],
                [
                    'ha_horas' => 28,
                    'quem' => 'fiscal',
                    'o_que' => 'Vistoria em campo',
                    'detalhe' => 'A equipe esteve no endereço e não encontrou ponto de venda algum.',
                    'situacao' => 'Em campo',
                    'campo' => [
                        'encontrado' => 'Nada encontrado no local',
                        'relato' => 'Calçada livre no endereço indicado, sem equipamento, isopor ou mercadoria. '
                            .'Nenhum vestígio de ocupação recente. Comerciantes do entorno confirmaram '
                            .'que o ponto aparece apenas em sábados e domingos, à noite.',
                        'fotos' => ['vistoria-calcada-livre.jpg'],
                        'gps' => '-13.0086, -38.4581',
                        'precisao_m' => 12,
                    ],
                ],
                [
                    'ha_horas' => 29,
                    'quem' => 'fiscal',
                    'o_que' => 'Concluída — nada encontrado',
                    'detalhe' => 'Vistoria encerrada sem constatação. A equipe registrou que o ponto é de fim '
                        .'de semana, para nova ida no dia e horário indicados.',
                    'situacao' => 'Concluída',
                    'desfecho' => 'Nada encontrado no local',
                    'campos' => [
                        ['rotulo' => 'Documento lavrado', 'valor' => 'Nenhum — não houve constatação'],
                        ['rotulo' => 'Recomendação da equipe', 'valor' => 'Reprogramar para sábado à noite, com a equipe Noturna'],
                    ],
                ],
            ],
        ],

        [
            'id' => 32,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-888820',
            'recebida_ha_horas' => 400,
            'prazo_em_dias' => -9,
            'anonima' => true,
            'requerente' => null,
            'documento' => null,
            'email' => null,
            'telefone' => null,
            'assunto' => 'Mesas de bar ocupando a calçada inteira',
            'relato' => 'Denúncia anônima de que o bar espalha mesas por toda a calçada em frente, e quem '
                .'passa com carrinho de bebê precisa descer para a rua. Informou a rua e o número.',
            'logradouro' => 'Rua Silveira Martins',
            'numero' => '1740',
            'referencia' => 'em frente à parada de ônibus',
            'bairro' => 'Cabula',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4415',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            /*
             * ── CASO AVANÇADO: A DENÚNCIA DE PONTA A PONTA ───────────────────
             *
             * O ciclo inteiro numa linha só: integração › triagem › gestor ›
             * vistoria › notificação › retorno › conclusão. É a que o dono usa
             * para percorrer a vida completa do registro, e a que prova que o
             * caminho educativo FUNCIONA — o notificado cumpriu, e nenhuma
             * apreensão foi necessária.
             */
            'situacao' => 'Concluída',
            'area' => 'Área 3',
            'equipe' => 'A2',
            'tramites' => [
                ['ha_horas' => 0, 'quem' => 'integracao'],
                [
                    'ha_horas' => 7,
                    'quem' => 'administrativo',
                    'o_que' => 'Triada e encaminhada à área',
                    'detalhe' => 'Encaminhada à Área 3 para direcionamento do gestor.',
                    'situacao' => 'Encaminhada à área',
                    'campos' => [
                        ['rotulo' => 'Bairro que sugeriu a área', 'valor' => 'Cabula'],
                        ['rotulo' => 'Área de destino', 'valor' => 'Área 3 — Brotas'],
                        ['rotulo' => 'Observação da triagem', 'valor' => 'Anônima, mas com rua e número: dá para localizar o ponto.'],
                    ],
                ],
                [
                    'ha_horas' => 12,
                    'quem' => 'gestor',
                    'o_que' => 'Direcionada à equipe',
                    'detalhe' => 'Direcionada à Equipe A2 para vistoria.',
                    'situacao' => 'Direcionada à equipe',
                    'campos' => [
                        ['rotulo' => 'Saída escolhida', 'valor' => 'Vistoria dirigida à equipe da própria área'],
                    ],
                ],
                [
                    'ha_horas' => 32,
                    'quem' => 'fiscal',
                    'o_que' => 'Vistoria em campo',
                    'detalhe' => 'A equipe encontrou a calçada ocupada por mesas, com o responsável presente.',
                    'situacao' => 'Em campo',
                    'campo' => [
                        'encontrado' => 'Ponto irregular, com o responsável presente',
                        'ambulante' => 'Genivaldo Alves Rodrigues — responsável pelo estabelecimento',
                        'equipamento' => 'Mesas e cadeiras em logradouro público',
                        'relato' => 'Nove mesas e trinta e seis cadeiras ocupando a calçada de ponta a ponta, '
                            .'sem faixa livre para pedestre. O responsável não apresentou Alvará para '
                            .'colocação de mesas e cadeiras em logradouro público e informou que '
                            .'"sempre foi assim". Orientado quanto à faixa mínima de passagem.',
                        'fotos' => ['vistoria-calcada-mesas.jpg', 'vistoria-passagem-bloqueada.jpg'],
                        'gps' => '-12.9648, -38.4402',
                        'precisao_m' => 10,
                    ],
                ],
                [
                    'ha_horas' => 33,
                    'quem' => 'fiscal',
                    'o_que' => 'Notificação Preliminar emitida',
                    'detalhe' => 'Notificação lavrada com prazo de 72 horas para retirar as mesas e apresentar o alvará.',
                    'situacao' => 'Aguardando regularização',
                    'desfecho' => 'Notificação Preliminar emitida',
                    'documento' => [
                        'tipo' => 'np',
                        'numero' => '194901',
                        'notificado' => 'Genivaldo Alves Rodrigues',
                        'endereco' => 'Rua Silveira Martins, 1740 — Cabula',
                        'inscricao' => null,
                        'atividade' => 'Comércio Informal',
                        'local' => 'Calçada da Rua Silveira Martins, altura do nº 1740 — Cabula',
                        'equipamento' => null,
                        'motivos' => ['mesas', 'alvara-mesas'],
                        'sancoes' => ['autuacao', 'apreensao'],
                        'prazo' => '72h',
                        'assinaturas' => [
                            ['rotulo' => 'Notificado', 'estado' => 'assinada'],
                            ['rotulo' => '1ª testemunha', 'estado' => 'assinada', 'nome' => 'Jorge Luiz Menezes'],
                            ['rotulo' => '2ª testemunha', 'estado' => 'assinada', 'nome' => 'Sandra Regina Alves'],
                        ],
                    ],
                ],
                [
                    'ha_horas' => 130,
                    'quem' => 'fiscal',
                    'o_que' => 'Retorno de fiscalização — regularizado',
                    'detalhe' => 'A equipe voltou ao ponto e encontrou a calçada com a faixa de passagem livre.',
                    'situacao' => 'Em campo',
                    'campo' => [
                        'encontrado' => 'Situação regularizada',
                        'relato' => 'Retorno após o prazo da notificação. As mesas foram reduzidas a quatro, '
                            .'recuadas para junto da fachada, com faixa livre de aproximadamente 1,60 m '
                            .'para pedestre. O responsável apresentou o protocolo do pedido de Alvará '
                            .'para mesas e cadeiras, aberto no dia seguinte à notificação.',
                        'fotos' => ['retorno-faixa-livre.jpg', 'retorno-protocolo-alvara.jpg'],
                        'gps' => '-12.9649, -38.4401',
                        'precisao_m' => 9,
                    ],
                ],
                [
                    'ha_horas' => 131,
                    'quem' => 'encarregado',
                    'o_que' => 'Concluída — regularizada após notificação',
                    'detalhe' => 'Notificação cumprida no prazo de retorno. Nenhuma penalidade aplicada.',
                    'situacao' => 'Concluída',
                    'desfecho' => 'Regularizado após notificação',
                    'campos' => [
                        ['rotulo' => 'Documento de referência', 'valor' => 'Notificação Preliminar nº 194901'],
                        ['rotulo' => 'Penalidade aplicada', 'valor' => 'Nenhuma — a notificação foi cumprida'],
                        ['rotulo' => 'Pendência do notificado', 'valor' => 'Alvará de mesas e cadeiras em análise, protocolo apresentado'],
                    ],
                ],
            ],
        ],

        [
            'id' => 33,
            'canal' => 'fala-salvador',
            'protocolo_origem' => '156-2026-889090',
            'recebida_ha_horas' => 150,
            'prazo_em_dias' => 3,
            'anonima' => false,
            'requerente' => 'Hildete Moura Vasconcelos',
            'documento' => null,
            'email' => null,
            'telefone' => '(71) 3336-9021',
            'assunto' => 'Carrinho de lanche fechando a entrada da garagem',
            'relato' => 'Moradora informa que o carrinho estaciona em frente ao portão da garagem no fim da '
                .'tarde e ninguém consegue entrar nem sair com o carro. Deu o endereço e disse que '
                .'já conversou com o vendedor.',
            'logradouro' => 'Rua Territorial',
            'numero' => '54',
            'referencia' => 'em frente ao portão da garagem do prédio',
            'bairro' => 'Barris',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4404',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            /*
             * ── CASO AVANÇADO: O CAMINHO COMUM, RESOLVIDO NA CONVERSA ────────
             *
             * A fiscalização é educativa antes de punitiva, e este é o caso que
             * mostra isso sem documento nenhum: o fiscal pede para deslocar, o
             * ambulante desloca, e acabou. Uma amostra em que todos os casos de
             * campo terminassem em papel desenharia um sistema punitivo que não é
             * o do cliente.
             */
            'situacao' => 'Concluída',
            'area' => 'Área 1',
            'equipe' => 'C2',
            'tramites' => [
                ['ha_horas' => 0, 'quem' => 'integracao'],
                [
                    'ha_horas' => 4,
                    'quem' => 'administrativo',
                    'o_que' => 'Triada e encaminhada à área',
                    'detalhe' => 'Encaminhada à Área 1 para direcionamento do gestor.',
                    'situacao' => 'Encaminhada à área',
                    'campos' => [
                        ['rotulo' => 'Bairro que sugeriu a área', 'valor' => 'Barris'],
                        ['rotulo' => 'Área de destino', 'valor' => 'Área 1 — Centro'],
                        ['rotulo' => 'Orientação ao gestor', 'valor' => 'Acesso de garagem bloqueado; a moradora já falou com o vendedor.'],
                    ],
                ],
                [
                    'ha_horas' => 8,
                    'quem' => 'gestor',
                    'o_que' => 'Direcionada à equipe',
                    'detalhe' => 'Direcionada à Equipe C2 para vistoria.',
                    'situacao' => 'Direcionada à equipe',
                    'campos' => [
                        ['rotulo' => 'Saída escolhida', 'valor' => 'Vistoria dirigida à equipe da própria área'],
                        ['rotulo' => 'Orientação à equipe', 'valor' => 'Ir depois das 17h, quando o carrinho monta.'],
                    ],
                ],
                [
                    'ha_horas' => 26,
                    'quem' => 'fiscal',
                    'o_que' => 'Vistoria em campo',
                    'detalhe' => 'A equipe encontrou o carrinho em frente ao portão, com o ambulante presente.',
                    'situacao' => 'Em campo',
                    'campo' => [
                        'encontrado' => 'Ponto irregular, com o ambulante presente',
                        'ambulante' => 'Ademir Batista dos Reis — sem permissão apresentada',
                        'equipamento' => 'Carrinho de mão, com chapa e botijão',
                        'relato' => 'Carrinho posicionado exatamente sobre o rebaixo do portão da garagem, '
                            .'impedindo a entrada de veículos. Ambulante presente, não apresentou '
                            .'permissão e informou que costuma montar ali por causa da tomada. '
                            .'Orientado quanto ao rebaixo de garagem e à necessidade de cadastro.',
                        'fotos' => ['vistoria-carrinho-portao.jpg', 'vistoria-portao-liberado.jpg'],
                        'gps' => '-12.9862, -38.5136',
                        'precisao_m' => 13,
                    ],
                ],
                [
                    'ha_horas' => 27,
                    'quem' => 'fiscal',
                    'o_que' => 'Regularizado no local, sem documento',
                    'detalhe' => 'O carrinho foi deslocado para o outro lado da via na presença da equipe. '
                        .'Nenhum documento foi lavrado.',
                    'situacao' => 'Concluída',
                    'desfecho' => 'Regularizado no local',
                    'campos' => [
                        ['rotulo' => 'Providência do ambulante', 'valor' => 'Deslocou o carrinho para fora do rebaixo, liberando a garagem'],
                        ['rotulo' => 'Documento lavrado', 'valor' => 'Nenhum — a irregularidade cessou na presença da equipe'],
                        ['rotulo' => 'Encaminhamento', 'valor' => 'Orientado a procurar a SEMOP para cadastro; endereço e horário anotados para nova ronda'],
                    ],
                ],
            ],
        ],

    ],

];

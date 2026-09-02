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
     * As situações, na ordem do fluxo. Duas etapas com dois donos:
     *
     *   Recebida            → chegou por integração e espera a TRIAGEM (administrativo);
     *   Encaminhada à área  → triada; espera o DIRECIONAMENTO do gestor daquela área;
     *   Direcionada à equipe| Em operação → o gestor decidiu como o trabalho acontece;
     *   Em campo / Concluída→ o que vem depois, quando o aplicativo do fiscal estiver ligado;
     *   Devolvida / Arquivada → as duas saídas da triagem, sempre com justificativa.
     */
    'situacoes' => [
        'Recebida',
        'Encaminhada à área',
        'Direcionada à equipe',
        'Em operação',
        'Em campo',
        'Concluída',
        'Devolvida',
        'Arquivada',
    ],

    /*
     * Por que uma denúncia é devolvida ao canal ou arquivada na triagem. A
     * escolha é de lista para o relatório poder somar por motivo; o texto livre
     * continua obrigatório ao lado, porque o motivo genérico não conta o caso.
     */
    'motivos_de_devolucao' => [
        'Endereço insuficiente para localizar o ponto',
        'Fora da competência da SEMOP',
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
            'situacao' => 'Recebida',
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
            'situacao' => 'Concluída',
            'area' => 'Área 1',
            'equipe' => 'C2',
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
            'motivo' => 'Fora da competência da SEMOP',
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
            'situacao' => 'Recebida',
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
            'bairro' => 'Tancredo Neves',
            'endereco_impreciso' => false,
            'atendente' => 'Central 156 — atendente 4433',
            'categoria' => 'Ocupação irregular de logradouro',
            'anexos' => [],
            'situacao' => 'Recebida',
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
            'situacao' => 'Concluída',
            'area' => 'Área 4',
            'equipe' => 'B2',
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

    ],

];

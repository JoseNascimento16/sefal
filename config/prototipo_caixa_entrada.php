<?php

/*
|--------------------------------------------------------------------------
| PROTÓTIPO — Caixa de Entrada do Administrativo
|--------------------------------------------------------------------------
|
| ⚠️ DADO DE PROTÓTIPO. A tela existe para o dono olhar o fluxo e aprovar a
| forma antes de virar tabela, migration e regra. Nada aqui é gravado em banco:
| a tela lê daqui e guarda o que a pessoa faz na SESSÃO
| (ver `App\Support\Prototipo\CaixaDeEntradaFicticia`).
|
| ── De onde a demanda vem (reunião com o cliente, 02/09/2026) ───────────────
|
| Hoje ela chega em PAPEL: o e-Salvador e o Fala Salvador (Disque 156) entregam
| documento impresso ao administrativo, que digita, decide o destino e encaminha
| à equipe da ÁREA do bairro. O cadastro manual é requisito, não gambiarra — a
| adaptação para API vem depois, e o papel não desaparece por decreto.
|
| ── As duas decisões do administrativo ──────────────────────────────────────
|
|   Registrada e atendida  → encaminhada à equipe responsável (derivada do
|                            BAIRRO, confirmada por quem registra);
|   Registrada e retornada → devolvida ao remetente ou arquivada, com
|                            JUSTIFICATIVA obrigatória — é ato administrativo:
|                            quem, quando, por quê.
|
| ── As datas são RELATIVAS de propósito ─────────────────────────────────────
|
| `dias_atras` e `prazo_em_dias` viram data na hora de servir a tela. Datas fixas
| envelhecem: uma semana depois da demonstração, a caixa apareceria inteira com
| prazo vencido e o dono leria isso como comportamento do sistema.
|
*/

return [

    /*
     * As origens que o formulário oferece. Lista fechada: é de onde o documento
     * veio, não texto livre — e é ela que separa o que um dia chega por API
     * (e-Salvador, 156) do que é interno (ofício).
     */
    'origens' => [
        'e-Salvador',
        'Fala Salvador',
        'Nova licença',
        'Ofício',
    ],

    /*
     * As situações da demanda, na ordem do trâmite. É o catálogo que a tela
     * mostra e o servidor valida — escrito nos dois lugares, um dia divergiriam.
     */
    'situacoes' => [
        'Aguardando triagem',
        'Encaminhada',
        'Devolvida',
        'Arquivada',
    ],

    /*
     * Por que uma demanda volta ou é arquivada. A escolha é de lista para o
     * relatório poder somar por motivo; o texto livre continua obrigatório ao
     * lado dela, porque o motivo genérico não conta o caso.
     */
    'motivos_de_devolucao' => [
        'Endereço insuficiente para localizar o ponto',
        'Fora da competência da SEMOP',
        'Demanda duplicada — já existe registro do mesmo fato',
        'Objeto já regularizado',
        'Pedido de licença sem a documentação exigida',
        'Denúncia sem elementos mínimos para vistoria',
    ],

    /*
     * Para onde a demanda vai quando não é atendida.
     */
    'destinos_de_retorno' => [
        'Devolvida ao remetente',
        'Arquivada',
    ],

    /*
     * O prazo padrão de atendimento, em dias, quando o formulário não informa
     * outro. Número de protótipo — o prazo real de cada canal é pergunta aberta
     * ao cliente.
     */
    'prazo_padrao_em_dias' => 10,

    /*
     * A caixa como ela chega ao administrativo numa manhã comum. Cada linha é um
     * caso que o dono reconhece — inclusive os que não dão certo.
     */
    'demandas' => [

        [
            'id' => 1,
            'protocolo' => 'CXE-0001',
            'origem' => 'Fala Salvador',
            'documento_origem' => '156-2026-884120',
            'dias_atras' => 1,
            'prazo_em_dias' => 9,
            'anonima' => true,
            'requerente' => null,
            'contato' => null,
            'assunto' => 'Barracas ocupando a calçada em frente à feira',
            'endereco' => 'Rua Barão de Mauá, altura do nº 120',
            'bairro' => 'Arenoso',
            'descricao' => 'Denunciante relata quatro barracas fixas fechando a passagem de pedestres '
                .'na altura da feira, com mesas e cadeiras na via. Pede vistoria no período da manhã.',
            'anexo' => 'denuncia-156-884120.pdf',
            'situacao' => 'Aguardando triagem',
            'equipe' => null,
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
        ],

        [
            'id' => 2,
            'protocolo' => 'CXE-0002',
            'origem' => 'e-Salvador',
            'documento_origem' => 'ESALV-2026-31877',
            'dias_atras' => 2,
            'prazo_em_dias' => 8,
            'anonima' => false,
            'requerente' => 'Marilene Souza dos Santos',
            'contato' => '(71) 98812-4477',
            'assunto' => 'Som alto em equipamento de bebidas após as 22h',
            'endereco' => 'Rua Manoel Ferreira, 45',
            'bairro' => 'Pituba',
            'descricao' => 'Moradora informa que o equipamento mantém venda de bebida alcoólica e som '
                .'ligado depois do horário autorizado, de quinta a domingo.',
            'anexo' => 'esalvador-31877.pdf',
            'situacao' => 'Aguardando triagem',
            'equipe' => null,
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
        ],

        [
            'id' => 3,
            'protocolo' => 'CXE-0003',
            'origem' => 'Nova licença',
            'documento_origem' => 'PROC-2026-004512',
            'dias_atras' => 4,
            'prazo_em_dias' => 6,
            'anonima' => false,
            'requerente' => 'Edvaldo Nascimento Lima',
            'contato' => '(71) 99140-2288',
            'assunto' => 'Pedido de nova licença para carrinho de lanches',
            'endereco' => 'Avenida Sete de Setembro, em frente ao nº 908',
            'bairro' => 'Avenida Sete de Setembro',
            'descricao' => 'Requerente pede permissão para carrinho de lanches em ponto de grande '
                .'circulação. Precisa de vistoria do local para conferir recuo e fluxo de pedestres.',
            'anexo' => 'processo-004512.pdf',
            'situacao' => 'Encaminhada',
            'equipe' => 'I1',
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
        ],

        [
            'id' => 4,
            'protocolo' => 'CXE-0004',
            'origem' => 'e-Salvador',
            'documento_origem' => 'ESALV-2026-31644',
            'dias_atras' => 6,
            'prazo_em_dias' => 4,
            'anonima' => true,
            'requerente' => null,
            'contato' => null,
            'assunto' => 'Ambulante vendendo em cima da faixa de travessia',
            'endereco' => 'Largo do Tanque, próximo ao terminal',
            'bairro' => 'Largo do Tanque',
            'descricao' => 'Relato de venda de água e refrigerante sobre a faixa de travessia, com risco '
                .'para pedestres no horário de pico.',
            'anexo' => 'esalvador-31644.pdf',
            'situacao' => 'Encaminhada',
            'equipe' => 'B2',
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
        ],

        [
            'id' => 5,
            'protocolo' => 'CXE-0005',
            'origem' => 'Fala Salvador',
            'documento_origem' => '156-2026-880913',
            'dias_atras' => 9,
            'prazo_em_dias' => 1,
            'anonima' => true,
            'requerente' => null,
            'contato' => null,
            'assunto' => 'Reclamação sobre feira livre — sem endereço',
            'endereco' => 'Não informado',
            'bairro' => 'Mussurunga',
            'descricao' => 'Denunciante desligou antes de informar a rua. O atendimento registrou apenas '
                .'"perto da feira", o que não localiza o ponto.',
            'anexo' => 'denuncia-156-880913.pdf',
            'situacao' => 'Devolvida',
            'equipe' => null,
            'motivo' => 'Endereço insuficiente para localizar o ponto',
            'justificativa' => 'O registro do 156 não traz rua nem ponto de referência que permita à equipe '
                .'chegar ao local. Devolvido ao canal de origem para complementar o endereço.',
            'destino' => 'Devolvida ao remetente',
        ],

        [
            'id' => 6,
            'protocolo' => 'CXE-0006',
            'origem' => 'Ofício',
            'documento_origem' => 'OF-SEMOP-2026-771',
            'dias_atras' => 12,
            'prazo_em_dias' => -2,
            'anonima' => false,
            'requerente' => 'Associação de Moradores da Ribeira',
            'contato' => 'assoc.ribeira@exemplo.org',
            'assunto' => 'Pedido de fiscalização periódica na orla',
            'endereco' => 'Largo da Ribeira, toda a extensão da orla',
            'bairro' => 'Ribeira',
            'descricao' => 'Ofício pede rondas semanais no fim de semana por conta do aumento de barracas '
                .'em período de temporada.',
            'anexo' => 'oficio-771.pdf',
            'situacao' => 'Encaminhada',
            'equipe' => 'A1',
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
        ],

        [
            'id' => 7,
            'protocolo' => 'CXE-0007',
            'origem' => 'e-Salvador',
            'documento_origem' => 'ESALV-2026-30901',
            'dias_atras' => 16,
            'prazo_em_dias' => -6,
            'anonima' => false,
            'requerente' => 'Roberto Carlos Menezes',
            'contato' => '(71) 98455-6612',
            'assunto' => 'Buraco na via em frente ao ponto de ambulante',
            'endereco' => 'Rua Silveira Martins, 1.240',
            'bairro' => 'Cabula',
            'descricao' => 'Reclamação sobre pavimento. A demanda não trata de comércio ambulante — o '
                .'objeto é conservação de via.',
            'anexo' => 'esalvador-30901.pdf',
            'situacao' => 'Arquivada',
            'equipe' => null,
            'motivo' => 'Fora da competência da SEMOP',
            'justificativa' => 'O objeto é conservação de pavimento, atribuição de outro órgão. Arquivada '
                .'nesta caixa e comunicada ao requerente pelo próprio canal.',
            'destino' => 'Arquivada',
        ],

        [
            'id' => 8,
            'protocolo' => 'CXE-0008',
            'origem' => 'Nova licença',
            'documento_origem' => 'PROC-2026-004380',
            'dias_atras' => 3,
            'prazo_em_dias' => 7,
            'anonima' => false,
            'requerente' => 'Josefa Bispo de Oliveira',
            'contato' => '(71) 99677-1030',
            'assunto' => 'Pedido de nova licença para venda de acarajé',
            'endereco' => 'Praça Tiradentes, esquina com a Rua da Poeira',
            'bairro' => 'Comércio',
            'descricao' => 'Requerente já atua no ponto e pede a regularização. Solicita vistoria para '
                .'medição do espaço e conferência do equipamento.',
            'anexo' => 'processo-004380.pdf',
            'situacao' => 'Aguardando triagem',
            'equipe' => null,
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
        ],

        [
            'id' => 9,
            'protocolo' => 'CXE-0009',
            'origem' => 'Fala Salvador',
            'documento_origem' => '156-2026-885201',
            'dias_atras' => 0,
            'prazo_em_dias' => 10,
            'anonima' => true,
            'requerente' => null,
            'contato' => null,
            'assunto' => 'Venda de bebida em equipamento sem alvará à noite',
            'endereco' => 'Rua Território do Amapá, 78',
            'bairro' => 'Tancredo Neves',
            'descricao' => 'Denúncia de funcionamento noturno com mesas na calçada e venda de bebida '
                .'alcoólica sem autorização. Pede vistoria depois das 21h.',
            'anexo' => 'denuncia-156-885201.pdf',
            'situacao' => 'Aguardando triagem',
            'equipe' => null,
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
        ],

        [
            'id' => 10,
            'protocolo' => 'CXE-0010',
            'origem' => 'e-Salvador',
            'documento_origem' => 'ESALV-2026-31990',
            'dias_atras' => 0,
            'prazo_em_dias' => 10,
            'anonima' => false,
            'requerente' => 'Cleber Andrade Rios',
            'contato' => '(71) 98120-7745',
            'assunto' => 'Equipamento cedido a terceiro',
            'endereco' => 'Avenida Cardeal da Silva, 622',
            'bairro' => 'Federação',
            'descricao' => 'Requerente informa que o ambulante do ponto não trabalha mais no local e '
                .'cedeu o equipamento a outra pessoa.',
            'anexo' => 'esalvador-31990.pdf',
            'situacao' => 'Aguardando triagem',
            'equipe' => null,
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
        ],

    ],

];

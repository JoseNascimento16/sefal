/* ============================================================================
   PROTÓTIPO — a estrutura da SEFAL: Área > Equipe > bloco de bairros
   ----------------------------------------------------------------------------
   Este módulo é o ESPELHO, no aplicativo de campo, da mesma parametrização que
   a Retaguarda já tem em `config/prototipo_estrutura.php` (tela Áreas e
   Equipes). Áreas, códigos de equipe, encarregados e as listas de bairros são
   REAIS — vêm do documento do cliente "ÁREAS DAS EQUIPES ATUALIZADA -
   17/04/2026". Os fiscais de campo (nome e matrícula) são inventados: o
   documento nomeia só o encarregado.

   ⚠️ DUAS CÓPIAS DA MESMA COISA — e a razão de existirem. A lei da fonte única
   diz que a mesma informação com dois donos sempre diverge. Aqui, hoje, o
   aplicativo de campo é um protótipo que roda SEM servidor: não há endpoint que
   entregue a estrutura, então ela é escrita no navegador. Quando o aplicativo
   passar a falar com o servidor, este arquivo morre e a estrutura passa a
   chegar do mesmo lugar que a Retaguarda lê. Até lá, ao mexer numa das duas
   cópias, mexa na outra — os nomes de campo foram mantidos iguais de propósito
   (`equipe`, `area`, `encarregado`, `recorte`, `turno`, `bairros`) para o
   de-para ser mecânico.

   ── Por que a EQUIPE é a identidade que importa ─────────────────────────────

   A demanda do Coordenador não chega para o fiscal: chega para a EQUIPE da
   área onde fica o endereço. Logo, quem define a fila do aplicativo é a equipe
   do fiscal que entrou — e é por isso que a matrícula digitada na porta decide
   o que a tela mostra.
   ============================================================================ */

/**
 * Como a área recorta a cidade — são TRÊS formas, não uma.
 *
 * Tratar as oito áreas como "bloco de bairros" faria a Noturna aparecer com
 * zero bairros, que é a leitura invertida: ela cobre todos. O nome dos valores
 * é o mesmo da parametrização da Retaguarda.
 */
export type Recorte = 'bairros' | 'corredores' | 'cidade';

/** Um anel de coordenadas — o contorno desenhado no mapa. */
export type Anel = [lat: number, lng: number][];

export type AreaSefal = {
    id: number;
    /** "Área 5", "Itinerante", "Noturna". */
    area: string;
    /** A região que dá nome à área: "Boca do Rio", "Centro". */
    regiao: string;
    /** O código da equipe: C1, B2, I1… */
    equipe: string;
    encarregado: string;
    recorte: Recorte;
    turno: string;
    bairros: string[];
    /**
     * O contorno da área no mapa, APROXIMADO e desenhado à mão.
     *
     * Não é limite oficial de bairro: é o recorte que o fiscal precisa para
     * saber onde termina o trabalho dele e começa o do vizinho. `corredores`
     * traz linhas em vez de anel (a equipe percorre eixos, não um bloco), e a
     * Noturna não traz geometria nenhuma — cobertura é a cidade inteira.
     */
    contorno: Anel | null;
    corredores: { nome: string; linha: Anel }[] | null;
};

export const AREAS_SEFAL: AreaSefal[] = [
    {
        id: 1,
        area: 'Área 1',
        regiao: 'Centro',
        equipe: 'C2',
        encarregado: 'José Roberto',
        recorte: 'bairros',
        turno: 'Diurno',
        bairros: [
            'Alto das Pombas', 'Barbalho', 'Barra', 'Barris', 'Calabar', 'Canela',
            'Centro Histórico', 'Chame-Chame', 'Comércio', 'Dois de Julho',
            'Engenho Velho da Federação', 'Federação', 'Garcia', 'Graça', 'Macaúbas',
            'Mares', 'Nazaré', 'Ondina', 'Rio Vermelho', 'Santo Agostinho', 'Saúde',
            'Tororó', 'Vasco da Gama', 'Vitória',
        ],
        contorno: [
            [-13.0108, -38.5342], [-13.0062, -38.5128], [-13.0088, -38.4902],
            [-12.9996, -38.4928], [-12.9878, -38.5038], [-12.9798, -38.5082],
            [-12.9718, -38.5058], [-12.9698, -38.5132], [-12.9792, -38.5222],
            [-12.9932, -38.5232], [-13.0042, -38.5288],
        ],
        corredores: null,
    },
    {
        id: 2,
        area: 'Área 2',
        regiao: 'Itapagipe',
        equipe: 'A1',
        encarregado: 'Marco Gonçalves',
        recorte: 'bairros',
        turno: 'Diurno',
        bairros: [
            'Alto da Terezinha', 'Alto do Cabrito', 'Boa Viagem', 'Bonfim', 'Calçada',
            'Caminho de Areia', 'Colinas de Periperi', 'Coutos', 'Fazenda Coutos',
            'Ilha Amarela', 'Itacaranha', 'Lobato', 'Mangueira', 'Massaranduba',
            'Mirantes', 'Monte Serrat', 'Paripe', 'Periperi', 'Plataforma',
            'Praia Grande', 'Ribeira', 'Roma', 'Santa Luzia', 'São João do Cabrito',
            'São Tomé', 'Uruguai', 'Vila Rui Barbosa', 'Vista Alegre',
        ],
        contorno: [
            [-12.9432, -38.5112], [-12.9252, -38.4962], [-12.9102, -38.4812],
            [-12.8952, -38.4722], [-12.8722, -38.4602], [-12.8462, -38.4532],
            [-12.8422, -38.4652], [-12.8722, -38.4742], [-12.8992, -38.4882],
            [-12.9212, -38.5072], [-12.9352, -38.5162],
        ],
        corredores: null,
    },
    {
        id: 3,
        area: 'Área 3',
        regiao: 'Brotas',
        equipe: 'A2',
        encarregado: 'Nonato Silva',
        recorte: 'bairros',
        turno: 'Diurno',
        bairros: [
            'Acupe', 'Amaralina', 'Arraial do Retiro', 'Barreiras', 'Boa Vista de Brotas',
            'Cabula', 'Caminho das Árvores', 'Candeal', 'Cosme de Farias', 'Daniel Lisboa',
            'Engenho Velho de Brotas', 'Engomadeira', 'Horto Florestal', 'Itaigara',
            'Luiz Anselmo', 'Matatu', 'Nordeste', 'Pernambués', 'Pituba', 'Resgate',
            'Saboeiro', 'Santa Cruz', 'São Gonçalo', 'Saramandaia', 'Vale das Pedrinhas',
            'Vila Laura',
        ],
        contorno: [
            [-13.0002, -38.4872], [-12.9928, -38.4602], [-12.9802, -38.4598],
            [-12.9648, -38.4452], [-12.9562, -38.4408], [-12.9598, -38.4702],
            [-12.9712, -38.4808], [-12.9802, -38.4952], [-12.9948, -38.4998],
        ],
        corredores: null,
    },
    {
        id: 4,
        area: 'Área 4',
        regiao: 'Liberdade',
        equipe: 'B2',
        encarregado: 'Andréa Rocha',
        recorte: 'bairros',
        turno: 'Diurno',
        bairros: [
            'Alto do Peru', 'Baixa de Quintas', 'Boa Vista de São Caetano', 'Bom Juá',
            "Caixa d'Água", 'Calabatão', 'Campinas de Pirajá', 'Capelinha', 'Cidade Nova',
            'Curuzu', 'Fazenda Grande do Retiro', 'IAPI', 'Lapinha', 'Largo do Tanque',
            'Marechal Rondon', 'Palestina', 'Pau Miúdo', 'Pero Vaz', 'Pirajá', 'Retiro',
            'San Martin', 'Santa Mônica', 'São Caetano', 'Valéria',
        ],
        contorno: [
            [-12.9432, -38.5102], [-12.9332, -38.4962], [-12.9252, -38.4722],
            [-12.9152, -38.4552], [-12.9302, -38.4402], [-12.9482, -38.4562],
            [-12.9602, -38.4782], [-12.9562, -38.5002],
        ],
        corredores: null,
    },
    {
        id: 5,
        area: 'Área 5',
        regiao: 'Boca do Rio',
        equipe: 'C1',
        encarregado: 'César Amaral',
        recorte: 'bairros',
        turno: 'Diurno',
        bairros: [
            'Aeroporto', 'Alto do Coqueirinho', 'Areia Branca', 'Bairro da Paz',
            'Cassange', 'Costa Azul', 'Imbuí', 'Itapuã', 'Itinga', 'Jardim Armação',
            'Jardim das Margaridas', 'Mussurunga', 'Patamares', 'Piatã', 'Pituaçu',
            'Praia do Flamengo', 'São Cristóvão', 'Stella Maris', 'Stiep', 'Vale dos Rios',
        ],
        /* Uma faixa ao longo da orla, do Jardim de Alah ao Aeroporto, e a volta
           por dentro — Mussurunga, Bairro da Paz, Imbuí, Stiep.
           ⚠️ Não é limite oficial de bairro: é um contorno traçado em volta dos
           bairros do bloco, com folga, e CONFERIDO contra todos os pontos que o
           protótipo desenha no mapa (25 pontos conhecidos, 12 registros do
           turno e os focos do mapa de calor caem dentro dele). Contorno mais
           justo deixaria ponto de dentro parecendo de fora, que é o erro que
           importa aqui: o fiscal deixaria de atender o que é dele. */
        contorno: [
            [-12.9917, -38.4532], [-12.9827, -38.4312], [-12.9782, -38.4202],
            [-12.9721, -38.4052], [-12.9648, -38.3872], [-12.958, -38.3702],
            [-12.9536, -38.3592], [-12.9474, -38.3442], [-12.9421, -38.3312],
            [-12.9139, -38.3427], [-12.9192, -38.3557], [-12.9253, -38.3706],
            [-12.9297, -38.3816], [-12.9366, -38.3986], [-12.9439, -38.4167],
            [-12.95, -38.4317], [-12.9545, -38.4427], [-12.9634, -38.4646],
        ],
        corredores: null,
    },
    {
        id: 6,
        area: 'Área 6',
        regiao: 'Pau da Lima',
        equipe: 'B1',
        encarregado: 'José Antonio',
        recorte: 'bairros',
        turno: 'Diurno',
        bairros: [
            'Águas Claras', 'Arenoso', 'Boca da Mata', 'CAB', 'Cabula VI',
            'Cajazeiras II a XI', 'Canabrava', 'Castelo Branco', 'Dom Avelar', 'Doron',
            'Fazenda Grande I a IV', 'Granjas Rurais', 'Jaguaripe I', 'Jardim Cajazeiras',
            'Jardim Nova Esperança', 'Mata Escura', 'Narandiba', 'Nova Brasília',
            'Nova Esperança', 'Porto Seco Pirajá', 'Santo Inácio', 'São Marcos',
            'São Rafael', 'Sete de Abril', 'Sussuarana', 'Tancredo Neves', 'Trobogy',
            'Vale dos Lagos', 'Vila Canária',
            // Os três COMPARTILHADOS com a Área 5: o vínculo bairro↔equipe não é
            // 1:1, o sistema sugere e o Coordenador confirma.
            'Jardim das Margaridas', 'Mussurunga', 'Patamares',
        ],
        contorno: [
            [-12.9302, -38.4302], [-12.9152, -38.4202], [-12.8952, -38.4102],
            [-12.8852, -38.4302], [-12.9002, -38.4502], [-12.9252, -38.4552],
            [-12.9452, -38.4402], [-12.9552, -38.4202],
        ],
        corredores: null,
    },
    {
        id: 7,
        area: 'Itinerante',
        regiao: 'Avenida Sete',
        equipe: 'I1',
        encarregado: 'Roberto Moraes',
        recorte: 'corredores',
        turno: 'Diurno',
        bairros: ['Avenida Sete de Setembro', 'Comércio', 'Avenida Joana Angélica'],
        contorno: null,
        corredores: [
            {
                nome: 'Avenida Sete de Setembro',
                linha: [
                    [-12.9862, -38.5188], [-12.9822, -38.5168], [-12.9788, -38.5152],
                    [-12.9748, -38.5132],
                ],
            },
            {
                nome: 'Comércio',
                linha: [[-12.9738, -38.5138], [-12.9698, -38.5122], [-12.9648, -38.5092]],
            },
            {
                nome: 'Avenida Joana Angélica',
                linha: [[-12.9808, -38.5088], [-12.9762, -38.5092], [-12.9718, -38.5102]],
            },
        ],
    },
    {
        id: 8,
        area: 'Noturna',
        regiao: 'Toda Salvador',
        equipe: 'N1',
        encarregado: 'Alcione Brandão',
        recorte: 'cidade',
        turno: 'Noturno',
        // Vazio de propósito: a cobertura é "todos os bairros", e repetir aqui a
        // lista de cada área diurna daria dois donos à mesma informação.
        bairros: [],
        contorno: null,
        corredores: null,
    },
];

/** O que cada recorte quer dizer na tela — rótulo e explicação, num lugar só. */
export const RECORTES: Record<Recorte, { rotulo: string; explicacao: string }> = {
    bairros: {
        rotulo: 'Bloco de bairros',
        explicacao: 'O contorno é o bloco de bairros da equipe. Fora dele, o ponto é de outra equipe.',
    },
    corredores: {
        rotulo: 'Corredores',
        explicacao: 'A equipe percorre eixos de grande circulação — por isso o mapa traça linhas, e não um bloco.',
    },
    cidade: {
        rotulo: 'Cidade inteira, por turno',
        explicacao: 'A cobertura é toda Salvador: o recorte desta equipe é o TURNO, não a geografia.',
    },
};

export const areaDaEquipe = (equipe: string): AreaSefal =>
    AREAS_SEFAL.find((a) => a.equipe === equipe) ?? AREAS_SEFAL[4];

/* ------------------------------- Os fiscais ------------------------------- */

export type FiscalSefal = {
    matricula: string;
    nome: string;
    /** Código da equipe a que ele pertence — é o que decide a fila dele. */
    equipe: string;
    papel: 'Encarregado' | 'Fiscal';
    turno: string;
    desde: string;
};

/**
 * Quem pode entrar no aplicativo, e em que equipe cada um está.
 *
 * Os encarregados são os do documento do cliente; os fiscais de campo repetem,
 * nome por nome, os que a parametrização da Retaguarda já mostra na tela de
 * Áreas e Equipes — para o dono abrir as duas telas e ver a mesma gente.
 *
 * ⚠️ A matrícula dos ENCARREGADOS é inventada (o documento só traz o nome). A do
 * `fiscal` não é matrícula: é o login curto que a demonstração usa, e ele cai no
 * César Amaral, encarregado da Equipe C1 — que é o mesmo nome do usuário
 * `fiscal` no banco da demonstração.
 */
export const FISCAIS_SEFAL: FiscalSefal[] = [
    // Encarregados
    { matricula: 'F-2500', nome: 'César Amaral', equipe: 'C1', papel: 'Encarregado', turno: 'Turno tarde', desde: '2014' },
    { matricula: 'F-2000', nome: 'José Roberto', equipe: 'C2', papel: 'Encarregado', turno: 'Turno manhã', desde: '2011' },
    { matricula: 'F-2200', nome: 'Marco Gonçalves', equipe: 'A1', papel: 'Encarregado', turno: 'Turno manhã', desde: '2012' },
    { matricula: 'F-2300', nome: 'Nonato Silva', equipe: 'A2', papel: 'Encarregado', turno: 'Turno manhã', desde: '2010' },
    { matricula: 'F-2400', nome: 'Andréa Rocha', equipe: 'B2', papel: 'Encarregado', turno: 'Turno tarde', desde: '2013' },
    { matricula: 'F-2600', nome: 'José Antonio', equipe: 'B1', papel: 'Encarregado', turno: 'Turno tarde', desde: '2009' },
    { matricula: 'F-2800', nome: 'Roberto Moraes', equipe: 'I1', papel: 'Encarregado', turno: 'Turno manhã', desde: '2015' },
    { matricula: 'F-2900', nome: 'Alcione Brandão', equipe: 'N1', papel: 'Encarregado', turno: 'Turno noite', desde: '2016' },

    // Área 5 · Equipe C1 — a equipe da demonstração
    { matricula: 'F-2504', nome: 'Aline Barbosa Fontes', equipe: 'C1', papel: 'Fiscal', turno: 'Turno tarde', desde: '2019' },
    { matricula: 'F-2529', nome: 'Renato Queiroz Bastos', equipe: 'C1', papel: 'Fiscal', turno: 'Turno manhã', desde: '2017' },
    { matricula: 'F-2558', nome: 'Iracema Duarte Lopes', equipe: 'C1', papel: 'Fiscal', turno: 'Turno tarde', desde: '2021' },
    { matricula: 'F-2571', nome: 'Tiago Marinho Cardoso', equipe: 'C1', papel: 'Fiscal', turno: 'Turno manhã', desde: '2022' },

    // As outras equipes — bastam para provar que a fila muda com quem entra
    { matricula: 'F-2041', nome: 'Adriana Melo Torres', equipe: 'C2', papel: 'Fiscal', turno: 'Turno manhã', desde: '2018' },
    { matricula: 'F-2088', nome: 'Cláudio Ferreira Lima', equipe: 'C2', papel: 'Fiscal', turno: 'Turno tarde', desde: '2016' },
    { matricula: 'F-2263', nome: 'Everton Matos da Silva', equipe: 'A1', papel: 'Fiscal', turno: 'Turno manhã', desde: '2020' },
    { matricula: 'F-2318', nome: 'Márcio Aurélio Campos', equipe: 'A2', papel: 'Fiscal', turno: 'Turno tarde', desde: '2015' },
    { matricula: 'F-2461', nome: 'Josenildo Braga Vieira', equipe: 'B2', papel: 'Fiscal', turno: 'Turno manhã', desde: '2019' },
    { matricula: 'F-2677', nome: 'Anderson Luz Sampaio', equipe: 'B1', papel: 'Fiscal', turno: 'Turno tarde', desde: '2021' },
    { matricula: 'F-2801', nome: 'Paulo Sérgio Macedo', equipe: 'I1', papel: 'Fiscal', turno: 'Turno manhã', desde: '2014' },
    { matricula: 'F-2947', nome: 'Roseane Silveira Coelho', equipe: 'N1', papel: 'Fiscal', turno: 'Turno noite', desde: '2018' },
];

/**
 * O fiscal genérico — quem entra com uma matrícula que o protótipo não conhece.
 *
 * Ele fica na **Equipe C1 · Área 5**, e não numa equipe qualquer: é o aparelho
 * da demonstração, e todo o resto do protótipo (os pontos do mapa, o histórico
 * do turno, o mapa de calor) está na Área 5. Jogá-lo em outra área faria o
 * aplicativo abrir com um mapa que não é o dele.
 */
const GENERICO: FiscalSefal = {
    matricula: 'F-40219',
    nome: 'Marcos Vinícius Andrade',
    equipe: 'C1',
    papel: 'Fiscal',
    turno: 'Turno tarde',
    desde: '2019',
};

/** "Marcos Vinícius Andrade" → "MA". */
const iniciaisDe = (nome: string): string => {
    const partes = nome.trim().split(/\s+/);
    const primeira = partes[0]?.[0] ?? 'F';
    const ultima = partes.length > 1 ? (partes[partes.length - 1][0] ?? '') : '';

    return (primeira + ultima).toUpperCase();
};

export type Identidade = {
    nome: string;
    matricula: string;
    /** O que o aplicativo diz embaixo do nome: setor, não equipe. */
    setor: string;
    papel: 'Encarregado' | 'Fiscal';
    turno: string;
    iniciais: string;
    desde: string;
};

/**
 * Quem entrou, a partir do que foi digitado na porta.
 *
 * O protótipo não tem servidor, então não há senha a conferir: o que a
 * matrícula decide é a IDENTIDADE — e, com ela, a equipe e a fila. `fiscal`
 * (o login da demonstração) e `F-2500` levam ao César Amaral; qualquer
 * matrícula desconhecida cai no fiscal genérico.
 */
export const identidadePorMatricula = (matricula: string | null): { fiscal: Identidade; area: AreaSefal } => {
    const chave = (matricula ?? '').trim().toLowerCase();

    const achado =
        chave === 'fiscal' || chave === 'cesar' || chave === 'césar'
            ? FISCAIS_SEFAL[0]
            : FISCAIS_SEFAL.find((f) => f.matricula.toLowerCase() === chave);

    const escolhido = achado ?? GENERICO;

    return {
        fiscal: {
            nome: escolhido.nome,
            matricula: escolhido.matricula,
            setor: 'SEFAL · Fiscalização em Logradouro Público',
            papel: escolhido.papel,
            turno: escolhido.turno,
            iniciais: iniciaisDe(escolhido.nome),
            desde: escolhido.desde,
        },
        area: areaDaEquipe(escolhido.equipe),
    };
};

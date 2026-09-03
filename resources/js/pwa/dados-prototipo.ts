/* ============================================================================
   PROTÓTIPO — dados fictícios do aplicativo do fiscal
   ----------------------------------------------------------------------------
   Nada aqui vem do banco: são pessoas, endereços e fiscalizações INVENTADOS,
   escritos para o dono ver as telas com a densidade real que elas terão em rua.
   Quando o aplicativo passar a falar com o servidor, este arquivo inteiro sai —
   é a fronteira do protótipo, e por isso está sozinho num módulo só.

   As COORDENADAS, essas, são reais, e todas caem na ÁREA 5 — Boca do Rio,
   Costa Azul, Jardim Armação, Stiep, Imbuí, Pituaçu, Patamares, Piatã, Itapuã,
   Stella Maris e Mussurunga. Sem isso o mapa mentiria sobre a única coisa que o
   mapa precisa acertar.

   ⚠️ POR QUE A ÁREA 5 E NÃO O CENTRO: o aparelho da demonstração é o do fiscal
   da **Equipe C1 · Área 5**, que é quem recebe as demandas encaminhadas pelo
   administrativo no roteiro combinado com o dono. Pontos conhecidos na Barra e
   no Pelourinho, como havia antes, punham no mapa dele o serviço de outra
   equipe — e a primeira pergunta do cliente seria justamente essa.
   ============================================================================ */

/* O desfecho e a leitura que dele se deriva moram no módulo das denúncias, que é
   o espelho do catálogo da Retaguarda. Repetir a lista aqui criaria a segunda
   cópia que a lei da fonte única proíbe. */
import { LOCAL_APOS_O_DESFECHO, type Desfecho } from './dados-demandas';

/**
 * Como o LOCAL ficou — a leitura curta que o mapa e a lista usam para colorir.
 *
 * ⚠️ Não confundir com a `Situacao` da denúncia (em `dados-demandas.ts`), que é
 * o catálogo do trâmite. Esta aqui é do PONTO: liberado ou com ocorrência. Na
 * fiscalização ela é DERIVADA do desfecho (ver `LOCAL_APOS_O_DESFECHO`), nunca
 * escolhida ao lado dele — a mesma coisa decidida duas vezes divergiria.
 */
export type Situacao = 'regular' | 'irregular';

export type Regiao = {
    id: string;
    nome: string;
    lat: number;
    lng: number;
};

export type EventoHistorico = {
    data: string;
    resumo: string;
    status: Situacao;
};

export type Ambulante = {
    id: string;
    nome: string;
    apelido: string;
    atividade: string;
    emoji: string;
    situacao: Situacao;
    permissao: string | null;
    regiao: string;
    endereco: string;
    lat: number;
    lng: number;
    ultimaEm: string;
    /** Retorno marcado por um fiscal para este ponto — em dias já vencidos. */
    retornoHaDias: number | null;
    historico: EventoHistorico[];
};

/**
 * De onde a fiscalização nasceu.
 *
 * `avulsa` é o fiscal andando a rua e vendo — o caminho que o protótipo já
 * encenava. `dirigida` é o trabalho que o administrativo encaminhou à equipe
 * com número de processo e prazo: aí a fiscalização nasce AMARRADA à demanda, e
 * o vínculo tem de ficar visível no registro, no recibo e no documento.
 */
export type OrigemRegistro = 'avulsa' | 'dirigida';

/**
 * A via do documento, CONGELADA no instante em que foi lavrada.
 *
 * O documento não se remonta a partir da tela na hora de imprimir: o que vale é
 * o que o notificado assinou. Por isso o texto inteiro fica guardado no
 * registro e a impressão apenas o repete — dá para tirar a segunda via dias
 * depois e sair igual à primeira.
 */
export type ViaImpressa = {
    tipo: 'np' | 'aa';
    numero: string;
    titulo: string;
    campos: { rotulo: string; valor: string }[];
    listas: { titulo: string; itens: string[] }[];
    assinaturas: { rotulo: string; estado: 'assinada' | 'recusada' | 'pendente'; nome?: string }[];
    rodape: string;
};

export type Registro = {
    id: string;
    protocolo: string;
    dataBr: string;
    hora: string;
    regiao: string;
    endereco: string;
    lat: number;
    lng: number;
    /**
     * COMO a vistoria terminou — da lista fechada que a Retaguarda soma.
     *
     * É o dado que fecha o passo do trâmite do outro lado. Texto livre aqui
     * faria o campo dizer uma coisa e a Retaguarda outra.
     */
    desfecho: Desfecho;
    /** Leitura derivada do desfecho — ponto liberado ou com ocorrência. */
    status: Situacao;
    ocorrencias: string[];
    relato: string;
    fotos: number;
    ambulante: string | null;
    retornoBr: string | null;
    envio: 'enviado' | 'pendente' | 'erro';
    documento: string | null;
    /** 'np' = Notificação Preliminar; 'aa' = Auto de Apreensão. */
    documentoTipo: 'np' | 'aa' | null;
    /** Preenchida quando o documento foi lavrado NESTA sessão do protótipo. */
    via: ViaImpressa | null;
    origem: OrigemRegistro;
    /** Preenchido só quando a origem é dirigida. */
    demandaId: string | null;
    /** O número do processo da demanda — vai para o campo REFERÊNCIA do papel. */
    referencia: string | null;
};

/* Quem está com o aparelho na mão sai do módulo de sessão: é a matrícula
   digitada na porta que decide, e um segundo lugar guardando a mesma pessoa
   divergiria dele no primeiro ajuste. */
export { FISCAL } from './sessao';

/**
 * Data BR de hoje somada de N dias.
 *
 * O "hoje" é o de verdade. Escrever as datas à mão faria o protótipo envelhecer:
 * uma semana depois da demonstração, o turno "de hoje" apareceria vazio e a fila
 * inteira vencida — e o dono leria isso como comportamento do sistema. É também
 * o que a Caixa de Entrada da Retaguarda faz, e é o que mantém as duas telas
 * mostrando a mesma data.
 */
export const dataBrDaqui = (dias: number): string => {
    const d = new Date();
    /* Meio-dia: somar dias sobre a meia-noite erra na virada do horário de
       verão, e um dia a menos numa data de prazo é um erro que aparece. */
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + dias);

    return d.toLocaleDateString('pt-BR');
};

export const HOJE_BR = dataBrDaqui(0);

/**
 * As regiões do mapa — todas dentro da Área 5, o bloco da Equipe C1.
 *
 * Não são bairros oficiais: são os pontos de referência sobre os quais os pinos
 * do protótipo se distribuem. O nome de cada uma é o do bairro do bloco.
 *
 * ⚠️ As coordenadas da orla estão interpoladas entre Itapuã e Pituaçu (os dois
 * pontos que já vinham conferidos no protótipo) e RECUADAS uns 200 m para
 * dentro da terra. O recuo não é capricho: cada pino tem um desvio próprio de
 * até 0,0026° a partir do centro da região, e com o centro sobre a linha d'água
 * metade dos pinos aparecia no mar — foi o que o mapa mostrou na conferência.
 */
export const REGIOES: Regiao[] = [
    { id: 'costa-azul', nome: 'Costa Azul', lat: -12.9796, lng: -38.4485 },
    { id: 'jardim-armacao', nome: 'Jardim Armação', lat: -12.9722, lng: -38.4302 },
    { id: 'stiep', nome: 'Stiep', lat: -12.9702, lng: -38.436 },
    { id: 'boca-do-rio', nome: 'Boca do Rio', lat: -12.9678, lng: -38.4192 },
    { id: 'imbui', nome: 'Imbuí', lat: -12.9659, lng: -38.4305 },
    { id: 'pituacu', nome: 'Pituaçu', lat: -12.9613, lng: -38.4032 },
    { id: 'patamares', nome: 'Patamares', lat: -12.956, lng: -38.3902 },
    { id: 'piata', nome: 'Piatã', lat: -12.9503, lng: -38.3762 },
    { id: 'itapua', nome: 'Itapuã', lat: -12.9449, lng: -38.3628 },
    { id: 'stella-maris', nome: 'Stella Maris', lat: -12.9378, lng: -38.3452 },
    { id: 'mussurunga', nome: 'Mussurunga', lat: -12.9317, lng: -38.3722 },
];

/**
 * O centro do mapa quando o aparelho não entrega posição.
 *
 * A Boca do Rio, que dá nome à Área 5 — e não mais o Farol da Barra, que fica
 * na área de outra equipe.
 */
export const CENTRO_SALVADOR = { lat: -12.9678, lng: -38.4192 };

export const OCORRENCIAS = [
    { id: 'desarmou', rotulo: 'Desarmou e saiu', emoji: '✅' },
    { id: 'orientado', rotulo: 'Orientado no local', emoji: '🗣️' },
    { id: 'recusou', rotulo: 'Recusou sair', emoji: '🚫' },
    { id: 'vazio', rotulo: 'Local vazio na chegada', emoji: '🌬️' },
    { id: 'reincidente', rotulo: 'Reincidente', emoji: '🔁' },
    { id: 'calcada', rotulo: 'Obstrução de calçada', emoji: '🚧' },
    { id: 'sem-permissao', rotulo: 'Sem permissão', emoji: '📄' },
    { id: 'bebida', rotulo: 'Venda de bebida', emoji: '🍺' },
    { id: 'alimento', rotulo: 'Manipulação de alimento', emoji: '🍢' },
    { id: 'apoio', rotulo: 'Apoio da guarda municipal', emoji: '🚓' },
];

const ATIVIDADES = [
    { nome: 'Acarajé e abará', emoji: '🍢' },
    { nome: 'Água e refrigerante', emoji: '🥤' },
    { nome: 'Cerveja e gelo', emoji: '🍺' },
    { nome: 'Artesanato e fitas', emoji: '🪡' },
    { nome: 'Óculos e bijuteria', emoji: '🕶️' },
    { nome: 'Milho e amendoim', emoji: '🌽' },
    { nome: 'Cocada e mariola', emoji: '🍬' },
    { nome: 'Espetinho', emoji: '🍡' },
    { nome: 'Sorvete e picolé', emoji: '🍦' },
    { nome: 'Capa de chuva e guarda-sol', emoji: '☂️' },
    { nome: 'Frutas', emoji: '🍍' },
    { nome: 'Caldo de cana', emoji: '🍹' },
];

type Semente = {
    nome: string;
    apelido: string;
    regiao: string;
    endereco: string;
    dLat: number;
    dLng: number;
    atividade: number;
    situacao: Situacao;
    permissao: string | null;
    /** Há quantos dias o ponto foi fiscalizado — vira data na montagem. */
    ultimaHaDias: number;
    retornoHaDias: number | null;
};

/* Os pontos conhecidos da Área 5. Nome, apelido, atividade e histórico são os
   mesmos de antes — o que mudou foi o ENDEREÇO: todos passaram para o bloco de
   bairros da Equipe C1, que é a equipe do aparelho. */
const SEMENTES: Semente[] = [
    { nome: 'Josefa Maria dos Santos', apelido: 'Dona Zefa', regiao: 'jardim-armacao', endereco: 'Av. Otávio Mangabeira, em frente ao Centro de Convenções', dLat: 0.0004, dLng: 0.0006, atividade: 0, situacao: 'regular', permissao: 'PA-2024/0187', ultimaHaDias: 14, retornoHaDias: null },
    { nome: 'Antônio Carlos de Jesus', apelido: 'Toinho do Gelo', regiao: 'boca-do-rio', endereco: 'Praia da Boca do Rio, altura da rampa', dLat: 0.0018, dLng: -0.0012, atividade: 2, situacao: 'irregular', permissao: null, ultimaHaDias: 5, retornoHaDias: 5 },
    { nome: 'Marinalva Conceição Lima', apelido: 'Nalva', regiao: 'costa-azul', endereco: 'Orla do Jardim de Alah, lado do calçadão', dLat: -0.0011, dLng: 0.0015, atividade: 1, situacao: 'regular', permissao: 'PA-2023/1142', ultimaHaDias: 21, retornoHaDias: null },
    { nome: 'Edvaldo Souza Pereira', apelido: 'Vardinho', regiao: 'stiep', endereco: 'Rua Ewerton Visco, esquina com o canteiro', dLat: 0.0022, dLng: 0.0021, atividade: 4, situacao: 'irregular', permissao: null, ultimaHaDias: 2, retornoHaDias: 2 },
    { nome: 'Rosângela Batista Nunes', apelido: 'Rosa do Milho', regiao: 'jardim-armacao', endereco: 'Av. Otávio Mangabeira, próximo ao Parque dos Ventos', dLat: -0.0026, dLng: -0.0007, atividade: 5, situacao: 'regular', permissao: 'PA-2025/0032', ultimaHaDias: 8, retornoHaDias: null },
    { nome: 'Genivaldo Ramos Filho', apelido: 'Geni', regiao: 'costa-azul', endereco: 'Av. Otávio Mangabeira, orla do Jardim de Alah', dLat: 0.0007, dLng: 0.0004, atividade: 3, situacao: 'irregular', permissao: null, ultimaHaDias: 3, retornoHaDias: 3 },
    { nome: 'Cláudia Regina Alves', apelido: 'Claudinha', regiao: 'imbui', endereco: 'Rua Ilhéus, calçada par', dLat: -0.0014, dLng: 0.0019, atividade: 8, situacao: 'regular', permissao: 'PA-2024/0790', ultimaHaDias: 15, retornoHaDias: null },
    { nome: 'Ubiratan Ferreira Gomes', apelido: 'Bira', regiao: 'stiep', endereco: 'Rua Doutor Augusto Lopes Pontes, ao lado da feira', dLat: 0.0016, dLng: -0.0018, atividade: 2, situacao: 'irregular', permissao: null, ultimaHaDias: 1, retornoHaDias: 1 },
    { nome: 'Maria de Lourdes Sacramento', apelido: 'Lurdinha', regiao: 'costa-azul', endereco: 'Praia de Costa Azul, acesso à areia', dLat: -0.0021, dLng: -0.0009, atividade: 0, situacao: 'regular', permissao: 'PA-2022/2210', ultimaHaDias: 24, retornoHaDias: null },
    { nome: 'Ademilson Cruz Barbosa', apelido: 'Demí', regiao: 'pituacu', endereco: 'Av. Prof. Pinto de Aguiar, entrada do Parque de Pituaçu', dLat: 0.0009, dLng: 0.0011, atividade: 3, situacao: 'irregular', permissao: null, ultimaHaDias: 7, retornoHaDias: 7 },
    { nome: 'Vera Lúcia Nascimento', apelido: 'Vera das Fitas', regiao: 'pituacu', endereco: 'Praia de Pituaçu, em frente ao quiosque 3', dLat: -0.0013, dLng: 0.0006, atividade: 3, situacao: 'regular', permissao: 'PA-2023/0455', ultimaHaDias: 12, retornoHaDias: null },
    { nome: 'Reinaldo Teixeira Matos', apelido: 'Rei', regiao: 'imbui', endereco: 'Rua Mário Leal, meio da ladeira', dLat: 0.0006, dLng: -0.0016, atividade: 6, situacao: 'irregular', permissao: null, ultimaHaDias: 4, retornoHaDias: null },
    { nome: 'Sandra Regina Oliveira', apelido: 'Sandrinha', regiao: 'patamares', endereco: 'Praia de Patamares, próximo ao mirante', dLat: -0.0019, dLng: -0.0013, atividade: 10, situacao: 'regular', permissao: 'PA-2025/0311', ultimaHaDias: 17, retornoHaDias: null },
    { nome: 'Jailson Moreira da Silva', apelido: 'Jaja', regiao: 'itapua', endereco: 'Rua da Música, em frente ao Farol de Itapuã', dLat: 0.0012, dLng: 0.0014, atividade: 7, situacao: 'irregular', permissao: null, ultimaHaDias: 6, retornoHaDias: 6 },
    { nome: 'Terezinha Gomes Rocha', apelido: 'Tetê', regiao: 'itapua', endereco: 'Praia de Itapuã, quiosque 4', dLat: -0.0017, dLng: 0.0008, atividade: 11, situacao: 'regular', permissao: 'PA-2024/1503', ultimaHaDias: 9, retornoHaDias: null },
    { nome: 'Wellington Prado Cardoso', apelido: 'Well', regiao: 'itapua', endereco: 'Alameda da Praia, altura do posto', dLat: 0.0024, dLng: -0.0011, atividade: 2, situacao: 'irregular', permissao: null, ultimaHaDias: 1, retornoHaDias: null },
    { nome: 'Ivonete Cerqueira Dias', apelido: 'Neta', regiao: 'piata', endereco: 'Praia de Piatã, lado do coreto', dLat: 0.0008, dLng: 0.0009, atividade: 8, situacao: 'regular', permissao: 'PA-2023/0918', ultimaHaDias: 13, retornoHaDias: null },
    { nome: 'Robson Almeida Vieira', apelido: 'Robinho', regiao: 'mussurunga', endereco: 'Av. Luís Viana Filho, ponto de ônibus', dLat: -0.0015, dLng: 0.0017, atividade: 1, situacao: 'irregular', permissao: null, ultimaHaDias: 2, retornoHaDias: 4 },
    { nome: 'Dilma Santana Freitas', apelido: 'Dida', regiao: 'mussurunga', endereco: 'Rua Direta de Mussurunga, calçada ímpar', dLat: 0.0019, dLng: -0.0006, atividade: 6, situacao: 'regular', permissao: 'PA-2022/0674', ultimaHaDias: 20, retornoHaDias: null },
    { nome: 'Sérgio Luiz Damasceno', apelido: 'Serginho', regiao: 'boca-do-rio', endereco: 'Rua Doutor Walter Ribeiro, esquina com a orla', dLat: 0.0011, dLng: 0.0013, atividade: 2, situacao: 'irregular', permissao: null, ultimaHaDias: 3, retornoHaDias: 9 },
    { nome: 'Patrícia Mendes Argolo', apelido: 'Paty', regiao: 'boca-do-rio', endereco: 'Praia da Boca do Rio, quiosque 12', dLat: -0.0016, dLng: 0.0007, atividade: 9, situacao: 'regular', permissao: 'PA-2025/0128', ultimaHaDias: 10, retornoHaDias: null },
    { nome: 'Anderson Rocha Sampaio', apelido: 'Deko', regiao: 'patamares', endereco: 'Alameda Praia de Patamares, acesso à areia', dLat: 0.0021, dLng: -0.0015, atividade: 7, situacao: 'irregular', permissao: null, ultimaHaDias: 1, retornoHaDias: null },
    { nome: 'Célia Maria Bonfim', apelido: 'Ciça', regiao: 'stella-maris', endereco: 'Praia de Stella Maris, frente ao estacionamento', dLat: 0.0006, dLng: 0.0008, atividade: 3, situacao: 'regular', permissao: 'PA-2024/0602', ultimaHaDias: 16, retornoHaDias: null },
    { nome: 'Nilton César Passos', apelido: 'Nilton', regiao: 'piata', endereco: 'Av. Otávio Mangabeira, saída da passarela de Piatã', dLat: -0.0012, dLng: -0.0009, atividade: 4, situacao: 'irregular', permissao: null, ultimaHaDias: 5, retornoHaDias: 12 },
    { nome: 'Adriana Lopes Figueiredo', apelido: 'Dri', regiao: 'jardim-armacao', endereco: 'Av. Otávio Mangabeira, mirante da Armação', dLat: 0.0014, dLng: 0.0005, atividade: 5, situacao: 'regular', permissao: 'PA-2023/1780', ultimaHaDias: 11, retornoHaDias: null },
];

const centroDe = (id: string): Regiao => REGIOES.find((r) => r.id === id) ?? REGIOES[0];

export const nomeRegiao = (id: string): string => centroDe(id).nome;

const historicoDe = (s: Semente, indice: number): EventoHistorico[] => {
    const base: EventoHistorico[] = [
        {
            data: dataBrDaqui(-s.ultimaHaDias),
            resumo:
                s.situacao === 'regular'
                    ? 'Ponto conferido — permissão em dia e passagem livre.'
                    : 'Orientado a desarmar; recolheu a barraca na hora.',
            status: s.situacao,
        },
    ];

    if (indice % 3 === 0) {
        base.push({
            data: dataBrDaqui(-34),
            resumo: 'Abordagem educativa: orientação sobre o horário permitido.',
            status: 'irregular',
        });
    }

    if (indice % 2 === 0) {
        base.push({
            data: dataBrDaqui(-46),
            resumo: 'Local vazio na chegada da equipe.',
            status: 'regular',
        });
    }

    return base;
};

export const AMBULANTES: Ambulante[] = SEMENTES.map((s, i) => {
    const centro = centroDe(s.regiao);
    const atividade = ATIVIDADES[s.atividade % ATIVIDADES.length];

    return {
        id: `amb-${String(i + 1).padStart(2, '0')}`,
        nome: s.nome,
        apelido: s.apelido,
        atividade: atividade.nome,
        emoji: atividade.emoji,
        situacao: s.situacao,
        permissao: s.permissao,
        regiao: s.regiao,
        endereco: s.endereco,
        lat: centro.lat + s.dLat,
        lng: centro.lng + s.dLng,
        ultimaEm: dataBrDaqui(-s.ultimaHaDias),
        retornoHaDias: s.retornoHaDias,
        historico: historicoDe(s, i),
    };
});

export const RETORNOS_PENDENTES = AMBULANTES.filter((a) => a.retornoHaDias !== null).sort(
    (a, b) => (b.retornoHaDias ?? 0) - (a.retornoHaDias ?? 0),
);

/* ----------------------------- Registros do turno ----------------------------- */

type SementeRegistro = [
    hora: string,
    /** Há quantos dias a fiscalização aconteceu (0 = hoje) — vira data BR. */
    diasAtras: number,
    regiao: string,
    endereco: string,
    /** Da lista fechada de desfechos — o `status` do ponto sai dele. */
    desfecho: Desfecho,
    ocorrencias: string[],
    relato: string,
    fotos: number,
    ambulante: string | null,
    /** Em quantos dias o retorno foi marcado — `null` quando não houve. */
    retornoEmDias: number | null,
    envio: 'enviado' | 'pendente' | 'erro',
    documento: string | null,
    /** Demanda que originou a fiscalização; `null` = o fiscal achou andando. */
    demandaId: string | null,
    /** Nº do processo da demanda, que o papel chama de REFERÊNCIA. */
    referencia: string | null,
];

/* O turno do fiscal da Equipe C1 — os endereços são os mesmos pontos da Área 5.
   A maioria é trabalho AVULSO (o fiscal andando a rua); a fiscalização DIRIGIDA
   é a da DEN-0029, e ela é a mesma dos dois lados: o mesmo endereço, o mesmo
   relato de campo, o mesmo permissionário e a MESMA Notificação nº 194903 que a
   Retaguarda mostra no passo do trâmite. É o que faz o retorno dessa denúncia
   ter sentido no aplicativo — o prazo dela está correndo.

   A proporção é educativa de propósito: sete casos terminaram sem documento
   nenhum, cinco com papel. */
const SEMENTES_REGISTRO: SementeRegistro[] = [
    ['14:38', 0, 'jardim-armacao', 'Av. Otávio Mangabeira, acesso à Praia da Boca do Rio', 'Regularizado no local', ['desarmou', 'reincidente'], 'Barraca montada sobre a faixa de pedestres. Orientado, desarmou na hora e deixou o ponto sem resistência.', 3, 'Toinho do Gelo', 3, 'pendente', null, null, null],
    ['13:52', 0, 'stiep', 'Rua Ewerton Visco, esquina com o canteiro', 'Auto de Apreensão lavrado', ['recusou', 'apoio'], 'Recusou-se a sair na primeira abordagem. Com apoio da guarda municipal, retirou a mercadoria.', 5, 'Vardinho', 1, 'erro', 'AA 160049', null, null],
    ['12:07', 0, 'itapua', 'Praia de Itapuã, altura do Farol', 'Regularizado no local', ['orientado'], 'Permissão apresentada e conferida. Passagem livre, sem obstrução.', 1, 'Tetê', null, 'enviado', null, null, null],
    ['11:20', 0, 'patamares', 'Alameda Praia de Patamares, acesso à areia', 'Nada encontrado no local', ['vazio'], 'Local vazio na chegada da equipe.', 1, null, null, 'enviado', null, null, null],
    ['10:44', 0, 'costa-azul', 'Orla do Jardim de Alah, canteiro central', 'Regularizado no local', ['desarmou', 'calcada'], 'Ponto ocupando o canteiro. Desarmou e saiu após a orientação.', 2, 'Geni', 7, 'pendente', null, null, null],
    ['09:58', 0, 'imbui', 'Rua Ilhéus, entorno da feira', 'Regularizado no local', ['bebida', 'sem-permissao'], 'Venda de bebida gelada sem permissão. Ambulante não identificado — saiu antes da conferência.', 4, null, 2, 'enviado', null, null, null],
    ['16:35', -1, 'itapua', 'Rua da Música, em frente ao Farol de Itapuã', 'Notificação Preliminar emitida', ['desarmou', 'alimento'], 'Espetinho montado sem estrutura mínima de higiene. Recolheu o material.', 3, 'Jaja', 6, 'enviado', 'NP 194894', null, null],
    ['15:12', -1, 'boca-do-rio', 'Praia da Boca do Rio, quiosque 12', 'Regularizado no local', ['orientado'], 'Ponto dentro dos limites autorizados.', 2, 'Paty', null, 'enviado', null, null, null],
    ['11:03', -1, 'stella-maris', 'Praia de Stella Maris, frente ao estacionamento', 'Regularizado no local', ['orientado'], 'Conferido no roteiro da manhã. Sem ocorrência.', 1, 'Ciça', null, 'enviado', null, null, null],
    ['09:41', -2, 'mussurunga', 'Av. Luís Viana Filho, ponto de ônibus', 'Notificação Preliminar emitida', ['desarmou', 'calcada', 'reincidente'], 'Terceira ocorrência no mesmo ponto neste mês. Desarmou e saiu.', 6, 'Robinho', 5, 'enviado', 'NP 194892', null, null],
    ['08:26', -2, 'pituacu', 'Av. Prof. Pinto de Aguiar, entrada do Parque de Pituaçu', 'Auto de Apreensão lavrado', ['recusou'], 'Insistiu em permanecer alegando autorização verbal. Documento emitido no local.', 4, 'Demí', 0, 'enviado', 'AA 160047', null, null],
    ['16:40', -3, 'costa-azul', 'Avenida Otávio Mangabeira, 2140 — Costa Azul', 'Notificação Preliminar emitida', ['calcada', 'orientado'], 'Puxada de madeira de aproximadamente 2 m × 3 m nos fundos da barraca, usada como depósito de bebidas e botijão, fora do padrão autorizado. Área de mesas avançando sobre a passagem de acesso à areia, que ficou com pouco mais de um metro. Permissionário presente, com permissão regular e DAM do exercício quitado, apresentado no local.', 3, 'Jailson Pereira dos Santos', 2, 'enviado', 'NP 194903', 'den-0029', 'DEN-0029'],
];

/** "NP 194894" → 'np'. O número guarda a sigla, então o tipo se deduz dele. */
const tipoDoDocumento = (documento: string | null): 'np' | 'aa' | null => {
    if (!documento) {
        return null;
    }

    return documento.startsWith('AA') ? 'aa' : 'np';
};

const protocoloDe = (dataBr: string, ordem: number): string => {
    const [dia, mes, ano] = dataBr.split('/');

    return `FA${ano}${mes}${dia}${String(ordem).padStart(3, '0')}`;
};

export const REGISTROS: Registro[] = SEMENTES_REGISTRO.map((s, i) => {
    const centro = centroDe(s[2]);
    const desvio = ((i % 5) - 2) * 0.0009;
    const dataBr = dataBrDaqui(s[1]);

    return {
        id: `reg-${String(i + 1).padStart(3, '0')}`,
        protocolo: protocoloDe(dataBr, i + 1),
        hora: s[0],
        dataBr,
        regiao: s[2],
        endereco: s[3],
        lat: centro.lat + desvio,
        lng: centro.lng - desvio,
        desfecho: s[4],
        status: LOCAL_APOS_O_DESFECHO[s[4]],
        ocorrencias: s[5],
        relato: s[6],
        fotos: s[7],
        ambulante: s[8],
        retornoBr: s[9] === null ? null : dataBrDaqui(s[9]),
        envio: s[10],
        documento: s[11],
        documentoTipo: tipoDoDocumento(s[11]),
        via: null,
        origem: s[12] ? 'dirigida' : 'avulsa',
        demandaId: s[12],
        referencia: s[13],
    };
});

/* ------------------------------ Mapa de calor ------------------------------ */

export type PontoCalor = [lat: number, lng: number, peso: number];

/**
 * Gerador determinístico — o protótipo tem de mostrar SEMPRE o mesmo desenho de
 * calor, senão a conversa com o dono muda a cada recarregamento da página.
 */
const sorteio = (semente: number): (() => number) => {
    let estado = semente;

    return () => {
        estado = (estado * 1664525 + 1013904223) % 4294967296;

        return estado / 4294967296;
    };
};

type Foco = { regiao: string; registros: number; raio: number; dias: number };

/** Os focos de incidência que a fiscalização vem acumulando. */
const FOCOS: Foco[] = [
    { regiao: 'boca-do-rio', registros: 74, raio: 0.006, dias: 90 },
    { regiao: 'itapua', registros: 52, raio: 0.007, dias: 90 },
    { regiao: 'costa-azul', registros: 41, raio: 0.0035, dias: 90 },
    { regiao: 'jardim-armacao', registros: 23, raio: 0.004, dias: 90 },
    { regiao: 'pituacu', registros: 16, raio: 0.006, dias: 60 },
    { regiao: 'stiep', registros: 12, raio: 0.003, dias: 45 },
    { regiao: 'patamares', registros: 9, raio: 0.004, dias: 30 },
];

export type IncidenciaCalor = {
    lat: number;
    lng: number;
    peso: number;
    /** Há quantos dias a incidência foi registrada — é o que o filtro recorta. */
    haDias: number;
    regiao: string;
};

const gerarIncidencias = (): IncidenciaCalor[] => {
    const proximo = sorteio(20260826);
    const pontos: IncidenciaCalor[] = [];

    for (const foco of FOCOS) {
        const centro = centroDe(foco.regiao);

        for (let i = 0; i < foco.registros; i++) {
            /* Concentração maior no miolo do foco: o quadrado do sorteio puxa os
               pontos para o centro, que é como a incidência se comporta em rua. */
            const distancia = proximo() ** 2 * foco.raio;
            const angulo = proximo() * Math.PI * 2;

            pontos.push({
                lat: centro.lat + Math.sin(angulo) * distancia,
                lng: centro.lng + Math.cos(angulo) * distancia * 1.02,
                peso: 0.45 + proximo() * 0.55,
                haDias: Math.floor(proximo() * foco.dias) + 1,
                regiao: foco.regiao,
            });
        }
    }

    return pontos;
};

export const INCIDENCIAS: IncidenciaCalor[] = gerarIncidencias();

export type ResumoRegiao = {
    id: string;
    nome: string;
    lat: number;
    lng: number;
    registros: number;
    fatia: number;
};

/** Ranking das regiões dentro da janela escolhida (7, 30 ou 90 dias). */
export const regioesMaisQuentes = (dias: number): ResumoRegiao[] => {
    const janela = INCIDENCIAS.filter((p) => p.haDias <= dias);
    const total = janela.length || 1;
    const contagem = new Map<string, number>();

    for (const ponto of janela) {
        contagem.set(ponto.regiao, (contagem.get(ponto.regiao) ?? 0) + 1);
    }

    return [...contagem.entries()]
        .map(([id, registros]) => {
            const centro = centroDe(id);

            return {
                id,
                nome: centro.nome,
                lat: centro.lat,
                lng: centro.lng,
                registros,
                fatia: Math.round((registros / total) * 100),
            };
        })
        .sort((a, b) => b.registros - a.registros)
        .slice(0, 5);
};

export const pontosDoCalor = (dias: number): PontoCalor[] =>
    INCIDENCIAS.filter((p) => p.haDias <= dias).map((p) => [p.lat, p.lng, p.peso]);

/* --------------------------------- Diversos --------------------------------- */

export const PRAZOS_RETORNO = [
    { id: 'amanha', rotulo: 'Amanhã', dias: 1 },
    { id: 'tres', rotulo: 'Em 3 dias', dias: 3 },
    { id: 'semana', rotulo: 'Em 1 semana', dias: 7 },
];

/**
 * Os DOIS documentos que o SEFAL lavra em campo — e são exatamente estes.
 *
 * O protótipo anterior chutava um par genérico ("Notificação de Termo" e "Auto
 * de Infração"). Os blocos de papel que o cliente entregou dizem outra coisa: o
 * que o agente preenche na calçada é a **Notificação Preliminar** (dá prazo
 * para sanar) e, quando recolhe material, o **Auto de Apreensão** (que manda os
 * bens para o SEGUB). Auto de Infração existe no fluxo da SEMOP, mas não é
 * documento de campo deste setor — não entra aqui.
 */
export const DOCUMENTOS_DE_CAMPO = [
    {
        id: 'np' as const,
        titulo: 'Notificação Preliminar',
        sigla: 'NP',
        descricao: 'Aponta as irregularidades e dá prazo para sanar, sob as penalidades previstas.',
        emoji: '📋',
    },
    {
        id: 'aa' as const,
        titulo: 'Auto de Apreensão',
        sigla: 'AA',
        descricao: 'Recolhe material e mercadoria, com guarda no SEGUB e prazo de permanência.',
        emoji: '📦',
    },
];

export const horaAgora = (): string =>
    new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });


export type Operacao = {
    id: string;
    nome: string;
    local: string;
    regiao: string;
    quando: string;
    horario: string;
    fiscais: number;
    tom: 'agora' | 'proxima' | 'planejada';
    observacao: string;
};

/** As operações que a chefia montou para a semana — pauta do fiscal ao acordar. */
export const OPERACOES: Operacao[] = [
    {
        id: 'op-1',
        nome: 'Rotina Orla',
        local: 'Boca do Rio e Jardim Armação',
        regiao: 'boca-do-rio',
        quando: `Hoje, ${dataBrDaqui(0)}`,
        horario: '16h às 20h',
        fiscais: 6,
        tom: 'agora',
        observacao: 'Você está escalado nesta operação.',
    },
    {
        id: 'op-2',
        nome: 'Rotina Costa Azul',
        local: 'Orla do Jardim de Alah',
        regiao: 'costa-azul',
        quando: `Amanhã, ${dataBrDaqui(1)}`,
        horario: '08h às 12h',
        fiscais: 4,
        tom: 'proxima',
        observacao: 'Foco em passagem livre no calçadão.',
    },
    {
        id: 'op-3',
        nome: 'Operação Verão',
        local: 'Orla de Itapuã e Stella Maris',
        regiao: 'itapua',
        quando: dataBrDaqui(2),
        horario: '14h às 19h',
        fiscais: 8,
        tom: 'planejada',
        observacao: 'Apoio da guarda municipal confirmado.',
    },
    {
        id: 'op-4',
        nome: 'Feira do Imbuí',
        local: 'Rua Ilhéus, entorno da feira',
        regiao: 'imbui',
        quando: dataBrDaqui(5),
        horario: '06h às 11h',
        fiscais: 5,
        tom: 'planejada',
        observacao: 'Montagem da feira acompanhada desde a madrugada.',
    },
];


/** Saudação pela hora do aparelho — o aplicativo abre falando com gente. */
export const saudacao = (): string => {
    const hora = new Date().getHours();

    if (hora < 12) {
        return 'Bom dia';
    }

    return hora < 18 ? 'Boa tarde' : 'Boa noite';
};

/**
 * A data de hoje por extenso, com a primeira letra maiúscula.
 *
 * Calculada, não escrita: era uma data fixa, e o aplicativo abria dizendo "26 de
 * agosto" em qualquer dia que a demonstração acontecesse — ao lado de uma fila
 * com prazos de hoje.
 */
export const DATA_POR_EXTENSO = (() => {
    const texto = new Date().toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return texto.charAt(0).toUpperCase() + texto.slice(1);
})();

/** "há 1 dia" / "há 4 dias" — a quantidade é sempre conhecida, então a frase sai pronta. */
export const emDias = (dias: number): string => (dias === 1 ? 'há 1 dia' : `há ${dias} dias`);

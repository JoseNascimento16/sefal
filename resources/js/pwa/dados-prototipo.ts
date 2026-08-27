/* ============================================================================
   PROTÓTIPO — dados fictícios do aplicativo do fiscal
   ----------------------------------------------------------------------------
   Nada aqui vem do banco: são pessoas, endereços e fiscalizações INVENTADOS,
   escritos para o dono ver as telas com a densidade real que elas terão em rua.
   Quando o aplicativo passar a falar com o servidor, este arquivo inteiro sai —
   é a fronteira do protótipo, e por isso está sozinho num módulo só.

   As COORDENADAS, essas, são reais: Barra, Rio Vermelho, Pelourinho, Itapuã,
   Campo Grande, Boca do Rio e Comércio. Sem isso o mapa mentiria sobre a única
   coisa que o mapa precisa acertar.
   ============================================================================ */

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

export type Registro = {
    id: string;
    protocolo: string;
    dataBr: string;
    hora: string;
    regiao: string;
    endereco: string;
    lat: number;
    lng: number;
    status: Situacao;
    ocorrencias: string[];
    relato: string;
    fotos: number;
    ambulante: string | null;
    retornoBr: string | null;
    envio: 'enviado' | 'pendente' | 'erro';
    documento: string | null;
};

export const FISCAL = {
    nome: 'Marcos Vinícius Andrade',
    matricula: 'F-40219',
    setor: 'SEFAL · Fiscalização de Ambulantes',
    equipe: 'Equipe Litoral · Turno tarde',
    iniciais: 'MA',
    desde: '2019',
};

export const REGIOES: Regiao[] = [
    { id: 'barra', nome: 'Barra', lat: -13.0106, lng: -38.5325 },
    { id: 'rio-vermelho', nome: 'Rio Vermelho', lat: -13.0104, lng: -38.4906 },
    { id: 'pelourinho', nome: 'Pelourinho', lat: -12.9718, lng: -38.5089 },
    { id: 'itapua', nome: 'Itapuã', lat: -12.9469, lng: -38.3628 },
    { id: 'campo-grande', nome: 'Campo Grande', lat: -12.9847, lng: -38.5152 },
    { id: 'boca-do-rio', nome: 'Boca do Rio', lat: -12.9647, lng: -38.4067 },
    { id: 'comercio', nome: 'Comércio', lat: -12.974, lng: -38.5137 },
    { id: 'ondina', nome: 'Ondina', lat: -13.0086, lng: -38.5065 },
];

/** O centro do mapa quando o aparelho não entrega posição: o Farol da Barra. */
export const CENTRO_SALVADOR = { lat: -13.0106, lng: -38.5325 };

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
    ultimaEm: string;
    retornoHaDias: number | null;
};

const SEMENTES: Semente[] = [
    { nome: 'Josefa Maria dos Santos', apelido: 'Dona Zefa', regiao: 'barra', endereco: 'Av. Oceânica, em frente ao Farol', dLat: 0.0004, dLng: 0.0006, atividade: 0, situacao: 'regular', permissao: 'PA-2024/0187', ultimaEm: '12/08/2026', retornoHaDias: null },
    { nome: 'Antônio Carlos de Jesus', apelido: 'Toinho do Gelo', regiao: 'barra', endereco: 'Praia do Porto da Barra, altura da rampa', dLat: 0.0018, dLng: -0.0012, atividade: 2, situacao: 'irregular', permissao: null, ultimaEm: '21/08/2026', retornoHaDias: 5 },
    { nome: 'Marinalva Conceição Lima', apelido: 'Nalva', regiao: 'barra', endereco: 'Largo do Farol da Barra, lado do calçadão', dLat: -0.0011, dLng: 0.0015, atividade: 1, situacao: 'regular', permissao: 'PA-2023/1142', ultimaEm: '05/08/2026', retornoHaDias: null },
    { nome: 'Edvaldo Souza Pereira', apelido: 'Vardinho', regiao: 'barra', endereco: 'Rua Marquês de Caravelas, esquina', dLat: 0.0022, dLng: 0.0021, atividade: 4, situacao: 'irregular', permissao: null, ultimaEm: '24/08/2026', retornoHaDias: 2 },
    { nome: 'Rosângela Batista Nunes', apelido: 'Rosa do Milho', regiao: 'barra', endereco: 'Av. Oceânica, próximo ao Morro do Cristo', dLat: -0.0026, dLng: -0.0007, atividade: 5, situacao: 'regular', permissao: 'PA-2025/0032', ultimaEm: '18/08/2026', retornoHaDias: null },
    { nome: 'Genivaldo Ramos Filho', apelido: 'Geni', regiao: 'rio-vermelho', endereco: 'Largo da Mariquita, canteiro central', dLat: 0.0007, dLng: 0.0004, atividade: 3, situacao: 'irregular', permissao: null, ultimaEm: '23/08/2026', retornoHaDias: 3 },
    { nome: 'Cláudia Regina Alves', apelido: 'Claudinha', regiao: 'rio-vermelho', endereco: 'Rua da Paciência, calçada par', dLat: -0.0014, dLng: 0.0019, atividade: 8, situacao: 'regular', permissao: 'PA-2024/0790', ultimaEm: '11/08/2026', retornoHaDias: null },
    { nome: 'Ubiratan Ferreira Gomes', apelido: 'Bira', regiao: 'rio-vermelho', endereco: 'Largo de Santana, ao lado da feira', dLat: 0.0016, dLng: -0.0018, atividade: 2, situacao: 'irregular', permissao: null, ultimaEm: '25/08/2026', retornoHaDias: 1 },
    { nome: 'Maria de Lourdes Sacramento', apelido: 'Lurdinha', regiao: 'rio-vermelho', endereco: 'Praia da Paciência, acesso à areia', dLat: -0.0021, dLng: -0.0009, atividade: 0, situacao: 'regular', permissao: 'PA-2022/2210', ultimaEm: '02/08/2026', retornoHaDias: null },
    { nome: 'Ademilson Cruz Barbosa', apelido: 'Demí', regiao: 'pelourinho', endereco: 'Largo do Pelourinho, escadaria', dLat: 0.0009, dLng: 0.0011, atividade: 3, situacao: 'irregular', permissao: null, ultimaEm: '19/08/2026', retornoHaDias: 7 },
    { nome: 'Vera Lúcia Nascimento', apelido: 'Vera das Fitas', regiao: 'pelourinho', endereco: 'Terreiro de Jesus, em frente à Catedral', dLat: -0.0013, dLng: 0.0006, atividade: 3, situacao: 'regular', permissao: 'PA-2023/0455', ultimaEm: '14/08/2026', retornoHaDias: null },
    { nome: 'Reinaldo Teixeira Matos', apelido: 'Rei', regiao: 'pelourinho', endereco: 'Rua Alfredo de Brito, meio da ladeira', dLat: 0.0006, dLng: -0.0016, atividade: 6, situacao: 'irregular', permissao: null, ultimaEm: '22/08/2026', retornoHaDias: null },
    { nome: 'Sandra Regina Oliveira', apelido: 'Sandrinha', regiao: 'pelourinho', endereco: 'Praça da Sé, próximo ao mirante', dLat: -0.0019, dLng: -0.0013, atividade: 10, situacao: 'regular', permissao: 'PA-2025/0311', ultimaEm: '09/08/2026', retornoHaDias: null },
    { nome: 'Jailson Moreira da Silva', apelido: 'Jaja', regiao: 'itapua', endereco: 'Rua da Música, em frente ao Farol de Itapuã', dLat: 0.0012, dLng: 0.0014, atividade: 7, situacao: 'irregular', permissao: null, ultimaEm: '20/08/2026', retornoHaDias: 6 },
    { nome: 'Terezinha Gomes Rocha', apelido: 'Tetê', regiao: 'itapua', endereco: 'Praia de Itapuã, quiosque 4', dLat: -0.0017, dLng: 0.0008, atividade: 11, situacao: 'regular', permissao: 'PA-2024/1503', ultimaEm: '17/08/2026', retornoHaDias: null },
    { nome: 'Wellington Prado Cardoso', apelido: 'Well', regiao: 'itapua', endereco: 'Alameda da Praia, altura do posto', dLat: 0.0024, dLng: -0.0011, atividade: 2, situacao: 'irregular', permissao: null, ultimaEm: '25/08/2026', retornoHaDias: null },
    { nome: 'Ivonete Cerqueira Dias', apelido: 'Neta', regiao: 'campo-grande', endereco: 'Praça Dois de Julho, lado do coreto', dLat: 0.0008, dLng: 0.0009, atividade: 8, situacao: 'regular', permissao: 'PA-2023/0918', ultimaEm: '13/08/2026', retornoHaDias: null },
    { nome: 'Robson Almeida Vieira', apelido: 'Robinho', regiao: 'campo-grande', endereco: 'Av. Sete de Setembro, ponto de ônibus', dLat: -0.0015, dLng: 0.0017, atividade: 1, situacao: 'irregular', permissao: null, ultimaEm: '24/08/2026', retornoHaDias: 4 },
    { nome: 'Dilma Santana Freitas', apelido: 'Dida', regiao: 'campo-grande', endereco: 'Rua Carlos Gomes, calçada ímpar', dLat: 0.0019, dLng: -0.0006, atividade: 6, situacao: 'regular', permissao: 'PA-2022/0674', ultimaEm: '06/08/2026', retornoHaDias: null },
    { nome: 'Sérgio Luiz Damasceno', apelido: 'Serginho', regiao: 'boca-do-rio', endereco: 'Av. Otávio Mangabeira, orla do Jardim de Alah', dLat: 0.0011, dLng: 0.0013, atividade: 2, situacao: 'irregular', permissao: null, ultimaEm: '23/08/2026', retornoHaDias: 9 },
    { nome: 'Patrícia Mendes Argolo', apelido: 'Paty', regiao: 'boca-do-rio', endereco: 'Praia da Boca do Rio, quiosque 12', dLat: -0.0016, dLng: 0.0007, atividade: 9, situacao: 'regular', permissao: 'PA-2025/0128', ultimaEm: '16/08/2026', retornoHaDias: null },
    { nome: 'Anderson Rocha Sampaio', apelido: 'Deko', regiao: 'boca-do-rio', endereco: 'Rua Pernambués, acesso à areia', dLat: 0.0021, dLng: -0.0015, atividade: 7, situacao: 'irregular', permissao: null, ultimaEm: '25/08/2026', retornoHaDias: null },
    { nome: 'Célia Maria Bonfim', apelido: 'Ciça', regiao: 'comercio', endereco: 'Praça Cairu, frente ao Mercado Modelo', dLat: 0.0006, dLng: 0.0008, atividade: 3, situacao: 'regular', permissao: 'PA-2024/0602', ultimaEm: '10/08/2026', retornoHaDias: null },
    { nome: 'Nilton César Passos', apelido: 'Nilton', regiao: 'comercio', endereco: 'Av. da França, saída do Elevador Lacerda', dLat: -0.0012, dLng: -0.0009, atividade: 4, situacao: 'irregular', permissao: null, ultimaEm: '21/08/2026', retornoHaDias: 12 },
    { nome: 'Adriana Lopes Figueiredo', apelido: 'Dri', regiao: 'ondina', endereco: 'Av. Adhemar de Barros, mirante de Ondina', dLat: 0.0014, dLng: 0.0005, atividade: 5, situacao: 'regular', permissao: 'PA-2023/1780', ultimaEm: '15/08/2026', retornoHaDias: null },
];

const centroDe = (id: string): Regiao => REGIOES.find((r) => r.id === id) ?? REGIOES[0];

export const nomeRegiao = (id: string): string => centroDe(id).nome;

const historicoDe = (s: Semente, indice: number): EventoHistorico[] => {
    const base: EventoHistorico[] = [
        {
            data: s.ultimaEm,
            resumo:
                s.situacao === 'regular'
                    ? 'Ponto conferido — permissão em dia e passagem livre.'
                    : 'Orientado a desarmar; recolheu a barraca na hora.',
            status: s.situacao,
        },
    ];

    if (indice % 3 === 0) {
        base.push({
            data: '29/07/2026',
            resumo: 'Abordagem educativa: orientação sobre o horário permitido.',
            status: 'irregular',
        });
    }

    if (indice % 2 === 0) {
        base.push({
            data: '11/07/2026',
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
        ultimaEm: s.ultimaEm,
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
    dataBr: string,
    regiao: string,
    endereco: string,
    status: Situacao,
    ocorrencias: string[],
    relato: string,
    fotos: number,
    ambulante: string | null,
    retornoBr: string | null,
    envio: 'enviado' | 'pendente' | 'erro',
    documento: string | null,
];

const SEMENTES_REGISTRO: SementeRegistro[] = [
    ['14:38', '26/08/2026', 'barra', 'Av. Oceânica, em frente ao Farol', 'irregular', ['desarmou', 'reincidente'], 'Barraca montada sobre a faixa de pedestres. Orientado, desarmou na hora e deixou o ponto sem resistência.', 3, 'Toinho do Gelo', '29/08/2026', 'pendente', null],
    ['13:52', '26/08/2026', 'barra', 'Rua Marquês de Caravelas, esquina', 'irregular', ['recusou', 'apoio'], 'Recusou-se a sair na primeira abordagem. Com apoio da guarda municipal, retirou a mercadoria.', 5, 'Vardinho', '27/08/2026', 'erro', 'NT 2026/0418'],
    ['12:07', '26/08/2026', 'barra', 'Praia do Porto da Barra, altura da rampa', 'regular', ['orientado'], 'Permissão apresentada e conferida. Passagem livre, sem obstrução.', 1, 'Dona Zefa', null, 'enviado', null],
    ['11:20', '26/08/2026', 'ondina', 'Av. Adhemar de Barros, mirante de Ondina', 'regular', ['vazio'], 'Local vazio na chegada da equipe.', 1, null, null, 'enviado', null],
    ['10:44', '26/08/2026', 'rio-vermelho', 'Largo da Mariquita, canteiro central', 'irregular', ['desarmou', 'calcada'], 'Ponto ocupando o canteiro. Desarmou e saiu após a orientação.', 2, 'Geni', '02/09/2026', 'pendente', null],
    ['09:58', '26/08/2026', 'rio-vermelho', 'Largo de Santana, ao lado da feira', 'irregular', ['bebida', 'sem-permissao'], 'Venda de bebida gelada sem permissão. Ambulante não identificado — saiu antes da conferência.', 4, null, '28/08/2026', 'enviado', null],
    ['16:35', '25/08/2026', 'itapua', 'Rua da Música, em frente ao Farol de Itapuã', 'irregular', ['desarmou', 'alimento'], 'Espetinho montado sem estrutura mínima de higiene. Recolheu o material.', 3, 'Jaja', '01/09/2026', 'enviado', 'NT 2026/0411'],
    ['15:12', '25/08/2026', 'boca-do-rio', 'Praia da Boca do Rio, quiosque 12', 'regular', ['orientado'], 'Ponto dentro dos limites autorizados.', 2, 'Paty', null, 'enviado', null],
    ['11:03', '25/08/2026', 'comercio', 'Praça Cairu, frente ao Mercado Modelo', 'regular', ['orientado'], 'Conferido no roteiro da manhã. Sem ocorrência.', 1, 'Ciça', null, 'enviado', null],
    ['09:41', '24/08/2026', 'campo-grande', 'Av. Sete de Setembro, ponto de ônibus', 'irregular', ['desarmou', 'calcada', 'reincidente'], 'Terceira ocorrência no mesmo ponto neste mês. Desarmou e saiu.', 6, 'Robinho', '31/08/2026', 'enviado', 'AI 2026/0093'],
    ['08:26', '24/08/2026', 'pelourinho', 'Largo do Pelourinho, escadaria', 'irregular', ['recusou'], 'Insistiu em permanecer alegando autorização verbal. Documento emitido no local.', 4, 'Demí', '26/08/2026', 'enviado', 'AI 2026/0091'],
    ['17:14', '22/08/2026', 'comercio', 'Av. da França, saída do Elevador Lacerda', 'irregular', ['desarmou', 'sem-permissao'], 'Ponto sem permissão, obstruindo a saída do elevador.', 2, 'Nilton', '25/08/2026', 'enviado', null],
];

const protocoloDe = (dataBr: string, ordem: number): string => {
    const [dia, mes, ano] = dataBr.split('/');

    return `FA${ano}${mes}${dia}${String(ordem).padStart(3, '0')}`;
};

export const REGISTROS: Registro[] = SEMENTES_REGISTRO.map((s, i) => {
    const centro = centroDe(s[2]);
    const desvio = ((i % 5) - 2) * 0.0009;

    return {
        id: `reg-${String(i + 1).padStart(3, '0')}`,
        protocolo: protocoloDe(s[1], i + 1),
        hora: s[0],
        dataBr: s[1],
        regiao: s[2],
        endereco: s[3],
        lat: centro.lat + desvio,
        lng: centro.lng - desvio,
        status: s[4],
        ocorrencias: s[5],
        relato: s[6],
        fotos: s[7],
        ambulante: s[8],
        retornoBr: s[9],
        envio: s[10],
        documento: s[11],
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
    { regiao: 'barra', registros: 74, raio: 0.006, dias: 90 },
    { regiao: 'rio-vermelho', registros: 52, raio: 0.005, dias: 90 },
    { regiao: 'pelourinho', registros: 41, raio: 0.0035, dias: 90 },
    { regiao: 'itapua', registros: 23, raio: 0.007, dias: 90 },
    { regiao: 'boca-do-rio', registros: 16, raio: 0.006, dias: 60 },
    { regiao: 'comercio', registros: 12, raio: 0.003, dias: 45 },
    { regiao: 'campo-grande', registros: 9, raio: 0.004, dias: 30 },
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

export const MOTIVOS_DOCUMENTO = [
    { id: 'nt', titulo: 'Notificação de Termo', sigla: 'NT', descricao: 'Registro formal da orientação, sem multa.' },
    { id: 'ai', titulo: 'Auto de Infração', sigla: 'AI', descricao: 'Escalada com penalidade — reincidência ou recusa.' },
];

/** Data BR de hoje somada de N dias — o protótipo não guarda nada, só mostra. */
export const dataBrDaqui = (dias: number): string => {
    const d = new Date(2026, 7, 26);
    d.setDate(d.getDate() + dias);

    return d.toLocaleDateString('pt-BR');
};

export const horaAgora = (): string =>
    new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

export const HOJE_BR = '26/08/2026';

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
        nome: 'Rotina Centro',
        local: 'Pelourinho e Terreiro de Jesus',
        regiao: 'pelourinho',
        quando: 'Amanhã, 27/08/2026',
        horario: '08h às 12h',
        fiscais: 4,
        tom: 'proxima',
        observacao: 'Foco em passagem livre nas escadarias.',
    },
    {
        id: 'op-2',
        nome: 'Operação Verão',
        local: 'Orla de Itapuã',
        regiao: 'itapua',
        quando: '28/08/2026',
        horario: '14h às 19h',
        fiscais: 8,
        tom: 'planejada',
        observacao: 'Apoio da guarda municipal confirmado.',
    },
    {
        id: 'op-3',
        nome: 'Varredura da Barra',
        local: 'Av. Oceânica, do Farol ao Morro do Cristo',
        regiao: 'barra',
        quando: 'Hoje, 26/08/2026',
        horario: '16h às 20h',
        fiscais: 6,
        tom: 'agora',
        observacao: 'Você está escalado nesta operação.',
    },
    {
        id: 'op-4',
        nome: 'Feira de Santana do Rio Vermelho',
        local: 'Largo de Santana',
        regiao: 'rio-vermelho',
        quando: '31/08/2026',
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

export const DATA_POR_EXTENSO = 'Quarta-feira, 26 de agosto de 2026';

/** "há 1 dia" / "há 4 dias" — a quantidade é sempre conhecida, então a frase sai pronta. */
export const emDias = (dias: number): string => (dias === 1 ? 'há 1 dia' : `há ${dias} dias`);

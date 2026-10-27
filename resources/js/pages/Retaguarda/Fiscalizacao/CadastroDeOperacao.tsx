import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Info,
    Link2,
    List,
    MapPinned,
    Pencil,
    Plus,
    Target,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import { useAcoes } from '@/hooks/use-acoes';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { dataBR, hojeISO, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { plural } from '@/lib/plural';
import { cn } from '@/lib/utils';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/retaguarda/operacoes';

/**
 * Cadastro de Operação — PROTÓTIPO.
 *
 * "A operação é evento; a equipe é organização." A área e a equipe são a
 * estrutura permanente com que a SEMOP divide a cidade; a operação é o trabalho
 * com começo, fim e foco que se monta em cima dela — Operação Verão na orla,
 * Volta às Aulas no entorno das escolas, a rotina semanal do Centro.
 *
 * ── O ponto crítico: é o MESMO catálogo do direcionamento ───────────────────
 *
 * O que se cadastra aqui é exatamente o que o direcionamento das Denúncias
 * oferece ao Chefe de Setor quando ele anexa uma denúncia a uma operação já
 * planejada. Uma lista só, no servidor: com duas, o direcionamento ofereceria
 * amanhã uma operação que este cadastro não conhece — e recusaria a que ele
 * acabou de criar. A tela diz isso em voz alta, porque é a informação que faz
 * alguém entender por que a operação encerrada continua na lista e some de lá.
 *
 * ── As datas são BR na tela e ISO no campo ──────────────────────────────────
 *
 * Lei do projeto: `dd/mm/aaaa` em tudo o que se lê. O ISO existe só no valor do
 * `<input type="date">` e no corpo da requisição.
 */

/** Uma operação como o servidor a entrega — já com os campos derivados. */
interface Operacao {
    id: number;
    nome: string;
    area: string;
    /** Códigos de equipe (`C1`, `N1`…). Mais de uma é caso normal. */
    equipes: string[];
    /** A região alcançada, em texto — "Orla de Itapuã a Boca do Rio". */
    regiao: string;
    /** Os bairros varridos dentro da área. Vazio = a área inteira. */
    bairros: string[];
    /** ISO — quem escreve dd/mm/aaaa é a tela. */
    inicio: string | null;
    fim: string | null;
    /** A etiqueta do período, montada pelo servidor (data é conta dele). */
    periodo: string;
    situacao: string;
    foco: string;
    observacao: string;
    total_bairros: number;
    total_equipes: number;
    /** Atalho do servidor para `situacao === 'Encerrada'`. */
    encerrada: boolean;
    /** Fiscais de qualquer área postos na operação. */
    fiscais: { id: number; nome: string }[];
    /** As denúncias anexadas — a fiscalização delas vai para a rua junto. */
    demandas: { id: number; protocolo: string; assunto: string; bairro: string; situacao: string }[];
}

interface FiscalDisponivel {
    id: number;
    nome: string;
    equipes: string[];
}

interface DemandaDisponivel {
    id: number;
    protocolo: string;
    assunto: string;
    bairro: string;
    area: string;
    situacao: string;
    operacao_id: number | null;
}

interface Equipe {
    equipe: string;
    area: string;
    regiao: string;
    encarregado: string;
}

interface Props {
    operacoes: Operacao[];
    situacoes: string[];
    /** As áreas que esta pessoa pode usar — já recortadas pelo servidor. */
    areas: string[];
    equipes: Equipe[];
    bairros: string[];
    /** Quem lidera cada equipe — a operação é executada por uma equipe, e o líder dela responde. */
    lideres: Record<string, { nome: string; matricula: string | null }>;
    /** Esta pessoa CADASTRA, ou apenas consulta? Quem responde é o servidor. */
    cadastra: boolean;
    /** As áreas das equipes que esta pessoa lidera (vazio para quem vê tudo). */
    areasDoLider: string[];
    recorteDeArea: boolean;
    /** Os bairros de cada área — marcados sozinhos ao escolher a área ou a equipe. */
    bairrosPorArea: Record<string, string[]>;
    /** Fiscais de QUALQUER área que podem ser postos na operação. */
    fiscaisDisponiveis: FiscalDisponivel[];
    /** As denúncias que podem ser anexadas (abertas, não agregadas). */
    demandasDisponiveis: DemandaDisponivel[];
    /**
     * As colunas da grade e as do arquivo, declaradas no servidor. Ver
     * `docs/padroes/listagem-clean.md`.
     */
    listagens: Listagens;
}

type Aba = 'operacoes' | 'operacao';
type Modo = 'navegacao' | 'edicao';

type Faceta =
    | { tipo: 'situacao'; valor: string }
    | { tipo: 'em-curso' }
    | { tipo: 'sem-fim' };

/*
 * ⚠️ A ORDEM importa: a expressão mais específica vem antes, senão a genérica come
 * a outra.
 */
const FACETAS: { expressao: RegExp; valor: Faceta }[] = [
    { expressao: /\bencerrad\w*\b/, valor: { tipo: 'situacao', valor: 'Encerrada' } },
    { expressao: /\bplanejad\w*\b/, valor: { tipo: 'situacao', valor: 'Planejada' } },
    { expressao: /\bem andamento\b|\bandament\w*\b/, valor: { tipo: 'em-curso' } },
    { expressao: /\bpermanent\w*\b|\bsem fim\b|\bsem prazo\b/, valor: { tipo: 'sem-fim' } },
];

/** O tom do selo de cada situação. */
const TOM_DA_SITUACAO: Record<string, string> = {
    Planejada: 'selo-info',
    'Em andamento': 'selo-ok',
    Encerrada: 'selo-neutro',
};

export default function CadastroDeOperacao({
    operacoes,
    situacoes,
    areas,
    equipes,
    bairros,
    lideres,
    cadastra,
    areasDoLider,
    recorteDeArea,
    bairrosPorArea,
    fiscaisDisponiveis,
    demandasDisponiveis,
    listagens,
}: Props) {
    const acoes = useAcoes();
    const { enviando, ocupado, enviar, guardar } = useEnvio();

    /*
     * O saco de erros de validação do servidor. Ele é lido AQUI, e renderizado
     * campo a campo, porque recusa que não aparece é bloqueio em silêncio — e a
     * lei do projeto proíbe. É por ele que "o fim não pode ser antes do início" e
     * "já existe operação com esse nome" chegam a quem preencheu o formulário.
     */
    const { errors } = usePage().props;

    const [aba, setAba] = useState<Aba>('operacoes');
    const [modo, setModo] = useState<Modo>('navegacao');
    const [busca, setBusca] = useState('');
    const [abertaId, setAbertaId] = useState<number | null>(null);
    const [excluindo, setExcluindo] = useState(false);

    const aberta = operacoes.find((o) => o.id === abertaId) ?? null;

    // ── Filtro ──────────────────────────────────────────────────────────────

    const filtradas = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return operacoes.filter((o) => {
            for (const faceta of facetas) {
                if (faceta.tipo === 'situacao' && o.situacao !== faceta.valor) {
                    return false;
                }

                if (faceta.tipo === 'em-curso' && o.situacao !== 'Em andamento') {
                    return false;
                }

                if (faceta.tipo === 'sem-fim' && o.fim !== null) {
                    return false;
                }
            }

            return casaTermos(termos, [
                o.nome,
                o.area,
                o.regiao,
                o.foco,
                o.observacao,
                o.situacao,
                o.equipes.join(' '),
                o.bairros.join(' '),
            ]);
        });
    }, [operacoes, busca]);

    // Os números saem da MESMA lista que a grade desenha.
    const numeros = useMemo(
        () => ({
            total: operacoes.length,
            emAndamento: operacoes.filter((o) => o.situacao === 'Em andamento').length,
            planejadas: operacoes.filter((o) => o.situacao === 'Planejada').length,
            encerradas: operacoes.filter((o) => o.encerrada).length,
        }),
        [operacoes],
    );

    // ── Formulário ──────────────────────────────────────────────────────────

    const vazio = {
        nome: '',
        area: areas[0] ?? '',
        regiao: '',
        equipes: [] as string[],
        bairros: [] as string[],
        inicio: hojeISO(),
        fim: '',
        situacao: situacoes[0] ?? '',
        foco: '',
        observacao: '',
        fiscais: [] as number[],
        demandas: [] as number[],
    };

    const [form, setForm] = useState({ ...vazio });
    const [buscaDemanda, setBuscaDemanda] = useState('');
    const [buscaFiscal, setBuscaFiscal] = useState('');

    /**
     * Escolher a ÁREA (ou uma equipe) marca sozinho os bairros daquela área — sem
     * restringir: dá para desmarcar um a um depois (dono, 25/09/2026).
     */
    function marcarBairrosDa(area: string) {
        const daArea = bairrosPorArea[area] ?? [];

        setForm((atual) => ({ ...atual, bairros: [...new Set([...atual.bairros, ...daArea])] }));
    }

    /**
     * Desmarcar a área (ou a equipe) tira os bairros dela — menos os que ainda
     * são cobertos pela área escolhida ou por outra equipe marcada (bairro de
     * divisa fica, se a outra área também o tem).
     */
    function desmarcarBairrosDa(area: string, areasQueFicam: string[]) {
        const cobertos = new Set(areasQueFicam.flatMap((a) => bairrosPorArea[a] ?? []));
        const daArea = new Set((bairrosPorArea[area] ?? []).filter((b) => !cobertos.has(b)));

        setForm((atual) => ({ ...atual, bairros: atual.bairros.filter((b) => !daArea.has(b)) }));
    }

    /** As áreas das equipes marcadas, sem esta (a que está saindo). */
    function areasDasEquipes(equipesMarcadas: string[]): string[] {
        return equipesMarcadas.map((codigo) => equipes.find((e) => e.equipe === codigo)?.area).filter((a): a is string => !!a);
    }

    function alternarId(campo: 'fiscais' | 'demandas', id: number) {
        setForm((atual) => ({
            ...atual,
            [campo]: atual[campo].includes(id) ? atual[campo].filter((v) => v !== id) : [...atual[campo], id],
        }));
    }

    function mudar<C extends keyof typeof vazio>(campo: C, valor: (typeof vazio)[C]) {
        setForm((atual) => ({ ...atual, [campo]: valor }));
    }

    function alternarNaLista(campo: 'equipes' | 'bairros', valor: string) {
        setForm((atual) => ({
            ...atual,
            [campo]: atual[campo].includes(valor)
                ? atual[campo].filter((v) => v !== valor)
                : [...atual[campo], valor],
        }));
    }

    function abrirNova() {
        setForm({ ...vazio });
        setAbertaId(null);
        setModo('edicao');
        setAba('operacao');
    }

    function abrirOperacao(operacao: Operacao) {
        setForm({
            nome: operacao.nome,
            area: operacao.area,
            regiao: operacao.regiao,
            equipes: [...operacao.equipes],
            bairros: [...operacao.bairros],
            inicio: operacao.inicio ?? hojeISO(),
            fim: operacao.fim ?? '',
            situacao: operacao.situacao,
            foco: operacao.foco,
            observacao: operacao.observacao,
            fiscais: operacao.fiscais.map((f) => f.id),
            demandas: operacao.demandas.map((d) => d.id),
        });
        setAbertaId(operacao.id);
        setModo('navegacao');
        setAba('operacao');
    }

    function voltarParaLista() {
        setAba('operacoes');
        setModo('navegacao');
    }

    /** O que vai no corpo — `fim` em branco viaja como nulo, não como "". */
    function corpo() {
        return { ...form, fim: form.fim.trim() === '' ? null : form.fim };
    }

    function salvar() {
        if (aberta === null) {
            enviar('salvar', store().url, corpo(), {
                onSuccess: () => {
                    setForm({ ...vazio });
                    voltarParaLista();
                },
            });

            return;
        }

        router.put(update(aberta.id).url, corpo(), {
            ...guardar('salvar', { onSuccess: () => setModo('navegacao') }),
        });
    }

    function excluir() {
        if (aberta === null) {
            return;
        }

        router.delete(destroy(aberta.id).url, {
            ...guardar('excluir', {
                onSuccess: () => {
                    setExcluindo(false);
                    setAbertaId(null);
                    voltarParaLista();
                },
            }),
        });
    }

    /** O líder da equipe que executa a operação, ou null quando não há equipe ou líder. */
    const liderDe = (equipes: string[]): string | null => {
        const primeira = equipes[0];
        const nome = primeira === undefined ? '' : (lideres[primeira]?.nome ?? '');

        return nome.trim() === '' ? null : nome;
    };

    /*
     * ── A GRADE ENXUTA ───────────────────────────────────────────────────────
     *
     * Régua em `docs/padroes/listagem-clean.md`. Quem varre esta lista está
     * procurando QUE operação existe e se ela está aberta — normalmente para
     * anexar trabalho a ela. Nome, área, período, equipes e situação respondem
     * isso em uma linha.
     *
     * O FOCO era a frase que ia embaixo do nome, e é texto livre: desceu para a
     * ficha. Região e bairros desceram com ele — contam o alcance, que interessa
     * depois de escolher a operação, não durante a varredura. Os três continuam
     * no arquivo exportado.
     */
    const listagem = listagens.operacoes;

    /** Cinza de apoio — o mesmo em toda célula que diz "isto não existe". */
    const fraco = { color: 'var(--sm-texto-fraco)' };

    /** O que cada célula desenha, com o texto inteiro para a dica. */
    function celula(o: Operacao, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'nome') {
            return {
                conteudo: o.nome,
                dica: o.foco === '' ? o.nome : `${o.nome} — ${o.foco}`,
            };
        }

        if (chave === 'area') {
            return {
                conteudo: o.area,
                dica: `${o.area} · ${liderDe(o.equipes) ?? 'equipe sem líder registrado'}`,
            };
        }

        if (chave === 'periodo') {
            return { conteudo: o.periodo, dica: o.periodo };
        }

        if (chave === 'equipes') {
            // Texto, e não uma fileira de selos: com quatro equipes a fileira
            // quebrava e a linha crescia. O selo desta linha é a situação.
            return o.equipes.length === 0
                ? {
                      conteudo: <span style={fraco}>sem equipe definida</span>,
                      dica: 'A operação foi planejada antes de a escala sair.',
                  }
                : {
                      conteudo: o.equipes.join(', '),
                      dica: `${o.equipes.length === 1 ? 'Equipe' : 'Equipes'} ${o.equipes.join(', ')}`,
                  };
        }

        return {
            conteudo: (
                <span
                    className={cn('selo', TOM_DA_SITUACAO[o.situacao] ?? 'selo-neutro')}
                >
                    {o.situacao}
                </span>
            ),
            dica: o.encerrada
                ? `${o.situacao} — não recebe denúncia nova`
                : o.situacao,
        };
    }

    // Só as chaves declaradas entram no arquivo, e a data sai em BR: o documento é
    // lido fora do sistema, onde ninguém traduz ISO.
    const linhasExportacao = filtradas.map((o) => ({
        nome: o.nome,
        area: o.area,
        regiao: o.regiao || VAZIO,
        equipes: o.equipes.length === 0 ? VAZIO : o.equipes.join(', '),
        periodo: o.periodo,
        inicio: dataBR(o.inicio),
        fim: dataBR(o.fim),
        situacao: o.situacao,
        // "a área inteira" e não vazio: bairro nenhum listado é uma decisão
        // (a operação varre a área toda), não dado faltando.
        bairros: o.bairros.length === 0 ? 'a área inteira' : o.bairros.join(', '),
        foco: o.foco || VAZIO,
        observacao: o.observacao || VAZIO,
    }));

    /** O formulário está preenchido o suficiente para o servidor aceitar? */
    const prontoParaSalvar =
        form.nome.trim().length >= 5 &&
        form.area.trim() !== '' &&
        form.inicio.trim() !== '' &&
        form.situacao.trim() !== '' &&
        // A mesma conferência que o servidor faz, adiantada aqui: a pessoa
        // descobre o período invertido antes de mandar, e não depois. O servidor
        // continua sendo a fronteira — este é conforto.
        (form.fim.trim() === '' || form.fim >= form.inicio);

    return (
        <>
            <Head title="Cadastro de Operação" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Cadastro de Operação</h1>
                    <p>
                        O trabalho de rua com <strong>começo, fim e foco</strong> que
                        a gestão monta em cima das áreas e equipes — e a que as
                        denúncias podem ser anexadas em vez de gerar ida isolada.
                    </p>

                    <ul className="rt-chips">
                        {cadastra && (
                            <li className="rt-chip" style={{ color: 'var(--sm-primaria)' }}>
                                <span className="rt-chip-dot" />
                                Você monta operação
                                {areasDoLider.length > 0
                                    ? ` · ${areasDoLider.join(' e ')}`
                                    : ''}
                            </li>
                        )}

                        {cadastra && recorteDeArea && areasDoLider.length === 0 && (
                            <li className="rt-chip" style={{ color: 'var(--sm-perigo)' }}>
                                <span className="rt-chip-dot" />
                                Sua conta não está vinculada a nenhuma equipe — procure
                                quem administra o sistema
                            </li>
                        )}

                        {!cadastra && (
                            <li className="rt-chip">
                                <span className="rt-chip-dot" />
                                Você consulta as operações para saber a que anexar a
                                demanda; montar operação é do Chefe de Setor ou do líder
                            </li>
                        )}
                    </ul>
                </div>

                <div className="rt-numeros">
                    <button
                        type="button"
                        className="rt-numero"
                        title="Ver todas as operações"
                        onClick={() => setBusca('')}
                    >
                        <strong>{numeros.total}</strong>
                        <span>{plural(numeros.total, 'operação', 'operações')}</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero info"
                        title="Ver as operações em andamento"
                        onClick={() => setBusca('em andamento')}
                    >
                        <strong>{numeros.emAndamento}</strong>
                        <span>em andamento</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero"
                        title="Ver as operações que ainda não começaram"
                        onClick={() => setBusca('planejadas')}
                    >
                        <strong>{numeros.planejadas}</strong>
                        <span>{plural(numeros.planejadas, 'planejada', 'planejadas')}</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero"
                        title="Ver as operações encerradas — elas não recebem denúncia nova"
                        onClick={() => setBusca('encerradas')}
                    >
                        <strong>{numeros.encerradas}</strong>
                        <span>{plural(numeros.encerradas, 'encerrada', 'encerradas')}</span>
                    </button>
                </div>
            </div>

            {/*
              * O aviso mudou de assunto quando o módulo saiu do protótipo: o que
              * era falso era dizer que nada é gravado — agora tudo é, em banco, e
              * com trâmite. O que continua de mentira são os DADOS, e é disso que
              * quem avalia precisa ser avisado antes de tirar conclusão deles.
              */}
            <SeloPrototipo>
                Ambiente de demonstração: as operações já cadastradas são{' '}
                <strong>exemplos</strong>, não operações reais de rua. O que você
                criar, alterar ou excluir{' '}
                <strong>é gravado de verdade</strong>.
            </SeloPrototipo>

            {/* A informação que faz a tela ser entendida: é o MESMO catálogo que o
                direcionamento das denúncias consome. Sem isto, ninguém liga o
                "encerrada" daqui ao desaparecimento da opção lá. */}
            <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                <Link2 size={16} aria-hidden />
                <div>
                    <strong>
                        É esta lista que aparece no direcionamento das Denúncias.
                    </strong>
                    <div>
                        Quando o Chefe de Setor anexa uma denúncia a uma operação, as
                        opções vêm daqui. Operação{' '}
                        <strong>encerrada não recebe denúncia nova</strong>: ela sai
                        da escolha por lá e continua aqui, para consulta — o
                        histórico é a régua da operação do ano que vem.
                    </div>
                </div>
            </div>

            {recorteDeArea && (
                <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                    <Info size={16} aria-hidden />
                    <div>
                        <strong>
                            Você está vendo as operações de{' '}
                            {areasDoLider.join(' e ')}.
                        </strong>
                        <div>
                            As operações das outras áreas não aparecem aqui — e
                            gravar sobre operação de outra área é recusado pelo
                            sistema, não só escondido.
                        </div>
                    </div>
                </div>
            )}

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Cadastro de Operação">
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'operacoes'}
                        onClick={voltarParaLista}
                    >
                        <List size={16} aria-hidden />
                        <span className="aba-rotulo">Operações</span>
                    </button>

                    {(aberta !== null || (cadastra && acoes.incluir)) && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'operacao'}
                            onClick={aberta === null ? abrirNova : () => setAba('operacao')}
                        >
                            {aberta === null ? (
                                <Plus size={16} aria-hidden />
                            ) : (
                                <Target size={16} aria-hidden />
                            )}
                            <span className="aba-rotulo">
                                {aberta === null ? 'Nova operação' : aberta.nome}
                            </span>
                        </button>
                    )}
                </div>

                {aba === 'operacoes' && (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder='Nome, área, região, equipe, bairro ou foco — ex.: "operações em andamento na orla"'
                            exemplos={[
                                'em andamento',
                                'planejadas',
                                'encerradas',
                                'permanentes',
                            ]}
                        />

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                marginBottom: 14,
                            }}
                        >
                            {cadastra && acoes.incluir && (
                                <BotaoAcao
                                    icone={<Plus size={16} aria-hidden />}
                                    ocupado={ocupado}
                                    onClick={abrirNova}
                                >
                                    Nova operação
                                </BotaoAcao>
                            )}


                            <div style={{ marginLeft: 'auto' }}>
                                <BotaoExportar
                                    titulo="Operações"
                                    subtitulo="Sistema › Cadastro de Operação"
                                    contexto={[
                                        recorteDeArea
                                            ? `Áreas: ${areasDoLider.join(' e ')}`
                                            : 'Todas as áreas',
                                        busca.trim() ? `busca: "${busca.trim()}"` : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                    orientacao="landscape"
                                    colunas={listagem.exportacao}
                                    linhas={linhasExportacao}
                                />
                            </div>
                        </div>

                        <div className="table-wrap">
                            <table className="data-table enxuta">
                                <thead>
                                    <tr>
                                        {/* Cabeçalho e células saem da MESMA lista de
                                            colunas: escritos em dois lugares, uma
                                            coluna nova entra só num deles e a grade
                                            mostra o valor sob o título errado. */}
                                        <CabecaDaGrade grade={listagem.grade} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtradas.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={listagem.grade.length}
                                                className="tabela-vazia"
                                            >
                                                {operacoes.length === 0
                                                    ? 'Nenhuma operação cadastrada. Use "Nova operação" para montar a primeira.'
                                                    : 'Nenhuma operação casa com a busca. Limpe o campo para ver a lista inteira.'}
                                            </td>
                                        </tr>
                                    )}

                                    {filtradas.map((o) => (
                                        <tr
                                            key={o.id}
                                            {...linhaClicavel(
                                                () => abrirOperacao(o),
                                                `Abrir ${o.nome}`,
                                                o.id === abertaId && 'ativo',
                                            )}
                                        >
                                            {listagem.grade.map((coluna) => {
                                                const { conteudo, dica } = celula(
                                                    o,
                                                    coluna.chave,
                                                );

                                                return (
                                                    <Celula
                                                        key={coluna.chave}
                                                        coluna={coluna}
                                                        dica={dica}
                                                    >
                                                        {conteudo}
                                                    </Celula>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {aba === 'operacao' && (
                    <>
                        <div className="rt-detalhe-cabeca">
                            <div>
                                <p className="sobrancelha">
                                    {aberta === null ? 'Nova operação' : aberta.area}
                                </p>
                                <h2 className="card-titulo">
                                    {aberta === null ? 'Montar operação' : aberta.nome}
                                </h2>
                                <p className="card-sub">
                                    {aberta === null
                                        ? 'Diga como a operação se chama, de que área ela é, quem executa e em que período.'
                                        : aberta.periodo}
                                </p>
                            </div>

                            {aberta !== null && modo === 'navegacao' && (
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                    {/* Os botões do registro vêm em cima (dono, 25/09/2026). */}
                                    <button type="button" className="btn btn-secondary btn-sm" onClick={voltarParaLista}>
                                        Voltar à lista
                                    </button>
                                    {cadastra && (
                                    <>
                                    {acoes.habilitado && (
                                        <BotaoAcao
                                            className="btn btn-secondary btn-sm"
                                            icone={<Pencil size={16} aria-hidden />}
                                            ocupado={ocupado}
                                            onClick={() => setModo('edicao')}
                                        >
                                            Editar
                                        </BotaoAcao>
                                    )}

                                    {acoes.excluir && (
                                        <BotaoAcao
                                            className="btn btn-perigo btn-sm"
                                            icone={<Trash2 size={16} aria-hidden />}
                                            ocupado={ocupado}
                                            onClick={() => setExcluindo(true)}
                                        >
                                            Excluir
                                        </BotaoAcao>
                                    )}
                                    </>
                                    )}
                                </div>
                            )}
                        </div>

                        {modo === 'navegacao' && aberta !== null ? (
                            <>
                                <dl className="rt-ficha">
                                    <div>
                                        <dt>Área</dt>
                                        <dd>
                                            {aberta.area}
                                            <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                                {liderDe(aberta.equipes) === null
                                                    ? 'equipe sem líder registrado'
                                                    : `líder ${liderDe(aberta.equipes)}`}
                                            </div>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Equipes</dt>
                                        <dd>
                                            {aberta.equipes.length === 0
                                                ? 'sem equipe definida'
                                                : aberta.equipes.join(', ')}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Início</dt>
                                        <dd>{dataBR(aberta.inicio)}</dd>
                                    </div>
                                    <div>
                                        <dt>Fim</dt>
                                        <dd>
                                            {aberta.fim === null ? (
                                                <span style={{ color: 'var(--sm-texto-fraco)' }}>
                                                    sem data de encerramento — é rotina permanente
                                                </span>
                                            ) : (
                                                dataBR(aberta.fim)
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Situação</dt>
                                        <dd>
                                            <span
                                                className={cn(
                                                    'selo',
                                                    TOM_DA_SITUACAO[aberta.situacao] ??
                                                        'selo-neutro',
                                                )}
                                            >
                                                {aberta.situacao}
                                            </span>
                                            {aberta.encerrada && (
                                                <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                                    não recebe denúncia nova
                                                </div>
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Região alcançada</dt>
                                        <dd>{aberta.regiao === '' ? 'a área inteira' : aberta.regiao}</dd>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <dt>Bairros alcançados</dt>
                                        <dd>
                                            {aberta.bairros.length === 0
                                                ? 'todos os bairros da área'
                                                : aberta.bairros.join(', ')}
                                        </dd>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <dt>Fiscais de outras áreas</dt>
                                        <dd>
                                            {aberta.fiscais.length === 0
                                                ? 'só os das equipes que executam'
                                                : aberta.fiscais.map((f) => f.nome).join(', ')}
                                        </dd>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <dt>Denúncias da operação</dt>
                                        <dd>
                                            {aberta.demandas.length === 0
                                                ? 'nenhuma anexada'
                                                : aberta.demandas.map((d) => `${d.protocolo} (${d.bairro || 'sem bairro'})`).join(', ')}
                                        </dd>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <dt>Foco</dt>
                                        <dd>{aberta.foco === '' ? VAZIO : aberta.foco}</dd>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <dt>Observação</dt>
                                        <dd>
                                            {aberta.observacao === '' ? VAZIO : aberta.observacao}
                                        </dd>
                                    </div>
                                </dl>

                                <div style={{ marginTop: 14 }}>
                                    <button
                                        type="button"
                                        className="btn btn-secondary btn-sm"
                                        onClick={voltarParaLista}
                                    >
                                        Voltar à lista
                                    </button>
                                </div>
                            </>
                        ) : (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    salvar();
                                }}
                            >
                                {/* Os botões vêm também EM CIMA (dono, 25/09/2026). */}
                                <div className="rt-barra-registro">
                                    <BotaoAcao
                                        type="submit"
                                        icone={<CalendarDays size={16} aria-hidden />}
                                        carregando={enviando === 'salvar'}
                                        ocupado={ocupado}
                                        disabled={!prontoParaSalvar}
                                        rotuloCarregando="Salvando…"
                                    >
                                        {aberta === null ? 'Criar operação' : 'Salvar alterações'}
                                    </BotaoAcao>

                                    <button
                                        type="button"
                                        className="btn btn-secondary btn-sm"
                                        disabled={ocupado}
                                        onClick={
                                            aberta === null
                                                ? voltarParaLista
                                                : () => setModo('navegacao')
                                        }
                                    >
                                        Cancelar
                                    </button>
                                </div>

                                <div className="rt-form-linha">
                                    <div className="form-group">
                                        <label className="form-label" htmlFor="nome">
                                            Nome da operação
                                        </label>
                                        <input
                                            id="nome"
                                            type="text"
                                            className="form-control"
                                            data-erro={errors.nome ? '1' : undefined}
                                            value={form.nome}
                                            maxLength={120}
                                            placeholder="Ex.: Operação Verão — Orla"
                                            onChange={(e) => mudar('nome', e.target.value)}
                                        />
                                        {errors.nome ? (
                                            <p className="form-erro">{errors.nome}</p>
                                        ) : (
                                            <p className="form-ajuda">
                                                É por ele que a equipe reconhece a operação em rua e
                                                que a denúncia a registra ao ser anexada.
                                            </p>
                                        )}
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="area">
                                            Área
                                        </label>
                                        <select
                                            id="area"
                                            className="form-control"
                                            data-erro={errors.area ? '1' : undefined}
                                            value={form.area}
                                            onChange={(e) => {
                                                const anterior = form.area;

                                                mudar('area', e.target.value);
                                                // Trocar de área tira os bairros da anterior (menos os que as
                                                // equipes marcadas ainda cobrem) e marca os da nova.
                                                desmarcarBairrosDa(anterior, [e.target.value, ...areasDasEquipes(form.equipes)]);
                                                marcarBairrosDa(e.target.value);
                                            }}
                                        >
                                            {areas.map((a) => (
                                                <option key={a} value={a}>
                                                    {a}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.area ? (
                                            <p className="form-erro">{errors.area}</p>
                                        ) : (
                                            <p className="form-ajuda">
                                                Obrigatória: é a área que decide quem vê a operação
                                                e quem a executa.
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="rt-form-linha">
                                    <div className="form-group">
                                        <label className="form-label" htmlFor="inicio">
                                            Início
                                        </label>
                                        <input
                                            id="inicio"
                                            type="date"
                                            className="form-control"
                                            data-erro={errors.inicio ? '1' : undefined}
                                            value={form.inicio}
                                            onChange={(e) => mudar('inicio', e.target.value)}
                                        />
                                        {errors.inicio && (
                                            <p className="form-erro">{errors.inicio}</p>
                                        )}
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="fim">
                                            Fim (opcional)
                                        </label>
                                        <input
                                            id="fim"
                                            type="date"
                                            className="form-control"
                                            data-erro={
                                                errors.fim ||
                                                (form.fim !== '' && form.fim < form.inicio)
                                                    ? '1'
                                                    : undefined
                                            }
                                            value={form.fim}
                                            min={form.inicio}
                                            onChange={(e) => mudar('fim', e.target.value)}
                                        />
                                        {errors.fim ? (
                                            <p className="form-erro">{errors.fim}</p>
                                        ) : form.fim !== '' && form.fim < form.inicio ? (
                                            <p className="form-erro">
                                                O fim não pode ser antes do início — assim a
                                                operação apareceria encerrada antes de começar.
                                            </p>
                                        ) : (
                                            <p className="form-ajuda">
                                                Em branco significa rotina permanente, sem data de
                                                encerramento.
                                            </p>
                                        )}
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="situacao">
                                            Situação
                                        </label>
                                        <select
                                            id="situacao"
                                            className="form-control"
                                            value={form.situacao}
                                            onChange={(e) => mudar('situacao', e.target.value)}
                                        >
                                            {situacoes.map((s) => (
                                                <option key={s} value={s}>
                                                    {s}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="form-ajuda">
                                            Encerrada deixa de receber denúncia nova no
                                            direcionamento, e continua consultável aqui.
                                        </p>
                                    </div>
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="regiao">
                                        Região alcançada (opcional)
                                    </label>
                                    <input
                                        id="regiao"
                                        type="text"
                                        className="form-control"
                                        value={form.regiao}
                                        maxLength={120}
                                        placeholder="Ex.: Orla de Itapuã a Boca do Rio"
                                        onChange={(e) => mudar('regiao', e.target.value)}
                                    />
                                </div>

                                {/* As EQUIPES em caixas, e não num select múltiplo:
                                    são poucas e cada uma precisa mostrar de que área
                                    é — escolher "N1" sem saber que é a Noturna é
                                    escolher às cegas. */}
                                <div className="form-group">
                                    <label className="form-label">Equipes que executam</label>
                                    <div className="rt-marcadores">
                                        {equipes.map((e) => (
                                            <label key={e.equipe} className="rt-marcador">
                                                <input
                                                    type="checkbox"
                                                    checked={form.equipes.includes(e.equipe)}
                                                    onChange={() => {
                                                        if (!form.equipes.includes(e.equipe)) {
                                                            marcarBairrosDa(e.area);
                                                        } else {
                                                            desmarcarBairrosDa(e.area, [
                                                                form.area,
                                                                ...areasDasEquipes(form.equipes.filter((x) => x !== e.equipe)),
                                                            ]);
                                                        }

                                                        alternarNaLista('equipes', e.equipe);
                                                    }}
                                                />
                                                <span>
                                                    <strong>{e.equipe}</strong> · {e.area} —{' '}
                                                    {e.encarregado}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                    <p className="form-ajuda">
                                        Mais de uma é caso normal: operação grande junta equipe de
                                        outra área como reforço.
                                    </p>
                                </div>

                                <div className="form-group">
                                    <label className="form-label">
                                        Bairros alcançados (opcional)
                                    </label>
                                    <div className="rt-marcadores">
                                        {bairros.map((b) => (
                                            <label key={b} className="rt-marcador">
                                                <input
                                                    type="checkbox"
                                                    checked={form.bairros.includes(b)}
                                                    onChange={() => alternarNaLista('bairros', b)}
                                                />
                                                <span>{b}</span>
                                            </label>
                                        ))}
                                    </div>
                                    <p className="form-ajuda">
                                        Escolher a área ou uma equipe marca os bairros dela; desmarcá-la
                                        tira esses bairros. Dá para desmarcar um a um. Nenhum marcado
                                        significa a área inteira.
                                    </p>
                                </div>

                                {/* FISCAIS de qualquer área (dono, 25/09/2026): a fiscalização da
                                    operação chega a eles além dos fiscais das equipes acima. */}
                                <div className="form-group">
                                    <label className="form-label">
                                        Fiscais de outras áreas (opcional) — {form.fiscais.length}
                                    </label>
                                    <input
                                        className="form-control"
                                        style={{ marginBottom: 8 }}
                                        value={buscaFiscal}
                                        placeholder="Procure o fiscal pelo nome ou pela equipe"
                                        aria-label="Procurar fiscal"
                                        onChange={(e) => setBuscaFiscal(e.target.value)}
                                    />
                                    <div className="rt-marcadores" style={{ maxHeight: 220, overflow: 'auto' }}>
                                        {fiscaisDisponiveis
                                            .filter((f) => form.fiscais.includes(f.id) || casaTermos(parseConsulta<never>(buscaFiscal, []).termos, [f.nome, ...f.equipes]))
                                            .map((f) => (
                                                <label key={f.id} className="rt-marcador">
                                                    <input
                                                        type="checkbox"
                                                        checked={form.fiscais.includes(f.id)}
                                                        onChange={() => alternarId('fiscais', f.id)}
                                                    />
                                                    <span>
                                                        {f.nome}
                                                        {f.equipes.length > 0 && <span style={{ color: 'var(--sm-texto-fraco)' }}> · {f.equipes.join(', ')}</span>}
                                                    </span>
                                                </label>
                                            ))}
                                    </div>
                                    <p className="form-ajuda">
                                        A fiscalização desta operação aparece para os fiscais das equipes que
                                        executam e também para os marcados aqui.
                                    </p>
                                </div>

                                {/* DENÚNCIAS da operação (dono, 25/09/2026): anexadas aqui mesmo, no
                                    cadastro ou na edição — a fiscalização delas vai para a rua junto. */}
                                <div className="form-group">
                                    <label className="form-label">
                                        Denúncias da operação (opcional) — {form.demandas.length}
                                    </label>
                                    <input
                                        className="form-control"
                                        style={{ marginBottom: 8 }}
                                        value={buscaDemanda}
                                        placeholder="Procure por protocolo, bairro, área ou assunto"
                                        aria-label="Procurar denúncia"
                                        onChange={(e) => setBuscaDemanda(e.target.value)}
                                    />
                                    <div className="rt-marcadores" style={{ maxHeight: 260, overflow: 'auto' }}>
                                        {demandasDisponiveis
                                            .filter((d) => d.operacao_id === null || d.operacao_id === aberta?.id || form.demandas.includes(d.id))
                                            .filter(
                                                (d) =>
                                                    form.demandas.includes(d.id) ||
                                                    (buscaDemanda.trim() === ''
                                                        ? d.area === form.area
                                                        : casaTermos(parseConsulta<never>(buscaDemanda, []).termos, [d.protocolo, d.bairro, d.area, d.assunto])),
                                            )
                                            .map((d) => (
                                                <label key={d.id} className="rt-marcador" title={d.assunto}>
                                                    <input
                                                        type="checkbox"
                                                        checked={form.demandas.includes(d.id)}
                                                        onChange={() => alternarId('demandas', d.id)}
                                                    />
                                                    <span>
                                                        <strong>{d.protocolo}</strong> · {d.bairro || 'sem bairro'}
                                                        <span style={{ color: 'var(--sm-texto-fraco)' }}> — {d.situacao}</span>
                                                    </span>
                                                </label>
                                            ))}
                                    </div>
                                    <p className="form-ajuda">
                                        Sem busca, aparecem as denúncias abertas da área escolhida. Anexada, a
                                        denúncia passa a "Em operação" e vai para a rua com as equipes da
                                        operação; desmarcada, volta para a mesa de onde veio.
                                    </p>
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="foco">
                                        Foco / objetivo (opcional)
                                    </label>
                                    <textarea
                                        id="foco"
                                        className="form-control"
                                        rows={2}
                                        maxLength={300}
                                        value={form.foco}
                                        placeholder="O que a equipe vai procurar nesta operação"
                                        onChange={(e) => mudar('foco', e.target.value)}
                                    />
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="observacao">
                                        Observação (opcional)
                                    </label>
                                    <textarea
                                        id="observacao"
                                        className="form-control"
                                        rows={2}
                                        maxLength={600}
                                        value={form.observacao}
                                        placeholder="Horário, reforço de equipe, quem pediu a operação"
                                        onChange={(e) => mudar('observacao', e.target.value)}
                                    />
                                </div>

                                <div className="rt-barra-registro rt-barra-registro-pe">
                                    <BotaoAcao
                                        type="submit"
                                        icone={<CalendarDays size={16} aria-hidden />}
                                        carregando={enviando === 'salvar'}
                                        ocupado={ocupado}
                                        disabled={!prontoParaSalvar}
                                        rotuloCarregando="Salvando…"
                                    >
                                        {aberta === null ? 'Criar operação' : 'Salvar alterações'}
                                    </BotaoAcao>

                                    <button
                                        type="button"
                                        className="btn btn-secondary btn-sm"
                                        disabled={ocupado}
                                        onClick={
                                            aberta === null
                                                ? voltarParaLista
                                                : () => setModo('navegacao')
                                        }
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        )}
                    </>
                )}
            </div>

            {excluindo && aberta !== null && (
                <ModalConfirm
                    titulo={`Excluir ${aberta.nome}?`}
                    mensagem={
                        <>
                            A operação sai do cadastro e deixa de aparecer no
                            direcionamento das denúncias. Se ela já aconteceu, prefira{' '}
                            <strong>encerrá-la</strong>: o histórico é a régua da
                            operação do ano que vem.
                        </>
                    }
                    rotuloConfirmar="Excluir operação"
                    iconeConfirmar={<Trash2 size={16} aria-hidden />}
                    processando={enviando === 'excluir'}
                    onCancelar={() => setExcluindo(false)}
                    onConfirmar={excluir}
                />
            )}

            {/* O caminho de volta quando a lista está vazia por recorte: dizer que
                não há operação da sua área é diferente de não haver operação. */}
            {operacoes.length === 0 && recorteDeArea && (
                <p className="form-ajuda" style={{ marginTop: 14 }}>
                    <MapPinned size={14} aria-hidden /> Não há operação registrada em{' '}
                    {areasDoLider.join(' e ')}. As operações das outras áreas existem
                    e não aparecem aqui.
                </p>
            )}
        </>
    );
}

CadastroDeOperacao.layout = {
    breadcrumbs: [{ title: 'Cadastro de Operação', href: index() }],
};

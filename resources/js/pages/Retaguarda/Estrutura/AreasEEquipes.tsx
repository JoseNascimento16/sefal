import { Head, router } from '@inertiajs/react';
import {
    Info,
    List,
    Map as MapIcon,
    MapPinned,
    Moon,
    Pencil,
    Plus,
    RotateCcw,
    Route as RouteIcon,
    Trash2,
    Undo2,
    UserRound,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import type { Area, Recorte } from '@/dados-prototipo/administrativo';
import { RECORTES } from '@/dados-prototipo/administrativo';
import { useAcoes } from '@/hooks/use-acoes';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { contar, plural } from '@/lib/plural';
import { cn } from '@/lib/utils';
import {
    bairros as rotaBairros,
    destroy,
    index,
    reiniciar as rotaReiniciar,
    store,
    update,
} from '@/routes/retaguarda/areas-e-equipes';

/**
 * Áreas e Equipes — PROTÓTIPO da estrutura permanente de fiscalização.
 *
 * "A operação é evento; a equipe é organização." Esta é a divisão com que a
 * SEMOP cobre a cidade — Área › Equipe › encarregado › fiscais › bloco de
 * bairros — e é dela que sai a derivação bairro → equipe que a Caixa de Entrada
 * usa para sugerir o destino de cada demanda.
 *
 * ── Três recortes, e não um ─────────────────────────────────────────────────
 *
 * Seis áreas cobrem BLOCOS DE BAIRROS; a Itinerante cobre CORREDORES; a Noturna
 * cobre a CIDADE INTEIRA e o recorte dela é o TURNO. A tela desenha cada uma
 * pelo que ela é: mostrar a Noturna com "0 bairros" seria a leitura exatamente
 * invertida — ela cobre todos.
 *
 * ── Bairro em duas áreas é aviso, nunca erro ────────────────────────────────
 *
 * O vínculo bairro↔equipe não é 1:1: a Caixa de Entrada SUGERE e o
 * administrativo CONFIRMA. Marcar isso como pendência mandaria o gestor
 * "corrigir" um dado que está certo.
 */

interface Props {
    areas: Area[];
    turnos: string[];
    bairros: string[];
    /** A sessão já mexeu na estrutura de demonstração? */
    alterada: boolean;
}

type Aba = 'areas' | 'area';
type Modo = 'navegacao' | 'edicao';

type Faceta =
    | { tipo: 'compartilhado' }
    | { tipo: 'recorte'; valor: Recorte };

const FACETAS: { expressao: RegExp; valor: Faceta }[] = [
    { expressao: /\bcompartilhad\w*\b|\bem duas areas\b/, valor: { tipo: 'compartilhado' } },
    { expressao: /\bcorredor\w*\b|\bitinerante\b/, valor: { tipo: 'recorte', valor: 'corredores' } },
    { expressao: /\bnoturn\w*\b/, valor: { tipo: 'recorte', valor: 'cidade' } },
];

/** O ícone de cada recorte — a diferença tem de ser vista, não só lida. */
function IconeDoRecorte({ recorte }: { recorte: Recorte }) {
    if (recorte === 'corredores') {
        return <RouteIcon size={16} aria-hidden />;
    }

    if (recorte === 'cidade') {
        return <Moon size={16} aria-hidden />;
    }

    return <MapPinned size={16} aria-hidden />;
}

export default function AreasEEquipes({ areas, turnos, bairros, alterada }: Props) {
    const acoes = useAcoes();
    const { enviando, ocupado, enviar, guardar } = useEnvio();

    const [aba, setAba] = useState<Aba>('areas');
    const [modo, setModo] = useState<Modo>('navegacao');
    const [busca, setBusca] = useState('');
    const [abertaId, setAbertaId] = useState<number | null>(null);
    const [excluindo, setExcluindo] = useState(false);
    const [novoBairro, setNovoBairro] = useState('');

    const aberta = areas.find((a) => a.id === abertaId) ?? null;

    // ── Filtro ──────────────────────────────────────────────────────────────

    const filtradas = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return areas.filter((a) => {
            for (const faceta of facetas) {
                if (faceta.tipo === 'compartilhado' && a.bairros_compartilhados.length === 0) {
                    return false;
                }

                if (faceta.tipo === 'recorte' && a.recorte !== faceta.valor) {
                    return false;
                }
            }

            return casaTermos(termos, [
                a.nome,
                a.regiao,
                a.equipe,
                a.encarregado,
                a.bairros.join(' '),
                a.fiscais.map((f) => `${f.nome} ${f.matricula}`).join(' '),
            ]);
        });
    }, [areas, busca]);

    // Os números saem da MESMA lista que os cartões desenham.
    const numeros = useMemo(() => {
        const distintos = new Set<string>();
        const compartilhados = new Set<string>();

        for (const area of areas) {
            for (const bairro of area.bairros) {
                distintos.add(bairro.toLocaleLowerCase('pt-BR'));
            }

            for (const bairro of area.bairros_compartilhados) {
                compartilhados.add(bairro.toLocaleLowerCase('pt-BR'));
            }
        }

        return {
            areas: areas.length,
            fiscais: areas.reduce((total, a) => total + a.total_fiscais, 0),
            bairros: distintos.size,
            compartilhados: compartilhados.size,
        };
    }, [areas]);

    // ── Formulário da área ──────────────────────────────────────────────────

    const vazio = {
        nome: '',
        regiao: '',
        equipe: '',
        encarregado: '',
        recorte: 'bairros' as Recorte,
        turno: turnos[0] ?? '',
    };

    const [form, setForm] = useState({ ...vazio });

    function mudar<C extends keyof typeof vazio>(campo: C, valor: (typeof vazio)[C]) {
        setForm((atual) => ({ ...atual, [campo]: valor }));
    }

    function abrirNova() {
        setForm({ ...vazio });
        setAbertaId(null);
        setModo('edicao');
        setAba('area');
    }

    function abrirArea(area: Area) {
        setForm({
            nome: area.nome,
            regiao: area.regiao,
            equipe: area.equipe,
            encarregado: area.encarregado,
            recorte: area.recorte,
            turno: area.turno,
        });
        setAbertaId(area.id);
        setModo('navegacao');
        setNovoBairro('');
        setAba('area');
    }

    function voltarParaLista() {
        setAba('areas');
        setModo('navegacao');
    }

    function salvar() {
        if (aberta === null) {
            enviar('salvar', store().url, form, {
                onSuccess: () => {
                    setForm({ ...vazio });
                    voltarParaLista();
                },
            });

            return;
        }

        router.put(update(aberta.id).url, form, {
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

    function mexerNoBairro(acao: 'adicionar' | 'remover', bairro: string) {
        if (aberta === null || bairro.trim() === '') {
            return;
        }

        enviar(
            `bairro-${acao}`,
            rotaBairros(aberta.id).url,
            { acao, bairro: bairro.trim() },
            { onSuccess: () => setNovoBairro('') },
        );
    }

    const linhasExportacao = filtradas.map((a) => ({
        nome: a.nome,
        regiao: a.regiao,
        equipe: a.equipe,
        encarregado: a.encarregado,
        recorte: RECORTES[a.recorte].rotulo,
        turno: a.turno,
        fiscais: String(a.total_fiscais),
        qtd_bairros:
            a.recorte === 'cidade' ? 'todos' : String(a.total_bairros),
        bairros:
            a.recorte === 'cidade'
                ? 'Todos os bairros de Salvador'
                : a.bairros.join(', '),
    }));

    return (
        <>
            <Head title="Áreas e Equipes" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Estrutura</p>
                    <h1>Áreas e Equipes</h1>
                    <p>
                        Como a fiscalização se organiza para cobrir a cidade:{' '}
                        <strong>Área › Equipe › encarregado › fiscais › bloco
                        de bairros</strong>. É desta estrutura que sai a equipe
                        sugerida para cada demanda da Caixa de Entrada.
                    </p>
                </div>

                <div className="rt-numeros">
                    <div className="rt-numero">
                        <strong>{numeros.areas}</strong>
                        <span>{plural(numeros.areas, 'área', 'áreas')}</span>
                    </div>
                    <div className="rt-numeros-separador" />
                    <div className="rt-numero info">
                        <strong>{numeros.fiscais}</strong>
                        <span>{plural(numeros.fiscais, 'fiscal', 'fiscais')}</span>
                    </div>
                    <div className="rt-numeros-separador" />
                    {/* "bairros e corredores" e não só "bairros": a Itinerante
                        cobre eixos (Avenida Sete, Comércio, Joana Angélica), e
                        eles entram nesta conta — chamar tudo de bairro faria o
                        número não bater com a soma dos blocos. */}
                    <div className="rt-numero">
                        <strong>{numeros.bairros}</strong>
                        <span>bairros e corredores</span>
                    </div>
                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero alerta"
                        title="Ver as áreas que dividem bairro com outra"
                        onClick={() => setBusca('compartilhados')}
                    >
                        <strong>{numeros.compartilhados}</strong>
                        <span>em duas áreas</span>
                    </button>
                </div>
            </div>

            <SeloPrototipo>
                A estrutura vem transcrita do documento{' '}
                <strong>"Áreas das equipes — 17/04/2026"</strong>: as áreas, as
                equipes, os encarregados e os blocos de bairros são os REAIS. A
                lista de fiscais de cada equipe é de exemplo, e{' '}
                <strong>nada é gravado</strong>: o que você alterar vale só nesta
                sessão do navegador.
            </SeloPrototipo>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Áreas e Equipes">
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'areas'}
                        onClick={voltarParaLista}
                    >
                        <List size={16} aria-hidden />
                        <span className="aba-rotulo">Áreas</span>
                    </button>

                    {(aberta !== null || acoes.incluir) && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'area'}
                            onClick={aberta === null ? abrirNova : () => setAba('area')}
                        >
                            {aberta === null ? (
                                <Plus size={16} aria-hidden />
                            ) : (
                                <MapIcon size={16} aria-hidden />
                            )}
                            <span className="aba-rotulo">
                                {aberta === null
                                    ? 'Nova área'
                                    : `${aberta.nome} · Equipe ${aberta.equipe}`}
                            </span>
                        </button>
                    )}
                </div>

                {aba === 'areas' && (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder='Área, região, equipe, encarregado, fiscal ou bairro — ex.: "áreas com bairro compartilhado"'
                            exemplos={[
                                'compartilhados',
                                'itinerante',
                                'noturna',
                                'brotas',
                                'mussurunga',
                            ]}
                        />

                        {numeros.compartilhados > 0 && (
                            <div className="rt-sugestao" style={{ marginBottom: 14 }}>
                                <Info size={16} aria-hidden />
                                <div>
                                    <strong>
                                        {contar(numeros.compartilhados, 'bairro', 'bairros')}{' '}
                                        {plural(
                                            numeros.compartilhados,
                                            'pertence',
                                            'pertencem',
                                        )}{' '}
                                        a mais de uma área.
                                    </strong>{' '}
                                    Não é erro a corrigir: o vínculo bairro↔equipe
                                    não é exclusivo. Nesses casos a Caixa de
                                    Entrada <strong>sugere</strong> uma equipe e o
                                    administrativo <strong>confirma</strong>.
                                </div>
                            </div>
                        )}

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                marginBottom: 14,
                            }}
                        >
                            {acoes.incluir && (
                                <BotaoAcao
                                    icone={<Plus size={16} aria-hidden />}
                                    ocupado={ocupado}
                                    onClick={abrirNova}
                                >
                                    Nova área
                                </BotaoAcao>
                            )}

                            {alterada && acoes.habilitado && (
                                <BotaoAcao
                                    className="btn btn-secondary btn-sm"
                                    icone={<RotateCcw size={16} aria-hidden />}
                                    carregando={enviando === 'reiniciar'}
                                    ocupado={ocupado}
                                    rotuloCarregando="Reiniciando…"
                                    onClick={() => enviar('reiniciar', rotaReiniciar().url)}
                                >
                                    Reiniciar demonstração
                                </BotaoAcao>
                            )}

                            <div style={{ marginLeft: 'auto' }}>
                                <BotaoExportar
                                    titulo="Áreas e Equipes"
                                    subtitulo="Estrutura › Áreas e Equipes"
                                    contexto={
                                        busca.trim()
                                            ? `Busca: "${busca.trim()}"`
                                            : 'Estrutura completa'
                                    }
                                    orientacao="landscape"
                                    colunas={[
                                        { chave: 'nome', titulo: 'Área' },
                                        { chave: 'regiao', titulo: 'Região' },
                                        { chave: 'equipe', titulo: 'Equipe' },
                                        { chave: 'encarregado', titulo: 'Encarregado' },
                                        { chave: 'recorte', titulo: 'Recorte' },
                                        { chave: 'turno', titulo: 'Turno' },
                                        { chave: 'fiscais', titulo: 'Fiscais', alinhar: 'center' },
                                        { chave: 'qtd_bairros', titulo: 'Bairros', alinhar: 'center' },
                                        { chave: 'bairros', titulo: 'Bloco de bairros' },
                                    ]}
                                    linhas={linhasExportacao}
                                />
                            </div>
                        </div>

                        {filtradas.length === 0 ? (
                            <p className="tabela-vazia">
                                {areas.length === 0
                                    ? 'Nenhuma área cadastrada. Use "Nova área" para começar a estrutura.'
                                    : 'Nenhuma área casa com a busca. Limpe o campo para ver a estrutura inteira.'}
                            </p>
                        ) : (
                            <div className="rt-cartoes">
                                {filtradas.map((area) => (
                                    <button
                                        key={area.id}
                                        type="button"
                                        className={cn(
                                            'rt-cartao-area',
                                            area.id === abertaId && 'ativo',
                                        )}
                                        title={`Abrir ${area.nome}`}
                                        onClick={() => abrirArea(area)}
                                    >
                                        <div className="rt-cartao-area-topo">
                                            <h3>{area.nome}</h3>
                                            <span className="selo selo-neutro">
                                                Equipe {area.equipe}
                                            </span>
                                        </div>

                                        <span className="rt-cartao-area-regiao">
                                            {area.regiao}
                                        </span>

                                        <span
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 6,
                                                fontSize: 12.5,
                                                color: 'var(--sm-texto-corpo)',
                                            }}
                                        >
                                            <UserRound size={14} aria-hidden />{' '}
                                            {area.encarregado}
                                        </span>

                                        <span
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 6,
                                                fontSize: 12,
                                                color: 'var(--sm-texto-fraco)',
                                            }}
                                        >
                                            <IconeDoRecorte recorte={area.recorte} />{' '}
                                            {RECORTES[area.recorte].rotulo}
                                            {area.recorte === 'cidade'
                                                ? ` · ${area.turno}`
                                                : ''}
                                        </span>

                                        <div className="rt-cartao-area-numeros">
                                            {/* A equipe da cidade inteira não
                                                tem contagem: dizer "todos os
                                                bairros" é a informação certa, e
                                                "153 bairros" seria um número
                                                que ninguém mantém. */}
                                            <span>
                                                {area.recorte === 'cidade' ? (
                                                    <>
                                                        <strong>todos</strong> os
                                                        bairros
                                                    </>
                                                ) : (
                                                    <>
                                                        <strong>
                                                            {area.total_bairros}
                                                        </strong>{' '}
                                                        {area.recorte === 'corredores'
                                                            ? plural(
                                                                  area.total_bairros,
                                                                  'corredor',
                                                                  'corredores',
                                                              )
                                                            : plural(
                                                                  area.total_bairros,
                                                                  'bairro',
                                                                  'bairros',
                                                              )}
                                                    </>
                                                )}
                                            </span>
                                            <span>
                                                <strong>{area.total_fiscais}</strong>{' '}
                                                {plural(
                                                    area.total_fiscais,
                                                    'fiscal',
                                                    'fiscais',
                                                )}
                                            </span>
                                            {area.bairros_compartilhados.length > 0 && (
                                                <span
                                                    style={{
                                                        color: 'var(--sm-aviso)',
                                                    }}
                                                >
                                                    <strong>
                                                        {
                                                            area.bairros_compartilhados
                                                                .length
                                                        }
                                                    </strong>{' '}
                                                    em duas áreas
                                                </span>
                                            )}
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                    </>
                )}

                {aba === 'area' && (
                    <>
                        <div className="rt-detalhe-cabeca">
                            <div>
                                <p className="sobrancelha">
                                    {aberta === null
                                        ? 'Nova área'
                                        : `Equipe ${aberta.equipe}`}
                                </p>
                                <h2 className="card-titulo">
                                    {aberta === null
                                        ? 'Criar área e equipe'
                                        : `${aberta.nome} — ${aberta.regiao}`}
                                </h2>
                                <p className="card-sub">
                                    {aberta === null
                                        ? 'Diga como a área se chama, que região ela cobre e como ela recorta a cidade.'
                                        : RECORTES[aberta.recorte].explicacao}
                                </p>
                            </div>

                            {aberta !== null && modo === 'navegacao' && (
                                <div style={{ display: 'flex', gap: 8 }}>
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
                                </div>
                            )}
                        </div>

                        {modo === 'navegacao' && aberta !== null ? (
                            <dl className="rt-ficha">
                                <div>
                                    <dt>Equipe</dt>
                                    <dd>{aberta.equipe}</dd>
                                </div>
                                <div>
                                    <dt>Encarregado</dt>
                                    <dd>{aberta.encarregado}</dd>
                                </div>
                                <div>
                                    <dt>Recorte</dt>
                                    <dd>{RECORTES[aberta.recorte].rotulo}</dd>
                                </div>
                                <div>
                                    <dt>Turno</dt>
                                    <dd>{aberta.turno}</dd>
                                </div>
                            </dl>
                        ) : (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    salvar();
                                }}
                            >
                                <div className="rt-form-linha">
                                    <div className="form-group">
                                        <label className="form-label" htmlFor="nome">
                                            Nome da área
                                        </label>
                                        <input
                                            id="nome"
                                            type="text"
                                            className="form-control"
                                            value={form.nome}
                                            maxLength={60}
                                            placeholder="Ex.: Área 7"
                                            onChange={(e) => mudar('nome', e.target.value)}
                                        />
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="regiao">
                                            Região
                                        </label>
                                        <input
                                            id="regiao"
                                            type="text"
                                            className="form-control"
                                            value={form.regiao}
                                            maxLength={60}
                                            placeholder="Ex.: Subúrbio Ferroviário"
                                            onChange={(e) => mudar('regiao', e.target.value)}
                                        />
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="equipe">
                                            Código da equipe
                                        </label>
                                        <input
                                            id="equipe"
                                            type="text"
                                            className="form-control"
                                            value={form.equipe}
                                            maxLength={6}
                                            placeholder="Ex.: D1"
                                            onChange={(e) =>
                                                mudar('equipe', e.target.value.toUpperCase())
                                            }
                                        />
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="encarregado">
                                            Encarregado
                                        </label>
                                        <input
                                            id="encarregado"
                                            type="text"
                                            className="form-control"
                                            value={form.encarregado}
                                            maxLength={120}
                                            onChange={(e) =>
                                                mudar('encarregado', e.target.value)
                                            }
                                        />
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="recorte">
                                            Como a área recorta a cidade
                                        </label>
                                        <select
                                            id="recorte"
                                            className="form-control"
                                            value={form.recorte}
                                            onChange={(e) =>
                                                mudar('recorte', e.target.value as Recorte)
                                            }
                                        >
                                            {(
                                                Object.keys(RECORTES) as Recorte[]
                                            ).map((chave) => (
                                                <option key={chave} value={chave}>
                                                    {RECORTES[chave].rotulo}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="form-ajuda">
                                            {RECORTES[form.recorte].explicacao}
                                        </p>
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="turno">
                                            Turno
                                        </label>
                                        <select
                                            id="turno"
                                            className="form-control"
                                            value={form.turno}
                                            onChange={(e) => mudar('turno', e.target.value)}
                                        >
                                            {turnos.map((t) => (
                                                <option key={t} value={t}>
                                                    {t}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div className="sobreposicao-acoes" style={{ marginTop: 18 }}>
                                    <button
                                        type="button"
                                        className="btn btn-secondary btn-sm"
                                        disabled={ocupado}
                                        onClick={() => {
                                            if (aberta === null) {
                                                voltarParaLista();

                                                return;
                                            }

                                            // Volta ao que está gravado: sair da
                                            // edição sem restaurar deixaria a
                                            // ficha mostrando o rascunho.
                                            abrirArea(aberta);
                                        }}
                                    >
                                        <Undo2 size={16} aria-hidden /> Cancelar
                                    </button>

                                    <BotaoAcao
                                        type="submit"
                                        carregando={enviando === 'salvar'}
                                        ocupado={ocupado}
                                        rotuloCarregando="Salvando…"
                                    >
                                        {aberta === null ? 'Criar área' : 'Salvar alterações'}
                                    </BotaoAcao>
                                </div>
                            </form>
                        )}

                        {aberta !== null && (
                            <>
                                <hr className="rt-regua" />

                                <h3 className="card-titulo">
                                    Fiscais da Equipe {aberta.equipe}
                                </h3>
                                <p className="card-sub">
                                    Quem vai a campo por esta área. O encarregado
                                    responde pela equipe.
                                </p>

                                {aberta.fiscais.length === 0 ? (
                                    <p className="tabela-vazia">
                                        Nenhum fiscal alocado nesta equipe.
                                    </p>
                                ) : (
                                    <div className="table-wrap">
                                        <table className="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Matrícula</th>
                                                    <th>Fiscal</th>
                                                    <th>Papel</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {aberta.fiscais.map((f) => (
                                                    <tr key={f.matricula}>
                                                        <td className="cell-id">
                                                            {f.matricula}
                                                        </td>
                                                        <td>{f.nome}</td>
                                                        <td>
                                                            <span className="selo selo-neutro">
                                                                Fiscal
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}

                                <hr className="rt-regua" />

                                {/* O título acompanha o RECORTE: "Bloco de
                                    bairros" na equipe da cidade inteira
                                    contradiria o texto logo abaixo, que diz que
                                    ela não tem bloco. E o subtítulo diz o que a
                                    explicação do topo não diz — o efeito de mexer
                                    aqui —, em vez de repeti-la. */}
                                <h3 className="card-titulo">
                                    {aberta.recorte === 'cidade'
                                        ? 'Cobertura'
                                        : aberta.recorte === 'corredores'
                                          ? 'Corredores da equipe'
                                          : 'Bloco de bairros'}
                                </h3>

                                {aberta.recorte === 'bairros' && (
                                    <p className="card-sub">
                                        Tirar ou acrescentar um bairro aqui muda a
                                        equipe sugerida para as demandas desse
                                        bairro na Caixa de Entrada.
                                    </p>
                                )}

                                {aberta.recorte === 'corredores' && (
                                    <p className="card-sub">
                                        Os eixos de grande circulação que esta
                                        equipe percorre.
                                    </p>
                                )}

                                {aberta.recorte === 'cidade' ? (
                                    <div className="rt-sugestao">
                                        <Info size={16} aria-hidden />
                                        <div>
                                            <strong>
                                                Esta equipe cobre todos os bairros
                                                de Salvador.
                                            </strong>{' '}
                                            Não há bloco a manter: o recorte dela é
                                            o turno ({aberta.turno}), e é por ele
                                            que a demanda chega até ela.
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        {aberta.bairros_compartilhados.length > 0 && (
                                            <div className="rt-sugestao">
                                                <Info size={16} aria-hidden />
                                                <div>
                                                    <strong>
                                                        {contar(
                                                            aberta
                                                                .bairros_compartilhados
                                                                .length,
                                                            'bairro deste bloco também pertence',
                                                            'bairros deste bloco também pertencem',
                                                        )}{' '}
                                                        a outra área
                                                    </strong>{' '}
                                                    (
                                                    {aberta.bairros_compartilhados.join(
                                                        ', ',
                                                    )}
                                                    ). É caso previsto, não
                                                    duplicidade a corrigir: a Caixa
                                                    de Entrada sugere a equipe e o
                                                    administrativo confirma.
                                                </div>
                                            </div>
                                        )}

                                        <ul className="rt-chips">
                                            {aberta.bairros.length === 0 && (
                                                <li
                                                    style={{
                                                        fontSize: 13,
                                                        color: 'var(--sm-texto-fraco)',
                                                    }}
                                                >
                                                    Bloco vazio — nenhuma demanda
                                                    será sugerida a esta equipe.
                                                </li>
                                            )}

                                            {aberta.bairros.map((bairro) => (
                                                <li
                                                    key={bairro}
                                                    className={cn(
                                                        'rt-chip',
                                                        aberta.bairros_compartilhados.includes(
                                                            bairro,
                                                        ) && 'compartilhado',
                                                    )}
                                                >
                                                    {bairro}
                                                    {acoes.habilitado && (
                                                        <button
                                                            type="button"
                                                            title={`Tirar ${bairro} do bloco`}
                                                            aria-label={`Tirar ${bairro} do bloco desta área`}
                                                            disabled={ocupado}
                                                            onClick={() =>
                                                                mexerNoBairro(
                                                                    'remover',
                                                                    bairro,
                                                                )
                                                            }
                                                        >
                                                            <X size={12} aria-hidden />
                                                        </button>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>

                                        {acoes.habilitado && (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'flex-end',
                                                    gap: 10,
                                                    marginTop: 16,
                                                    flexWrap: 'wrap',
                                                }}
                                            >
                                                <div
                                                    className="form-group"
                                                    style={{ margin: 0, minWidth: 260 }}
                                                >
                                                    <label
                                                        className="form-label"
                                                        htmlFor="novo-bairro"
                                                    >
                                                        Acrescentar ao bloco
                                                    </label>
                                                    <input
                                                        id="novo-bairro"
                                                        type="text"
                                                        className="form-control"
                                                        list="bairros-conhecidos"
                                                        value={novoBairro}
                                                        maxLength={80}
                                                        placeholder="Escolha ou digite o bairro"
                                                        onChange={(e) =>
                                                            setNovoBairro(e.target.value)
                                                        }
                                                    />
                                                    {/* A lista oferece o que já
                                                        existe, para o mesmo bairro
                                                        não entrar com duas grafias
                                                        e virar dois bairros. */}
                                                    <datalist id="bairros-conhecidos">
                                                        {bairros.map((b) => (
                                                            <option key={b} value={b} />
                                                        ))}
                                                    </datalist>
                                                </div>

                                                <BotaoAcao
                                                    icone={<Plus size={16} aria-hidden />}
                                                    carregando={
                                                        enviando === 'bairro-adicionar'
                                                    }
                                                    ocupado={ocupado}
                                                    disabled={novoBairro.trim() === ''}
                                                    rotuloCarregando="Acrescentando…"
                                                    style={{ marginBottom: 2 }}
                                                    onClick={() =>
                                                        mexerNoBairro(
                                                            'adicionar',
                                                            novoBairro,
                                                        )
                                                    }
                                                >
                                                    Acrescentar
                                                </BotaoAcao>
                                            </div>
                                        )}
                                    </>
                                )}
                            </>
                        )}
                    </>
                )}
            </div>

            {excluindo && aberta !== null && (
                <ModalConfirm
                    titulo={`Excluir ${aberta.nome}?`}
                    mensagem={
                        <>
                            A área sai da estrutura com a{' '}
                            <strong>Equipe {aberta.equipe}</strong>, o encarregado
                            e{' '}
                            {contar(aberta.total_bairros, 'bairro', 'bairros')} do
                            bloco. As demandas desses bairros deixam de ter equipe
                            sugerida na Caixa de Entrada.
                        </>
                    }
                    rotuloConfirmar="Excluir área"
                    destrutiva
                    processando={enviando === 'excluir'}
                    onCancelar={() => setExcluindo(false)}
                    onConfirmar={excluir}
                />
            )}
        </>
    );
}

AreasEEquipes.layout = {
    breadcrumbs: [{ title: 'Áreas e Equipes', href: index() }],
};

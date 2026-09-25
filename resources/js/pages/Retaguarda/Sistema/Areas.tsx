import { Head, router } from '@inertiajs/react';
import { Check, CircleCheck, CircleSlash, Eye, List, Pencil, Plus, Trash2, TriangleAlert, Undo2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import { Paginacao, useOrdenacao, usePaginacao } from '@/components/retaguarda/th-ordenavel';
import { useAcoes } from '@/hooks/use-acoes';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta, semAcento } from '@/lib/busca';
import { VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { destroy, index, store, update } from '@/routes/retaguarda/areas';

/**
 * Sistema › Áreas — as áreas e os BAIRROS de cada uma (pedido do dono,
 * 25/09/2026), com os bairros em chips, como no Cadastro de Operação.
 *
 * Dos bairros da área sai a sugestão de equipe de cada demanda (pelo bairro do
 * endereço), os bairros que a operação marca sozinha e o recorte do líder nos
 * mapas. Quem está em cada equipe fica em Equipes. As colunas da lista vêm do
 * catálogo (`areas`, em `config/listagens_da_retaguarda.php`).
 */

interface Area {
    id: number;
    nome: string;
    regiao: string;
    recorte: string;
    turno: string;
    ativa: boolean;
    bairros: string[];
    equipes: string[];
    tem_historico: boolean;
}

interface Formulario {
    nome: string;
    regiao: string;
    recorte: string;
    turno: string;
    ativa: boolean;
    bairros: string[];
}

type Aba = 'localizar' | 'registro';
type Modo = 'navegacao' | 'edicao';
type Faceta = 'ativas' | 'inativas' | 'sem-bairro' | 'sem-equipe';

const FACETAS = [
    { expressao: /\binativ\w*\b/, valor: 'inativas' as const },
    { expressao: /\bativ(a|as)\b/, valor: 'ativas' as const },
    { expressao: /\bsem bairros?\b/, valor: 'sem-bairro' as const },
    { expressao: /\bsem equipes?\b/, valor: 'sem-equipe' as const },
];

const ROTULO_DO_RECORTE: Record<string, string> = {
    bairros: 'Bairros',
    corredores: 'Corredores',
    cidade: 'Cidade inteira',
};

function formularioDe(a: Area | null, recortes: string[], turnos: string[]): Formulario {
    return {
        nome: a?.nome ?? '',
        regiao: a?.regiao ?? '',
        recorte: a?.recorte || recortes[0] || 'bairros',
        turno: a?.turno || turnos[0] || '',
        ativa: a?.ativa ?? true,
        bairros: a?.bairros ?? [],
    };
}

export default function Areas({
    areas,
    bairrosConhecidos,
    recortes,
    turnos,
    listagens,
}: {
    areas: Area[];
    bairrosConhecidos: string[];
    recortes: string[];
    turnos: string[];
    listagens: Listagens;
}) {
    const listagem = listagens['areas'];
    const [aba, setAba] = useState<Aba>('localizar');
    const [aberta, setAberta] = useState<Area | null>(null);
    const [modo, setModo] = useState<Modo>('edicao');
    const [form, setForm] = useState<Formulario>(() => formularioDe(null, recortes, turnos));
    const [erros, setErros] = useState<Record<string, string>>({});
    const [busca, setBusca] = useState('');
    const [buscaBairro, setBuscaBairro] = useState('');
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);

    const { enviando, ocupado, enviar, guardar } = useEnvio();
    const acoes = useAcoes();

    const filtradas = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return areas.filter((a) => {
            if (facetas.includes('ativas') && !a.ativa) {
                return false;
            }

            if (facetas.includes('inativas') && a.ativa) {
                return false;
            }

            if (facetas.includes('sem-bairro') && a.bairros.length > 0) {
                return false;
            }

            if (facetas.includes('sem-equipe') && a.equipes.length > 0) {
                return false;
            }

            return casaTermos(termos, [a.nome, a.regiao, ROTULO_DO_RECORTE[a.recorte], a.turno, ...a.bairros, ...a.equipes]);
        });
    }, [areas, busca]);

    const ord = useOrdenacao(filtradas, { campo: 'area', acessor: 'nome' });
    const pag = usePaginacao(ord.itens);

    const acessores: Record<string, AcessorOrd<Area> | undefined> = {
        area: 'nome',
        regiao: 'regiao',
        recorte: (a) => ROTULO_DO_RECORTE[a.recorte] ?? a.recorte,
        total_bairros: (a) => a.bairros.length,
        situacao: (a) => a.ativa,
    };

    const fraco = { color: 'var(--sm-texto-fraco)' };

    function celula(a: Area, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'area') {
            return {
                conteudo: (
                    <>
                        <strong>{a.nome}</strong>
                        {a.equipes.length > 0 && <span style={fraco}> · {a.equipes.join(', ')}</span>}
                    </>
                ),
                dica: a.equipes.length > 0 ? `${a.nome} — equipes ${a.equipes.join(', ')}` : `${a.nome} — sem equipe`,
            };
        }

        if (chave === 'regiao') {
            return { conteudo: a.regiao || VAZIO, dica: a.regiao };
        }

        if (chave === 'recorte') {
            return { conteudo: ROTULO_DO_RECORTE[a.recorte] ?? a.recorte };
        }

        if (chave === 'total_bairros') {
            return {
                conteudo: a.recorte === 'cidade' ? <span style={fraco}>cidade</span> : a.bairros.length,
                dica: a.bairros.join(', ') || 'Nenhum bairro nesta área.',
            };
        }

        return {
            conteudo: (
                <span className={cn('selo', a.ativa ? 'selo-ok' : 'selo-neutro')}>
                    {a.ativa ? <CircleCheck size={13} aria-hidden /> : <CircleSlash size={13} aria-hidden />}{' '}
                    {a.ativa ? 'Ativa' : 'Inativa'}
                </span>
            ),
        };
    }

    const linhasExportacao = ord.itens.map((a) => ({
        area: a.nome,
        regiao: a.regiao || VAZIO,
        recorte: ROTULO_DO_RECORTE[a.recorte] ?? a.recorte,
        turno: a.turno || VAZIO,
        total_bairros: String(a.bairros.length),
        bairros: a.bairros.join(', ') || VAZIO,
        equipes: a.equipes.join(', ') || VAZIO,
        situacao: a.ativa ? 'Ativa' : 'Inativa',
    }));

    // ── Registro ──────────────────────────────────────────────────────────

    const nova = aberta === null;
    const somenteLeitura = modo === 'navegacao';
    const podeGravar = nova ? acoes.incluir : acoes.habilitado;

    function abrir(a: Area) {
        setAberta(a);
        setForm(formularioDe(a, recortes, turnos));
        setErros({});
        setBuscaBairro('');
        setModo('navegacao');
        setAba('registro');
    }

    function incluir() {
        if (!acoes.incluir) {
            return;
        }

        setAberta(null);
        setForm(formularioDe(null, recortes, turnos));
        setErros({});
        setBuscaBairro('');
        setModo('edicao');
        setAba('registro');
    }

    function voltarParaLista() {
        setAba('localizar');
        setAberta(null);
        setErros({});
    }

    function alternarBairro(b: string) {
        if (somenteLeitura) {
            return;
        }

        setForm((atual) => ({
            ...atual,
            bairros: atual.bairros.includes(b) ? atual.bairros.filter((x) => x !== b) : [...atual.bairros, b],
        }));
    }

    /** Bairro que ainda não existe em área nenhuma: entra pelo campo de busca. */
    function acrescentarBairroNovo() {
        const nome = buscaBairro.trim();

        if (nome === '' || somenteLeitura) {
            return;
        }

        const existente = todosOsBairros.find((b) => semAcento(b) === semAcento(nome));
        const alvo = existente ?? nome;

        setForm((atual) => (atual.bairros.includes(alvo) ? atual : { ...atual, bairros: [...atual.bairros, alvo] }));
        setBuscaBairro('');
    }

    function salvar() {
        const opcoes = {
            onSuccess: () => voltarParaLista(),
            onError: (recebidos: Record<string, string>) => setErros(recebidos),
        };

        if (aberta === null) {
            enviar('salvar', store().url, { ...form }, opcoes);

            return;
        }

        router.put(update(aberta.id).url, { ...form }, guardar('salvar', opcoes));
    }

    function excluir() {
        if (aberta === null) {
            return;
        }

        router.delete(
            destroy(aberta.id).url,
            guardar('excluir', {
                onSuccess: () => voltarParaLista(),
                onFinish: () => setConfirmandoExclusao(false),
            }),
        );
    }

    // Os chips: todo bairro conhecido, mais os desta área que ainda não existiam em lugar nenhum.
    const todosOsBairros = useMemo(() => {
        const lista = [...bairrosConhecidos];

        for (const b of form.bairros) {
            if (!lista.includes(b)) {
                lista.push(b);
            }
        }

        return lista.sort((a, b) => semAcento(a).localeCompare(semAcento(b)));
    }, [bairrosConhecidos, form.bairros]);

    const bairrosVisiveis = somenteLeitura
        ? todosOsBairros.filter((b) => form.bairros.includes(b))
        : todosOsBairros.filter((b) => buscaBairro.trim() === '' || semAcento(b).includes(semAcento(buscaBairro.trim())));

    const listaDeErros = Object.values(erros);

    /*
     * Os BOTÕES do registro vêm em cima, antes do formulário (dono, 25/09/2026);
     * em edição, Voltar e Salvar se repetem embaixo, para quem terminou de
     * preencher não precisar subir a tela.
     */
    const botaoVoltar = (
        <BotaoAcao className="btn btn-secondary btn-sm" icone={<Undo2 size={16} aria-hidden />} ocupado={ocupado} onClick={voltarParaLista}>
            Voltar
        </BotaoAcao>
    );
    const botaoSalvar =
        modo === 'edicao' && podeGravar ? (
            <BotaoAcao
                icone={<Check size={16} aria-hidden />}
                carregando={enviando === 'salvar'}
                ocupado={ocupado}
                rotuloCarregando="Salvando…"
                onClick={salvar}
            >
                Salvar
            </BotaoAcao>
        ) : null;

    return (
        <>
            <Head title="Áreas" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Áreas</h1>
                    <p>
                        As áreas da fiscalização e os <strong>bairros</strong> de cada uma. É pelo bairro do endereço que
                        cada demanda recebe a sugestão de equipe, e é daqui que a operação marca os bairros sozinha ao
                        escolher a área. Quem está em cada equipe fica em Equipes.
                    </p>
                </div>
            </div>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Áreas">
                    <button type="button" role="tab" className="aba" aria-selected={aba === 'localizar'} onClick={voltarParaLista}>
                        <List size={16} aria-hidden />
                        <span className="aba-rotulo">Localizar ({areas.length})</span>
                    </button>
                    {(aberta !== null || acoes.incluir) && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'registro'}
                            onClick={aberta === null ? incluir : () => setAba('registro')}
                        >
                            {aberta === null ? <Plus size={16} aria-hidden /> : <Eye size={16} aria-hidden />}
                            <span className="aba-rotulo">{aberta === null ? 'Incluir' : aberta.nome}</span>
                        </button>
                    )}
                </div>

                {aba === 'localizar' ? (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder="Procure por área, região, bairro ou equipe"
                            exemplos={['sem equipe', 'sem bairro', 'inativas', 'Barra']}
                        />

                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                            {acoes.incluir && (
                                <BotaoAcao icone={<Plus size={16} aria-hidden />} ocupado={ocupado} onClick={incluir}>
                                    Incluir
                                </BotaoAcao>
                            )}
                            <BotaoExportar
                                titulo="Áreas"
                                subtitulo="Sistema › Áreas"
                                contexto={busca.trim() ? `Busca: "${busca.trim()}"` : 'Todas as áreas'}
                                colunas={listagem.exportacao}
                                linhas={linhasExportacao}
                            />
                        </div>

                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela — para abrir a área.
                            </p>
                        )}

                        <div className="table-wrap">
                            <table className="data-table enxuta">
                                <thead>
                                    <tr>
                                        <CabecaDaGrade grade={listagem.grade} ord={ord} acessores={acessores} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td colSpan={listagem.grade.length} className="tabela-vazia">
                                                {areas.length === 0
                                                    ? 'Nenhuma área cadastrada ainda. Use "Incluir" para cadastrar a primeira.'
                                                    : 'Nenhuma área casa com a busca. Limpe o campo para ver todas.'}
                                            </td>
                                        </tr>
                                    )}
                                    {pag.visiveis.map((a) => (
                                        <tr key={a.id} {...linhaClicavel(() => abrir(a), `Abrir a área ${a.nome}`)}>
                                            {listagem.grade.map((coluna) => {
                                                const { conteudo, dica } = celula(a, coluna.chave);

                                                return (
                                                    <Celula key={coluna.chave} coluna={coluna} dica={dica}>
                                                        {conteudo}
                                                    </Celula>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Paginacao {...pag.props} />
                    </>
                ) : (
                    <>
                        <div className="rt-barra-registro">
                            {botaoVoltar}
                            {aberta !== null && somenteLeitura && (
                                <>
                                    {acoes.excluir && (
                                        <BotaoAcao
                                            className="btn btn-perigo btn-sm"
                                            icone={<Trash2 size={16} aria-hidden />}
                                            ocupado={ocupado}
                                            onClick={() => setConfirmandoExclusao(true)}
                                        >
                                            Excluir
                                        </BotaoAcao>
                                    )}
                                    {acoes.habilitado && (
                                        <BotaoAcao icone={<Pencil size={16} aria-hidden />} ocupado={ocupado} onClick={() => setModo('edicao')}>
                                            Editar
                                        </BotaoAcao>
                                    )}
                                </>
                            )}
                            {botaoSalvar}
                        </div>

                        {listaDeErros.length > 0 && (
                            <div className="form-erro" style={{ marginBottom: 16 }}>
                                <TriangleAlert size={15} aria-hidden /> Não foi possível salvar:
                                <ul style={{ margin: '6px 0 0', paddingLeft: 20 }}>
                                    {listaDeErros.map((m) => (
                                        <li key={m}>{m}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="rt-form-linha">
                            <div className="form-group">
                                <label className="form-label" htmlFor="ar-nome">
                                    Nome <span aria-hidden>*</span>
                                </label>
                                <input
                                    id="ar-nome"
                                    className="form-control"
                                    value={form.nome}
                                    maxLength={60}
                                    disabled={somenteLeitura}
                                    placeholder="ex.: Área 7"
                                    onChange={(e) => setForm({ ...form, nome: e.target.value })}
                                />
                                {erros.nome && <p className="form-erro">{erros.nome}</p>}
                            </div>
                            <div className="form-group">
                                <label className="form-label" htmlFor="ar-regiao">
                                    Região <span aria-hidden>*</span>
                                </label>
                                <input
                                    id="ar-regiao"
                                    className="form-control"
                                    value={form.regiao}
                                    maxLength={60}
                                    disabled={somenteLeitura}
                                    placeholder="ex.: Orla"
                                    onChange={(e) => setForm({ ...form, regiao: e.target.value })}
                                />
                                {erros.regiao && <p className="form-erro">{erros.regiao}</p>}
                            </div>
                        </div>

                        <div className="rt-form-linha">
                            <div className="form-group">
                                <label className="form-label" htmlFor="ar-recorte">
                                    O que a área cobre <span aria-hidden>*</span>
                                </label>
                                <select
                                    id="ar-recorte"
                                    className="form-control"
                                    value={form.recorte}
                                    disabled={somenteLeitura}
                                    onChange={(e) => setForm({ ...form, recorte: e.target.value })}
                                >
                                    {recortes.map((r) => (
                                        <option key={r} value={r}>
                                            {ROTULO_DO_RECORTE[r] ?? r}
                                        </option>
                                    ))}
                                </select>
                                {erros.recorte && <p className="form-erro">{erros.recorte}</p>}
                            </div>
                            <div className="form-group">
                                <label className="form-label" htmlFor="ar-turno">
                                    Turno <span aria-hidden>*</span>
                                </label>
                                <select
                                    id="ar-turno"
                                    className="form-control"
                                    value={form.turno}
                                    disabled={somenteLeitura}
                                    onChange={(e) => setForm({ ...form, turno: e.target.value })}
                                >
                                    {turnos.map((t) => (
                                        <option key={t} value={t}>
                                            {t}
                                        </option>
                                    ))}
                                </select>
                                {erros.turno && <p className="form-erro">{erros.turno}</p>}
                            </div>
                        </div>

                        <div className="form-group">
                            <p className="form-label" style={{ marginBottom: 6 }}>
                                Bairros da área ({form.bairros.length})
                            </p>
                            {!somenteLeitura && (
                                <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
                                    <input
                                        className="form-control"
                                        value={buscaBairro}
                                        placeholder="Procure o bairro — ou digite um novo e tecle Enter"
                                        aria-label="Procurar ou acrescentar bairro"
                                        onChange={(e) => setBuscaBairro(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                acrescentarBairroNovo();
                                            }
                                        }}
                                    />
                                    <button type="button" className="btn btn-secondary btn-sm" onClick={acrescentarBairroNovo} disabled={buscaBairro.trim() === ''}>
                                        <Plus size={15} aria-hidden /> Acrescentar
                                    </button>
                                </div>
                            )}
                            <div className="rt-marcadores" style={{ maxHeight: 280, overflow: 'auto' }}>
                                {bairrosVisiveis.map((b) => (
                                    <label key={b} className="rt-marcador">
                                        <input
                                            type="checkbox"
                                            checked={form.bairros.includes(b)}
                                            disabled={somenteLeitura}
                                            onChange={() => alternarBairro(b)}
                                        />
                                        <span>{b}</span>
                                    </label>
                                ))}
                                {bairrosVisiveis.length === 0 && (
                                    <p className="form-ajuda">
                                        {somenteLeitura ? 'Nenhum bairro nesta área.' : 'Nenhum bairro com esse nome — tecle Enter para acrescentá-lo.'}
                                    </p>
                                )}
                            </div>
                            <p className="form-ajuda">
                                Bairro em mais de uma área é caso normal (bairro de divisa): a demanda dele recebe as duas
                                sugestões. {form.recorte === 'cidade' && 'Área que cobre a cidade inteira não precisa de bairros.'}
                            </p>
                            {erros.bairros && <p className="form-erro">{erros.bairros}</p>}
                        </div>

                        <div className="form-group">
                            <label className="form-label" htmlFor="ar-ativa" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                <input
                                    id="ar-ativa"
                                    type="checkbox"
                                    checked={form.ativa}
                                    disabled={somenteLeitura}
                                    onChange={(e) => setForm({ ...form, ativa: e.target.checked })}
                                    style={{ width: 16, height: 16 }}
                                />
                                Ativa
                            </label>
                            <p className="form-ajuda">
                                Desmarcada, a área deixa de ser oferecida em novos cadastros — o que ela já teve continua
                                com o nome dela.
                            </p>
                        </div>

                        {modo === 'edicao' && (
                            <div className="rt-barra-registro rt-barra-registro-pe">
                                {botaoVoltar}
                                {botaoSalvar}
                            </div>
                        )}
                    </>
                )}
            </div>

            {confirmandoExclusao && aberta !== null && (
                <ModalConfirm
                    titulo={`Excluir a área ${aberta.nome}?`}
                    mensagem={
                        aberta.equipes.length > 0 || aberta.tem_historico ? (
                            <>
                                A área <strong>{aberta.nome}</strong>{' '}
                                {aberta.equipes.length > 0
                                    ? `tem ${contar(aberta.equipes.length, 'equipe', 'equipes')} cadastrada`
                                    : 'já recebeu demanda ou operação'}{' '}
                                — o sistema não vai excluí-la. Para tirá-la de uso, edite e desmarque <strong>Ativa</strong>.
                            </>
                        ) : (
                            <>
                                A área <strong>{aberta.nome}</strong> sai do cadastro, com os{' '}
                                {contar(aberta.bairros.length, 'bairro', 'bairros')} dela. Ela ainda não tem equipe nem histórico.
                            </>
                        )
                    }
                    rotuloConfirmar={aberta.equipes.length > 0 || aberta.tem_historico ? 'Editar para inativar' : 'Excluir'}
                    destrutiva={!(aberta.equipes.length > 0 || aberta.tem_historico)}
                    iconeConfirmar={aberta.equipes.length > 0 || aberta.tem_historico ? <Pencil size={16} aria-hidden /> : <Trash2 size={16} aria-hidden />}
                    processando={enviando === 'excluir'}
                    onCancelar={() => setConfirmandoExclusao(false)}
                    onConfirmar={() => {
                        if (aberta.equipes.length > 0 || aberta.tem_historico) {
                            setConfirmandoExclusao(false);
                            setModo('edicao');

                            return;
                        }

                        excluir();
                    }}
                />
            )}
        </>
    );
}

Areas.layout = {
    breadcrumbs: [
        {
            title: 'Áreas',
            href: index(),
        },
    ],
};

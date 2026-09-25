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
import { casaTermos, parseConsulta } from '@/lib/busca';
import { VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { destroy, index, store, update } from '@/routes/retaguarda/equipes';

/**
 * Sistema › Equipes — quem está em cada equipe (pedido do dono, 25/09/2026).
 *
 * O código, a área, o turno, o LÍDER (a conta que recebe o trabalho da equipe) e
 * os FISCAIS. É daqui que sai o recorte do líder nas telas e a fila de cada
 * fiscal no aplicativo. As colunas da lista vêm do catálogo (`equipes`, em
 * `config/listagens_da_retaguarda.php`).
 *
 * O registro abre em modo NAVEGAÇÃO; alterar é um clique em "Editar". Equipe com
 * histórico não se exclui — desmarca-se "Ativa".
 */

interface Pessoa {
    id: number;
    nome: string;
    login?: string;
}

interface Equipe {
    id: number;
    codigo: string;
    nome: string;
    area_id: number;
    area: string;
    turno: string;
    lider_id: number | null;
    lider: string | null;
    encarregado: string | null;
    fiscais: Pessoa[];
    ativa: boolean;
    tem_historico: boolean;
}

interface Formulario {
    codigo: string;
    nome: string;
    area_id: string;
    turno: string;
    lider_id: string;
    fiscais: number[];
    ativa: boolean;
}

type Aba = 'localizar' | 'registro';
type Modo = 'navegacao' | 'edicao';
type Faceta = 'ativas' | 'inativas' | 'sem-lider' | 'sem-fiscal';

const FACETAS = [
    { expressao: /\binativ\w*\b/, valor: 'inativas' as const },
    { expressao: /\bativ(a|as|o|os)\b/, valor: 'ativas' as const },
    { expressao: /\bsem lider\b/, valor: 'sem-lider' as const },
    { expressao: /\bsem fisca\w*\b/, valor: 'sem-fiscal' as const },
];

function formularioDe(e: Equipe | null, turnoPadrao: string): Formulario {
    return {
        codigo: e?.codigo ?? '',
        nome: e?.nome ?? '',
        area_id: e ? String(e.area_id) : '',
        turno: e?.turno || turnoPadrao,
        lider_id: e?.lider_id ? String(e.lider_id) : '',
        fiscais: e?.fiscais.map((f) => f.id) ?? [],
        ativa: e?.ativa ?? true,
    };
}

export default function Equipes({
    equipes,
    areas,
    turnos,
    lideres,
    fiscais,
    listagens,
}: {
    equipes: Equipe[];
    areas: { id: number; nome: string }[];
    turnos: string[];
    lideres: Pessoa[];
    fiscais: Pessoa[];
    listagens: Listagens;
}) {
    const listagem = listagens['equipes'];
    const [aba, setAba] = useState<Aba>('localizar');
    const [aberta, setAberta] = useState<Equipe | null>(null);
    const [modo, setModo] = useState<Modo>('edicao');
    const [form, setForm] = useState<Formulario>(() => formularioDe(null, turnos[0] ?? ''));
    const [erros, setErros] = useState<Record<string, string>>({});
    const [busca, setBusca] = useState('');
    const [buscaFiscal, setBuscaFiscal] = useState('');
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);

    const { enviando, ocupado, enviar, guardar } = useEnvio();
    const acoes = useAcoes();

    const filtradas = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return equipes.filter((e) => {
            if (facetas.includes('ativas') && !e.ativa) {
return false;
}

            if (facetas.includes('inativas') && e.ativa) {
return false;
}

            if (facetas.includes('sem-lider') && e.lider_id !== null) {
return false;
}

            if (facetas.includes('sem-fiscal') && e.fiscais.length > 0) {
return false;
}

            return casaTermos(termos, [e.codigo, e.nome, e.area, e.turno, e.lider, ...e.fiscais.map((f) => f.nome)]);
        });
    }, [equipes, busca]);

    const ord = useOrdenacao(filtradas, { campo: 'equipe', acessor: 'codigo' });
    const pag = usePaginacao(ord.itens);

    const acessores: Record<string, AcessorOrd<Equipe> | undefined> = {
        equipe: 'codigo',
        area: 'area',
        lider: (e) => e.lider ?? '',
        total_fiscais: (e) => e.fiscais.length,
        situacao: (e) => e.ativa,
    };

    const fraco = { color: 'var(--sm-texto-fraco)' };

    function celula(e: Equipe, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'equipe') {
            return {
                conteudo: (
                    <>
                        <strong>{e.codigo}</strong>
                        {e.nome && e.nome !== `Equipe ${e.codigo}` && <span style={fraco}> · {e.nome}</span>}
                    </>
                ),
                dica: e.nome || e.codigo,
            };
        }

        if (chave === 'area') {
            return { conteudo: e.area || VAZIO, dica: e.area };
        }

        if (chave === 'lider') {
            return e.lider
                ? { conteudo: e.lider, dica: e.lider }
                : {
                      conteudo: <span className="selo selo-aviso">Sem líder</span>,
                      dica: 'Sem líder com conta, o trabalho encaminhado à equipe não chega a ninguém.',
                  };
        }

        if (chave === 'total_fiscais') {
            return {
                conteudo: e.fiscais.length,
                dica: e.fiscais.map((f) => f.nome).join(', ') || 'Nenhum fiscal na equipe.',
            };
        }

        return {
            conteudo: (
                <span className={cn('selo', e.ativa ? 'selo-ok' : 'selo-neutro')}>
                    {e.ativa ? <CircleCheck size={13} aria-hidden /> : <CircleSlash size={13} aria-hidden />}{' '}
                    {e.ativa ? 'Ativa' : 'Inativa'}
                </span>
            ),
        };
    }

    const linhasExportacao = ord.itens.map((e) => ({
        equipe: e.nome && e.nome !== `Equipe ${e.codigo}` ? `${e.codigo} · ${e.nome}` : e.codigo,
        area: e.area || VAZIO,
        turno: e.turno || VAZIO,
        lider: e.lider ?? 'Sem líder',
        total_fiscais: String(e.fiscais.length),
        fiscais: e.fiscais.map((f) => f.nome).join(', ') || VAZIO,
        situacao: e.ativa ? 'Ativa' : 'Inativa',
    }));

    // ── Registro ──────────────────────────────────────────────────────────

    const nova = aberta === null;
    const somenteLeitura = modo === 'navegacao';
    const podeGravar = nova ? acoes.incluir : acoes.habilitado;

    function abrir(e: Equipe) {
        setAberta(e);
        setForm(formularioDe(e, turnos[0] ?? ''));
        setErros({});
        setBuscaFiscal('');
        setModo('navegacao');
        setAba('registro');
    }

    function incluir() {
        if (!acoes.incluir) {
return;
}

        setAberta(null);
        setForm(formularioDe(null, turnos[0] ?? ''));
        setErros({});
        setBuscaFiscal('');
        setModo('edicao');
        setAba('registro');
    }

    function voltarParaLista() {
        setAba('localizar');
        setAberta(null);
        setErros({});
    }

    function alternarFiscal(id: number) {
        if (somenteLeitura) {
return;
}

        setForm((atual) => ({
            ...atual,
            fiscais: atual.fiscais.includes(id) ? atual.fiscais.filter((f) => f !== id) : [...atual.fiscais, id],
        }));
    }

    function salvar() {
        const dados = {
            ...form,
            area_id: form.area_id === '' ? null : Number(form.area_id),
            lider_id: form.lider_id === '' ? null : Number(form.lider_id),
        };
        const opcoes = {
            onSuccess: () => voltarParaLista(),
            onError: (recebidos: Record<string, string>) => setErros(recebidos),
        };

        if (aberta === null) {
            enviar('salvar', store().url, dados, opcoes);

            return;
        }

        router.put(update(aberta.id).url, dados, guardar('salvar', opcoes));
    }

    function excluir() {
        if (aberta === null) {
return;
}

        router.delete(
            destroy(aberta.id).url,
            guardar('excluir', {
                onSuccess: () => {
                    setConfirmandoExclusao(false);
                    voltarParaLista();
                },
                onFinish: () => setConfirmandoExclusao(false),
            }),
        );
    }

    // Fiscais: o líder atual e os já vinculados aparecem mesmo que tenham mudado de setor.
    const opcoesDeFiscal = useMemo(() => {
        const mapa = new Map(fiscais.map((f) => [f.id, f]));
        aberta?.fiscais.forEach((f) => mapa.set(f.id, mapa.get(f.id) ?? f));

        return [...mapa.values()].sort((a, b) => a.nome.localeCompare(b.nome));
    }, [fiscais, aberta]);

    const opcoesDeLider = useMemo(() => {
        const lista = [...lideres];

        if (aberta?.lider_id && !lista.some((l) => l.id === aberta.lider_id)) {
            lista.push({ id: aberta.lider_id, nome: aberta.lider ?? `Conta ${aberta.lider_id}` });
        }

        return lista;
    }, [lideres, aberta]);

    const fiscaisVisiveis = opcoesDeFiscal.filter((f) => {
        const { termos } = parseConsulta<never>(buscaFiscal, []);

        return form.fiscais.includes(f.id) || casaTermos(termos, [f.nome, f.login]);
    });

    const listaDeErros = Object.values(erros);

    return (
        <>
            <Head title="Equipes" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Equipes</h1>
                    <p>
                        Quem está em cada equipe: a área, o turno, o <strong>líder</strong> — a conta que recebe o
                        trabalho encaminhado à equipe — e os <strong>fiscais</strong>, que recebem a fila no
                        aplicativo. A divisão da cidade em áreas e bairros fica em Áreas e Equipes.
                    </p>
                </div>
            </div>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Equipes">
                    <button type="button" role="tab" className="aba" aria-selected={aba === 'localizar'} onClick={voltarParaLista}>
                        <List size={16} aria-hidden />
                        <span className="aba-rotulo">Localizar ({equipes.length})</span>
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
                            <span className="aba-rotulo">{aberta === null ? 'Incluir' : `Equipe ${aberta.codigo}`}</span>
                        </button>
                    )}
                </div>

                {aba === 'localizar' ? (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder="Procure por código, área, líder, fiscal ou turno"
                            exemplos={['sem líder', 'sem fiscal', 'inativas', 'noturno']}
                        />

                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                            {acoes.incluir && (
                                <BotaoAcao icone={<Plus size={16} aria-hidden />} ocupado={ocupado} onClick={incluir}>
                                    Incluir
                                </BotaoAcao>
                            )}
                            <BotaoExportar
                                titulo="Equipes"
                                subtitulo="Sistema › Equipes"
                                contexto={busca.trim() ? `Busca: "${busca.trim()}"` : 'Todas as equipes'}
                                colunas={listagem.exportacao}
                                linhas={linhasExportacao}
                            />
                        </div>

                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela — para abrir a equipe.
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
                                                {equipes.length === 0
                                                    ? 'Nenhuma equipe cadastrada ainda. Use "Incluir" para cadastrar a primeira.'
                                                    : 'Nenhuma equipe casa com a busca. Limpe o campo para ver todas.'}
                                            </td>
                                        </tr>
                                    )}
                                    {pag.visiveis.map((e) => (
                                        <tr key={e.id} {...linhaClicavel(() => abrir(e), `Abrir a equipe ${e.codigo}`)}>
                                            {listagem.grade.map((coluna) => {
                                                const { conteudo, dica } = celula(e, coluna.chave);

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
                                <label className="form-label" htmlFor="eq-codigo">
                                    Código <span aria-hidden>*</span>
                                </label>
                                <input
                                    id="eq-codigo"
                                    className="form-control"
                                    value={form.codigo}
                                    maxLength={10}
                                    disabled={somenteLeitura}
                                    placeholder="ex.: C2"
                                    onChange={(ev) => setForm({ ...form, codigo: ev.target.value.toUpperCase() })}
                                />
                                {erros.codigo && <p className="form-erro">{erros.codigo}</p>}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="eq-nome">
                                    Nome
                                </label>
                                <input
                                    id="eq-nome"
                                    className="form-control"
                                    value={form.nome}
                                    maxLength={80}
                                    disabled={somenteLeitura}
                                    placeholder={form.codigo ? `Equipe ${form.codigo}` : 'Opcional'}
                                    onChange={(ev) => setForm({ ...form, nome: ev.target.value })}
                                />
                            </div>
                        </div>

                        <div className="rt-form-linha">
                            <div className="form-group">
                                <label className="form-label" htmlFor="eq-area">
                                    Área <span aria-hidden>*</span>
                                </label>
                                <select
                                    id="eq-area"
                                    className="form-control"
                                    value={form.area_id}
                                    disabled={somenteLeitura}
                                    onChange={(ev) => setForm({ ...form, area_id: ev.target.value })}
                                >
                                    <option value="">Escolha a área…</option>
                                    {areas.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.nome}
                                        </option>
                                    ))}
                                </select>
                                {erros.area_id && <p className="form-erro">{erros.area_id}</p>}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="eq-turno">
                                    Turno <span aria-hidden>*</span>
                                </label>
                                <select
                                    id="eq-turno"
                                    className="form-control"
                                    value={form.turno}
                                    disabled={somenteLeitura}
                                    onChange={(ev) => setForm({ ...form, turno: ev.target.value })}
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
                            <label className="form-label" htmlFor="eq-lider">
                                Líder
                            </label>
                            <select
                                id="eq-lider"
                                className="form-control"
                                value={form.lider_id}
                                disabled={somenteLeitura}
                                onChange={(ev) => setForm({ ...form, lider_id: ev.target.value })}
                            >
                                <option value="">Sem líder com conta</option>
                                {opcoesDeLider.map((l) => (
                                    <option key={l.id} value={l.id}>
                                        {l.nome}
                                    </option>
                                ))}
                            </select>
                            <p className="form-ajuda">
                                A conta que recebe o trabalho encaminhado à equipe. Só aparecem contas ativas do setor
                                Líder de Equipe — dê o setor em Usuários.
                                {aberta?.encarregado && !form.lider_id && ` Encarregado registrado: ${aberta.encarregado}.`}
                            </p>
                            {erros.lider_id && <p className="form-erro">{erros.lider_id}</p>}
                        </div>

                        <div className="form-group">
                            <p className="form-label" style={{ marginBottom: 6 }}>
                                Fiscais ({form.fiscais.length})
                            </p>
                            {!somenteLeitura && opcoesDeFiscal.length > 8 && (
                                <input
                                    className="form-control"
                                    style={{ marginBottom: 8 }}
                                    value={buscaFiscal}
                                    placeholder="Procure o fiscal pelo nome ou matrícula"
                                    aria-label="Procurar fiscal"
                                    onChange={(ev) => setBuscaFiscal(ev.target.value)}
                                />
                            )}
                            {opcoesDeFiscal.length === 0 ? (
                                <p className="form-ajuda">
                                    Nenhuma conta ativa do setor Fiscal. Dê o setor em Usuários para ela aparecer aqui.
                                </p>
                            ) : (
                                <div className="rt-escolhas">
                                    {(somenteLeitura ? opcoesDeFiscal.filter((f) => form.fiscais.includes(f.id)) : fiscaisVisiveis).map((f) => (
                                        <label key={f.id} className="rt-escolha" htmlFor={`eq-fiscal-${f.id}`}>
                                            <input
                                                id={`eq-fiscal-${f.id}`}
                                                type="checkbox"
                                                checked={form.fiscais.includes(f.id)}
                                                disabled={somenteLeitura}
                                                onChange={() => alternarFiscal(f.id)}
                                            />
                                            <span>{f.nome}</span>
                                        </label>
                                    ))}
                                    {somenteLeitura && form.fiscais.length === 0 && (
                                        <p className="form-ajuda">Nenhum fiscal nesta equipe.</p>
                                    )}
                                </div>
                            )}
                            {erros.fiscais && <p className="form-erro">{erros.fiscais}</p>}
                        </div>

                        <div className="form-group">
                            <label className="form-label" htmlFor="eq-ativa" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                <input
                                    id="eq-ativa"
                                    type="checkbox"
                                    checked={form.ativa}
                                    disabled={somenteLeitura}
                                    onChange={(ev) => setForm({ ...form, ativa: ev.target.checked })}
                                    style={{ width: 16, height: 16 }}
                                />
                                Ativa
                            </label>
                            <p className="form-ajuda">
                                Desmarcada, a equipe deixa de ser oferecida em novos encaminhamentos — o que ela já fez
                                continua com o código dela.
                            </p>
                        </div>

                        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 20 }}>
                            <BotaoAcao className="btn btn-secondary btn-sm" icone={<Undo2 size={16} aria-hidden />} ocupado={ocupado} onClick={voltarParaLista}>
                                Voltar
                            </BotaoAcao>

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

                            {modo === 'edicao' && podeGravar && (
                                <BotaoAcao
                                    icone={<Check size={16} aria-hidden />}
                                    carregando={enviando === 'salvar'}
                                    ocupado={ocupado}
                                    rotuloCarregando="Salvando…"
                                    onClick={salvar}
                                >
                                    Salvar
                                </BotaoAcao>
                            )}
                        </div>
                    </>
                )}
            </div>

            {confirmandoExclusao && aberta !== null && (
                <ModalConfirm
                    titulo={`Excluir a equipe ${aberta.codigo}?`}
                    mensagem={
                        aberta.tem_historico ? (
                            <>
                                A equipe <strong>{aberta.codigo}</strong> já recebeu demanda, foi a campo ou entrou em
                                operação — o sistema não vai excluí-la, para não apagar esse histórico. Para tirá-la de
                                uso, edite e desmarque <strong>Ativa</strong>.
                            </>
                        ) : (
                            <>
                                A equipe <strong>{aberta.codigo}</strong> sai do cadastro, com o vínculo de{' '}
                                {contar(aberta.fiscais.length, 'fiscal', 'fiscais')}. Ela ainda não tem histórico.
                            </>
                        )
                    }
                    rotuloConfirmar={aberta.tem_historico ? 'Editar para inativar' : 'Excluir'}
                    destrutiva={!aberta.tem_historico}
                    iconeConfirmar={aberta.tem_historico ? <Pencil size={16} aria-hidden /> : <Trash2 size={16} aria-hidden />}
                    processando={enviando === 'excluir'}
                    onCancelar={() => setConfirmandoExclusao(false)}
                    onConfirmar={() => {
                        if (aberta.tem_historico) {
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

Equipes.layout = {
    breadcrumbs: [
        {
            title: 'Equipes',
            href: index(),
        },
    ],
};

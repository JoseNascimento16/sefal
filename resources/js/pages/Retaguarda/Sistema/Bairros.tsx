import { Head, router } from '@inertiajs/react';
import { Check, CircleCheck, CircleSlash, Eye, List, MapPin, Pencil, Plus, Trash2, TriangleAlert, Undo2 } from 'lucide-react';
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
import { cn } from '@/lib/utils';
import { destroy, index, store, update } from '@/routes/retaguarda/bairros';

/**
 * Sistema › Bairros — o catálogo de bairros da cidade (dono, 25/09/2026).
 *
 * O nome certo e a coordenada que o mapa usa. As áreas dizem quais bairros
 * cobrem, e o cadastro de Áreas oferece os daqui. Renomear um bairro renomeia
 * também nas áreas e operações que o citam. Bairro que está em alguma área não se
 * exclui. As colunas vêm do catálogo (`bairros`).
 */

interface Bairro {
    id: number;
    nome: string;
    latitude: number | null;
    longitude: number | null;
    ativo: boolean;
    areas: string[];
}

interface Formulario {
    nome: string;
    latitude: string;
    longitude: string;
    ativo: boolean;
}

type Aba = 'localizar' | 'registro';
type Modo = 'navegacao' | 'edicao';
type Faceta = 'ativos' | 'inativos' | 'sem-area' | 'sem-coordenada';

const FACETAS = [
    { expressao: /\binativ\w*\b/, valor: 'inativos' as const },
    { expressao: /\bativos?\b/, valor: 'ativos' as const },
    { expressao: /\bsem areas?\b/, valor: 'sem-area' as const },
    { expressao: /\bsem (coordenada|mapa)\b/, valor: 'sem-coordenada' as const },
];

function formularioDe(b: Bairro | null): Formulario {
    return {
        nome: b?.nome ?? '',
        latitude: b?.latitude === null || b?.latitude === undefined ? '' : String(b.latitude),
        longitude: b?.longitude === null || b?.longitude === undefined ? '' : String(b.longitude),
        ativo: b?.ativo ?? true,
    };
}

const temCoordenada = (b: Bairro) => b.latitude !== null && b.longitude !== null;

export default function Bairros({ bairros, listagens }: { bairros: Bairro[]; listagens: Listagens }) {
    const listagem = listagens['bairros'];
    const [aba, setAba] = useState<Aba>('localizar');
    const [aberto, setAberto] = useState<Bairro | null>(null);
    const [modo, setModo] = useState<Modo>('edicao');
    const [form, setForm] = useState<Formulario>(() => formularioDe(null));
    const [erros, setErros] = useState<Record<string, string>>({});
    const [busca, setBusca] = useState('');
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);

    const { enviando, ocupado, enviar, guardar } = useEnvio();
    const acoes = useAcoes();

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return bairros.filter((b) => {
            if (facetas.includes('ativos') && !b.ativo) {
                return false;
            }

            if (facetas.includes('inativos') && b.ativo) {
                return false;
            }

            if (facetas.includes('sem-area') && b.areas.length > 0) {
                return false;
            }

            if (facetas.includes('sem-coordenada') && temCoordenada(b)) {
                return false;
            }

            return casaTermos(termos, [b.nome, ...b.areas]);
        });
    }, [bairros, busca]);

    const ord = useOrdenacao(filtrados, { campo: 'bairro', acessor: 'nome' });
    const pag = usePaginacao(ord.itens);

    const acessores: Record<string, AcessorOrd<Bairro> | undefined> = {
        bairro: 'nome',
        areas: (b) => b.areas.join(', '),
        coordenada: (b) => temCoordenada(b),
        situacao: (b) => b.ativo,
    };

    const fraco = { color: 'var(--sm-texto-fraco)' };

    function celula(b: Bairro, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'bairro') {
            return { conteudo: <strong>{b.nome}</strong>, dica: b.nome };
        }

        if (chave === 'areas') {
            return b.areas.length === 0
                ? { conteudo: <span style={fraco}>em nenhuma área</span>, dica: 'O bairro ainda não está em área nenhuma.' }
                : { conteudo: b.areas.join(', '), dica: b.areas.join(', ') };
        }

        if (chave === 'coordenada') {
            return temCoordenada(b)
                ? { conteudo: <span className="selo selo-ok"><MapPin size={13} aria-hidden /> Sim</span>, dica: `${b.latitude}, ${b.longitude}` }
                : { conteudo: <span className="selo selo-aviso">Sem coordenada</span>, dica: 'Sem coordenada o bairro não aparece no mapa.' };
        }

        return {
            conteudo: (
                <span className={cn('selo', b.ativo ? 'selo-ok' : 'selo-neutro')}>
                    {b.ativo ? <CircleCheck size={13} aria-hidden /> : <CircleSlash size={13} aria-hidden />}{' '}
                    {b.ativo ? 'Ativo' : 'Inativo'}
                </span>
            ),
        };
    }

    const linhasExportacao = ord.itens.map((b) => ({
        bairro: b.nome,
        areas: b.areas.join(', ') || VAZIO,
        coordenada: temCoordenada(b) ? 'Sim' : 'Não',
        latitude: b.latitude === null ? VAZIO : String(b.latitude),
        longitude: b.longitude === null ? VAZIO : String(b.longitude),
        situacao: b.ativo ? 'Ativo' : 'Inativo',
    }));

    // ── Registro ──────────────────────────────────────────────────────────

    const novo = aberto === null;
    const somenteLeitura = modo === 'navegacao';
    const podeGravar = novo ? acoes.incluir : acoes.habilitado;

    function abrir(b: Bairro) {
        setAberto(b);
        setForm(formularioDe(b));
        setErros({});
        setModo('navegacao');
        setAba('registro');
    }

    function incluir() {
        if (!acoes.incluir) {
            return;
        }

        setAberto(null);
        setForm(formularioDe(null));
        setErros({});
        setModo('edicao');
        setAba('registro');
    }

    function voltarParaLista() {
        setAba('localizar');
        setAberto(null);
        setErros({});
    }

    function salvar() {
        const dados = {
            ...form,
            latitude: form.latitude.trim() === '' ? null : form.latitude.replace(',', '.'),
            longitude: form.longitude.trim() === '' ? null : form.longitude.replace(',', '.'),
        };
        const opcoes = {
            onSuccess: () => voltarParaLista(),
            onError: (recebidos: Record<string, string>) => setErros(recebidos),
        };

        if (aberto === null) {
            enviar('salvar', store().url, dados, opcoes);

            return;
        }

        router.put(update(aberto.id).url, dados, guardar('salvar', opcoes));
    }

    function excluir() {
        if (aberto === null) {
            return;
        }

        router.delete(
            destroy(aberto.id).url,
            guardar('excluir', {
                onSuccess: () => voltarParaLista(),
                onFinish: () => setConfirmandoExclusao(false),
            }),
        );
    }

    const listaDeErros = Object.values(erros);
    const botaoVoltar = (
        <BotaoAcao className="btn btn-secondary btn-sm" icone={<Undo2 size={16} aria-hidden />} ocupado={ocupado} onClick={voltarParaLista}>
            Voltar
        </BotaoAcao>
    );
    const botaoSalvar =
        modo === 'edicao' && podeGravar ? (
            <BotaoAcao icone={<Check size={16} aria-hidden />} carregando={enviando === 'salvar'} ocupado={ocupado} rotuloCarregando="Salvando…" onClick={salvar}>
                Salvar
            </BotaoAcao>
        ) : null;

    return (
        <>
            <Head title="Bairros" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Bairros</h1>
                    <p>
                        O catálogo de bairros da cidade: o nome certo e a coordenada que o mapa usa. As áreas dizem quais
                        bairros cobrem, e o cadastro de Áreas oferece os daqui.
                    </p>
                </div>
            </div>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Bairros">
                    <button type="button" role="tab" className="aba" aria-selected={aba === 'localizar'} onClick={voltarParaLista}>
                        <List size={16} aria-hidden />
                        <span className="aba-rotulo">Localizar ({bairros.length})</span>
                    </button>
                    {(aberto !== null || acoes.incluir) && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'registro'}
                            onClick={aberto === null ? incluir : () => setAba('registro')}
                        >
                            {aberto === null ? <Plus size={16} aria-hidden /> : <Eye size={16} aria-hidden />}
                            <span className="aba-rotulo">{aberto === null ? 'Incluir' : aberto.nome}</span>
                        </button>
                    )}
                </div>

                {aba === 'localizar' ? (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder="Procure pelo bairro ou pela área"
                            exemplos={['sem coordenada', 'sem área', 'inativos', 'Área 5']}
                        />

                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                            {acoes.incluir && (
                                <BotaoAcao icone={<Plus size={16} aria-hidden />} ocupado={ocupado} onClick={incluir}>
                                    Incluir
                                </BotaoAcao>
                            )}
                            <BotaoExportar
                                titulo="Bairros"
                                subtitulo="Sistema › Bairros"
                                contexto={busca.trim() ? `Busca: "${busca.trim()}"` : 'Todos os bairros'}
                                colunas={listagem.exportacao}
                                linhas={linhasExportacao}
                            />
                        </div>

                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela — para abrir o bairro.
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
                                                {bairros.length === 0
                                                    ? 'Nenhum bairro cadastrado ainda. Use "Incluir" para cadastrar o primeiro.'
                                                    : 'Nenhum bairro casa com a busca. Limpe o campo para ver todos.'}
                                            </td>
                                        </tr>
                                    )}
                                    {pag.visiveis.map((b) => (
                                        <tr key={b.id} {...linhaClicavel(() => abrir(b), `Abrir o bairro ${b.nome}`)}>
                                            {listagem.grade.map((coluna) => {
                                                const { conteudo, dica } = celula(b, coluna.chave);

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
                            {aberto !== null && somenteLeitura && (
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

                        <div className="form-group">
                            <label className="form-label" htmlFor="br-nome">
                                Nome <span aria-hidden>*</span>
                            </label>
                            <input
                                id="br-nome"
                                className="form-control"
                                value={form.nome}
                                maxLength={120}
                                disabled={somenteLeitura}
                                placeholder="ex.: Imbuí"
                                onChange={(e) => setForm({ ...form, nome: e.target.value })}
                            />
                            <p className="form-ajuda">Renomear aqui renomeia também nas áreas e operações que citam o bairro.</p>
                            {erros.nome && <p className="form-erro">{erros.nome}</p>}
                        </div>

                        <div className="rt-form-linha">
                            <div className="form-group">
                                <label className="form-label" htmlFor="br-latitude">
                                    Latitude
                                </label>
                                <input
                                    id="br-latitude"
                                    className="form-control"
                                    inputMode="decimal"
                                    value={form.latitude}
                                    disabled={somenteLeitura}
                                    placeholder="ex.: -12,9714"
                                    onChange={(e) => setForm({ ...form, latitude: e.target.value })}
                                />
                                {erros.latitude && <p className="form-erro">{erros.latitude}</p>}
                            </div>
                            <div className="form-group">
                                <label className="form-label" htmlFor="br-longitude">
                                    Longitude
                                </label>
                                <input
                                    id="br-longitude"
                                    className="form-control"
                                    inputMode="decimal"
                                    value={form.longitude}
                                    disabled={somenteLeitura}
                                    placeholder="ex.: -38,5014"
                                    onChange={(e) => setForm({ ...form, longitude: e.target.value })}
                                />
                                {erros.longitude && <p className="form-erro">{erros.longitude}</p>}
                            </div>
                        </div>
                        <p className="form-ajuda" style={{ marginTop: -6 }}>
                            A coordenada é o ponto do bairro no mapa de calor. Sem ela, o bairro existe nas listas mas não é
                            desenhado.
                        </p>

                        {aberto !== null && (
                            <dl className="rt-ficha" style={{ margin: '12px 0' }}>
                                <div>
                                    <dt>Áreas</dt>
                                    <dd>{aberto.areas.length === 0 ? 'em nenhuma área' : aberto.areas.join(', ')}</dd>
                                </div>
                            </dl>
                        )}

                        <div className="form-group">
                            <label className="form-label" htmlFor="br-ativo" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                <input
                                    id="br-ativo"
                                    type="checkbox"
                                    checked={form.ativo}
                                    disabled={somenteLeitura}
                                    onChange={(e) => setForm({ ...form, ativo: e.target.checked })}
                                    style={{ width: 16, height: 16 }}
                                />
                                Ativo
                            </label>
                            <p className="form-ajuda">Desmarcado, o bairro deixa de ser oferecido no cadastro de Áreas.</p>
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

            {confirmandoExclusao && aberto !== null && (
                <ModalConfirm
                    titulo={`Excluir o bairro ${aberto.nome}?`}
                    mensagem={
                        aberto.areas.length > 0 ? (
                            <>
                                O bairro <strong>{aberto.nome}</strong> está na {aberto.areas.join(', ')} — o sistema não vai
                                excluí-lo. Tire-o da área em Áreas, ou edite e desmarque <strong>Ativo</strong>.
                            </>
                        ) : (
                            <>
                                O bairro <strong>{aberto.nome}</strong> sai do catálogo. Ele não está em área nenhuma.
                            </>
                        )
                    }
                    rotuloConfirmar={aberto.areas.length > 0 ? 'Editar para inativar' : 'Excluir'}
                    destrutiva={aberto.areas.length === 0}
                    iconeConfirmar={aberto.areas.length > 0 ? <Pencil size={16} aria-hidden /> : <Trash2 size={16} aria-hidden />}
                    processando={enviando === 'excluir'}
                    onCancelar={() => setConfirmandoExclusao(false)}
                    onConfirmar={() => {
                        if (aberto.areas.length > 0) {
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

Bairros.layout = {
    breadcrumbs: [
        {
            title: 'Bairros',
            href: index(),
        },
    ],
};

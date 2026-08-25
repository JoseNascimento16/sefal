import { Head, router } from '@inertiajs/react';
import {
    Check,
    CircleCheck,
    CircleSlash,
    Eye,
    List,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
    Undo2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import {
    Paginacao,
    ThOrdenavel,
    useOrdenacao,
    usePaginacao,
} from '@/components/retaguarda/th-ordenavel';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { VAZIO } from '@/lib/datas';
import { cn } from '@/lib/utils';

/**
 * A tela das listas de escolha da Parametrização — UMA implementação para as
 * seis (tipo de infração, atividade do ambulante, unidade de medida, tipo de
 * operação, origem da operação, motivo de recusa).
 *
 * As seis são a mesma tela: localizar na lista, incluir, alterar, inativar,
 * excluir. O que muda é o nome das coisas e o campo que só aquela lista tem — e
 * isso vem do SERVIDOR, na `definicao`, junto com a validação que o exige. Seis
 * cópias desta tela divergiriam no primeiro ajuste, e a esquecida seria a que
 * ninguém abre.
 *
 * Duas vistas, em abas: **Localizar** (a lista inteira, com busca e exportação)
 * e o **registro** aberto. O registro abre em modo NAVEGAÇÃO — para olhar, não
 * para alterar sem querer; alterar é um clique consciente em "Editar".
 */

/** Um campo próprio da lista, declarado pelo servidor. */
export interface CampoLookup {
    chave: string;
    rotulo: string;
    obrigatorio: boolean;
    maximo: number;
    /** Campo de várias linhas (a descrição do tipo de infração). */
    longo: boolean;
    exemplo: string | null;
    ajuda: string | null;
}

export interface DefinicaoLookup {
    /** Plural, como no menu: "Tipos de Infração". */
    titulo: string;
    /** Um registro, para as mensagens: "Tipo de infração". */
    singular: string;
    descricao: string;
    /** Texto de exemplo dentro do campo de nome vazio. */
    exemplo: string;
    /** Onde a pessoa está: "Parametrização › Tipos de Infração". */
    trilha: string;
    campos: CampoLookup[];
    exemplosDeBusca: string[];
}

export interface ItemLookup {
    id: number;
    nome: string;
    ativo: boolean;
    [campo: string]: string | number | boolean | null;
}

/**
 * As rotas desta lista, tipadas pelo Wayfinder na página que usa o componente —
 * nada de endereço escrito à mão no meio da tela.
 */
export interface RotasLookup {
    store: () => { url: string };
    update: (id: number) => { url: string };
    destroy: (id: number) => { url: string };
}

/** O que a busca reconhece como situação, em qualquer uma das seis telas. */
type Faceta = 'ativos' | 'inativos';

const FACETAS = [
    { expressao: /\bativos?\b|\bem uso\b/, valor: 'ativos' as const },
    { expressao: /\binativos?\b|\bfora de uso\b/, valor: 'inativos' as const },
];

type Aba = 'localizar' | 'registro';
type Modo = 'navegacao' | 'edicao';

/** Os valores do formulário: nome, situação e os campos próprios da lista. */
type Formulario = Record<string, string | boolean | null>;

function formularioDe(
    definicao: DefinicaoLookup,
    item: ItemLookup | null,
): Formulario {
    const valores: Formulario = {
        nome: item?.nome ?? '',
        ativo: item?.ativo ?? true,
    };

    for (const campo of definicao.campos) {
        const valor = item?.[campo.chave];
        valores[campo.chave] = valor === null || valor === undefined ? '' : String(valor);
    }

    return valores;
}

export default function CrudLookup({
    definicao,
    itens,
    rotas,
}: {
    definicao: DefinicaoLookup;
    itens: ItemLookup[];
    rotas: RotasLookup;
}) {
    const [aba, setAba] = useState<Aba>('localizar');
    const [aberto, setAberto] = useState<ItemLookup | null>(null);
    const [modo, setModo] = useState<Modo>('edicao');
    const [form, setForm] = useState<Formulario>(() => formularioDe(definicao, null));
    const [erros, setErros] = useState<Record<string, string>>({});
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);
    const [busca, setBusca] = useState('');

    const { enviando, ocupado, enviar, guardar } = useEnvio();

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return itens.filter((item) => {
            if (facetas.includes('ativos') && !item.ativo) {
                return false;
            }

            if (facetas.includes('inativos') && item.ativo) {
                return false;
            }

            return casaTermos(termos, [
                item.nome,
                ...definicao.campos.map((campo) =>
                    item[campo.chave] === null ? '' : String(item[campo.chave]),
                ),
            ]);
        });
    }, [itens, busca, definicao.campos]);

    const ord = useOrdenacao(filtrados, { campo: 'nome', acessor: 'nome' });
    const pag = usePaginacao(ord.itens);

    /** Abre um registro para olhar — alterar é um clique a mais, de propósito. */
    function abrir(item: ItemLookup) {
        setAberto(item);
        setForm(formularioDe(definicao, item));
        setErros({});
        setModo('navegacao');
        setAba('registro');
    }

    /** Formulário em branco, já em modo de edição. */
    function incluir() {
        setAberto(null);
        setForm(formularioDe(definicao, null));
        setErros({});
        setModo('edicao');
        setAba('registro');
    }

    function voltarParaLista() {
        setAba('localizar');
        setAberto(null);
        setErros({});
    }

    function campo(chave: string, valor: string | boolean) {
        setForm((atual) => ({ ...atual, [chave]: valor }));
    }

    function salvar() {
        const dados = { ...form, nome: String(form.nome ?? '').trim() };

        const opcoes = {
            onSuccess: () => voltarParaLista(),
            onError: (recebidos: Record<string, string>) => setErros(recebidos),
        };

        if (aberto === null) {
            enviar('salvar', rotas.store().url, dados, opcoes);

            return;
        }

        router.put(rotas.update(aberto.id).url, dados, guardar('salvar', opcoes));
    }

    function excluir() {
        if (aberto === null) {
            return;
        }

        router.delete(
            rotas.destroy(aberto.id).url,
            guardar('excluir', {
                onSuccess: () => {
                    setConfirmandoExclusao(false);
                    voltarParaLista();
                },
            }),
        );
    }

    // Só as chaves declaradas entram no arquivo, e a situação já em palavras: o
    // documento é lido fora do sistema, onde ninguém traduz `true`.
    const linhasExportacao = ord.itens.map((item) => {
        const linha: Record<string, string> = { nome: item.nome };

        for (const c of definicao.campos) {
            linha[c.chave] =
                item[c.chave] === null || item[c.chave] === ''
                    ? VAZIO
                    : String(item[c.chave]);
        }

        linha.situacao = item.ativo ? 'Ativo' : 'Inativo';

        return linha;
    });

    const listaDeErros = Object.values(erros);

    return (
        <>
            <Head title={definicao.titulo} />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Parametrização</p>
                    <h1>{definicao.titulo}</h1>
                    <p>{definicao.descricao}</p>
                </div>
            </div>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label={definicao.titulo}>
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'localizar'}
                        onClick={voltarParaLista}
                    >
                        <List size={16} aria-hidden />
                        <span className="aba-rotulo">Localizar</span>
                    </button>

                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'registro'}
                        onClick={aberto === null ? incluir : () => setAba('registro')}
                    >
                        {aberto === null ? (
                            <Plus size={16} aria-hidden />
                        ) : (
                            <Eye size={16} aria-hidden />
                        )}
                        <span className="aba-rotulo">
                            {aberto === null ? 'Incluir' : aberto.nome}
                        </span>
                    </button>
                </div>

                {aba === 'localizar' ? (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder={`Procure na lista de ${definicao.titulo.toLowerCase()}`}
                            exemplos={definicao.exemplosDeBusca}
                        />

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                marginBottom: 10,
                            }}
                        >
                            <BotaoAcao
                                icone={<Plus size={16} aria-hidden />}
                                ocupado={ocupado}
                                onClick={incluir}
                            >
                                Incluir
                            </BotaoAcao>

                            <BotaoExportar
                                titulo={definicao.titulo}
                                subtitulo={definicao.trilha}
                                contexto={
                                    busca.trim()
                                        ? `Busca: "${busca.trim()}"`
                                        : 'Lista completa'
                                }
                                colunas={[
                                    { chave: 'nome', titulo: 'Nome' },
                                    ...definicao.campos.map((c) => ({
                                        chave: c.chave,
                                        titulo: c.rotulo,
                                    })),
                                    { chave: 'situacao', titulo: 'Situação' },
                                ]}
                                linhas={linhasExportacao}
                            />
                        </div>

                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <ThOrdenavel campo="nome" acessor="nome" ord={ord}>
                                            Nome
                                        </ThOrdenavel>

                                        {definicao.campos.map((c) => (
                                            <ThOrdenavel
                                                key={c.chave}
                                                campo={c.chave}
                                                acessor={(item: ItemLookup) =>
                                                    item[c.chave] === null
                                                        ? ''
                                                        : String(item[c.chave])
                                                }
                                                ord={ord}
                                            >
                                                {c.rotulo}
                                            </ThOrdenavel>
                                        ))}

                                        <ThOrdenavel
                                            campo="ativo"
                                            acessor={(item: ItemLookup) => item.ativo}
                                            ord={ord}
                                        >
                                            Situação
                                        </ThOrdenavel>
                                    </tr>
                                </thead>

                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={definicao.campos.length + 2}
                                                className="tabela-vazia"
                                            >
                                                {itens.length === 0
                                                    ? `Nenhum registro nesta lista ainda. Use "Incluir" para cadastrar o primeiro — enquanto ela estiver vazia, quem trabalha em rua não terá o que escolher.`
                                                    : 'Nenhum registro casa com a busca. Limpe o campo para ver a lista inteira.'}
                                            </td>
                                        </tr>
                                    )}

                                    {pag.visiveis.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="clicavel"
                                            onClick={() => abrir(item)}
                                        >
                                            <td>
                                                <strong>{item.nome}</strong>
                                            </td>

                                            {definicao.campos.map((c) => (
                                                <td key={c.chave}>
                                                    {item[c.chave] === null ||
                                                    item[c.chave] === ''
                                                        ? VAZIO
                                                        : String(item[c.chave])}
                                                </td>
                                            ))}

                                            <td>
                                                {/* `cn` e não texto de classe
                                                    montado à mão: o formatador
                                                    do projeto come o espaço de
                                                    dentro das aspas e as classes
                                                    saem grudadas, sem nada
                                                    quebrar. */}
                                                <span
                                                    className={cn(
                                                        'selo',
                                                        item.ativo
                                                            ? 'selo-ok'
                                                            : 'selo-neutro',
                                                    )}
                                                >
                                                    {item.ativo ? (
                                                        <CircleCheck size={13} aria-hidden />
                                                    ) : (
                                                        <CircleSlash size={13} aria-hidden />
                                                    )}{' '}
                                                    {item.ativo ? 'Ativo' : 'Inativo'}
                                                </span>
                                            </td>
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
                                <TriangleAlert size={15} aria-hidden /> Não foi
                                possível salvar:
                                <ul style={{ margin: '6px 0 0', paddingLeft: 20 }}>
                                    {listaDeErros.map((mensagem) => (
                                        <li key={mensagem}>{mensagem}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="form-group">
                            <label className="form-label" htmlFor="lookup-nome">
                                Nome <span aria-hidden>*</span>
                            </label>
                            <input
                                id="lookup-nome"
                                className="form-control"
                                value={String(form.nome ?? '')}
                                maxLength={120}
                                disabled={modo === 'navegacao'}
                                placeholder={definicao.exemplo}
                                onChange={(e) => campo('nome', e.target.value)}
                            />
                            {erros.nome && <p className="form-erro">{erros.nome}</p>}
                        </div>

                        {definicao.campos.map((c) => (
                            <div className="form-group" key={c.chave}>
                                <label className="form-label" htmlFor={`lookup-${c.chave}`}>
                                    {c.rotulo}{' '}
                                    {c.obrigatorio && <span aria-hidden>*</span>}
                                </label>

                                {c.longo ? (
                                    <textarea
                                        id={`lookup-${c.chave}`}
                                        className="form-control"
                                        rows={3}
                                        value={String(form[c.chave] ?? '')}
                                        maxLength={c.maximo}
                                        disabled={modo === 'navegacao'}
                                        placeholder={c.exemplo ?? undefined}
                                        onChange={(e) => campo(c.chave, e.target.value)}
                                    />
                                ) : (
                                    <input
                                        id={`lookup-${c.chave}`}
                                        className="form-control"
                                        value={String(form[c.chave] ?? '')}
                                        maxLength={c.maximo}
                                        disabled={modo === 'navegacao'}
                                        placeholder={c.exemplo ?? undefined}
                                        onChange={(e) => campo(c.chave, e.target.value)}
                                    />
                                )}

                                {c.ajuda && <p className="form-ajuda">{c.ajuda}</p>}
                                {erros[c.chave] && (
                                    <p className="form-erro">{erros[c.chave]}</p>
                                )}
                            </div>
                        ))}

                        <div className="form-group">
                            <label
                                className="form-label"
                                htmlFor="lookup-ativo"
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <input
                                    id="lookup-ativo"
                                    type="checkbox"
                                    checked={form.ativo === true}
                                    disabled={modo === 'navegacao'}
                                    onChange={(e) => campo('ativo', e.target.checked)}
                                    style={{ width: 16, height: 16 }}
                                />
                                Em uso
                            </label>
                            {/* Inativar é o caminho normal de aposentar um valor:
                                ele some do que se pode escolher hoje e continua
                                legível no que já foi registrado. */}
                            <p className="form-ajuda">
                                Desmarcado, o registro deixa de ser oferecido em
                                novas escolhas — o que já foi gravado continua
                                mostrando este nome.
                            </p>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                flexWrap: 'wrap',
                                marginTop: 20,
                            }}
                        >
                            <BotaoAcao
                                className="btn btn-secondary btn-sm"
                                icone={<Undo2 size={16} aria-hidden />}
                                ocupado={ocupado}
                                onClick={voltarParaLista}
                            >
                                Voltar
                            </BotaoAcao>

                            {aberto !== null && modo === 'navegacao' && (
                                <>
                                    <BotaoAcao
                                        className="btn btn-perigo btn-sm"
                                        icone={<Trash2 size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        onClick={() => setConfirmandoExclusao(true)}
                                    >
                                        Excluir
                                    </BotaoAcao>

                                    <BotaoAcao
                                        icone={<Pencil size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        onClick={() => setModo('edicao')}
                                    >
                                        Editar
                                    </BotaoAcao>
                                </>
                            )}

                            {modo === 'edicao' && (
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

            {confirmandoExclusao && aberto !== null && (
                <ModalConfirm
                    titulo={`Excluir "${aberto.nome}"?`}
                    mensagem={
                        <>
                            {definicao.singular} <strong>{aberto.nome}</strong> sai
                            da lista para sempre. Se ele já foi usado em algum
                            registro, o certo é <strong>desmarcar "Em uso"</strong>{' '}
                            em vez de excluir — assim o histórico continua legível.
                        </>
                    }
                    rotuloConfirmar="Excluir"
                    destrutiva
                    iconeConfirmar={<Trash2 size={16} aria-hidden />}
                    processando={enviando === 'excluir'}
                    onCancelar={() => setConfirmandoExclusao(false)}
                    onConfirmar={excluir}
                />
            )}
        </>
    );
}

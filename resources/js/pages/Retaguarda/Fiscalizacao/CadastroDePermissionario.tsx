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
    UserRound,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
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
import { casaTermos, parseConsulta, semAcento } from '@/lib/busca';
import { dataBR, hojeISO, VAZIO } from '@/lib/datas';
import { maskCpfCnpj, maskTelefone } from '@/lib/masks';
import { index, store, update, destroy } from '@/routes/retaguarda/permissionarios';
import { cn } from '@/lib/utils';

/**
 * Cadastro de Permissionário — a identidade de quem é fiscalizado.
 *
 * A tela existe para uma realidade concreta: **o alvo muitas vezes não tem
 * documento à mão**. Por isso o documento é opcional e a identidade que a tela
 * destaca é a de campo — **foto + apelido**. Sem foto, a linha mostra as
 * iniciais, nunca um espaço vazio: o fiscal precisa reconhecer a pessoa, e um
 * quadrado em branco não reconhece ninguém.
 *
 * Duas vistas, em abas: **Localizar** (a base inteira, com busca e exportação) e
 * o **registro** aberto. O registro abre em modo NAVEGAÇÃO — para olhar, não
 * para alterar sem querer.
 */

interface Permissionario {
    id: number;
    codigo: string;
    nome: string;
    apelido: string | null;
    /** Só `[0-9A-Z]` — é por ele que a busca casa o que a pessoa digita. */
    documento: string | null;
    /** Como uma pessoa lê (`000.000.000-00`); vem do servidor. */
    documento_formatado: string;
    rg: string | null;
    telefone: string | null;
    numero_permissao: string | null;
    /** ISO — quem escreve dd/mm/aaaa é esta tela. */
    validade_permissao: string | null;
    atividade_id: number;
    atividade: string | null;
    situacao: string;
    foto_url: string | null;
    cadastrado_em: string | null;
}

interface Atividade {
    id: number;
    nome: string;
    ativo: boolean;
}

type Aba = 'localizar' | 'registro';
type Modo = 'navegacao' | 'edicao';

/** O que a busca reconhece além das palavras soltas. */
type Faceta =
    | { tipo: 'situacao'; valor: string }
    | { tipo: 'atividade'; valor: number }
    | { tipo: 'sem-documento' }
    | { tipo: 'com-documento' }
    | { tipo: 'permissao-vencida' };

/**
 * As facetas do domínio, das mais específicas para as mais genéricas — a ordem
 * importa: declarada ao contrário, a genérica engole a outra.
 */
const FACETAS: { expressao: RegExp; valor: Faceta }[] = [
    { expressao: /\bcadastrad\w* em campo\b|\bquarentena\b|\baguardando validacao\b/, valor: { tipo: 'situacao', valor: 'Cadastrado em campo' } },
    { expressao: /\bsem documento\b|\bsem cpf\b|\bnao identificad\w*\b/, valor: { tipo: 'sem-documento' } },
    { expressao: /\bcom documento\b|\bcom cpf\b/, valor: { tipo: 'com-documento' } },
    { expressao: /\bpermissao vencida\b|\bvencid\w*\b/, valor: { tipo: 'permissao-vencida' } },
    { expressao: /\bregular(es)?\b/, valor: { tipo: 'situacao', valor: 'Regular' } },
    { expressao: /\birregular(es)?\b/, valor: { tipo: 'situacao', valor: 'Irregular' } },
];

/** Escapa o que, num nome de atividade, o motor de expressões leria como sintaxe. */
function escapaExpressao(valor: string): string {
    return valor.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * As atividades viram facetas em tempo de execução — a lista é mantida pelo
 * gestor, então não há como escrevê-las aqui.
 *
 * Da mais longa para a mais curta: declarada ao contrário, uma atividade
 * "Bebidas" comeria a expressão de "Bebidas e água de coco". E como faceta (e
 * não termo livre), "bebidas" filtra pelo RAMO — não casa por acaso com alguém
 * cujo apelido tenha a palavra.
 */
function facetasDeAtividade(atividades: Atividade[]): { expressao: RegExp; valor: Faceta }[] {
    return [...atividades]
        .sort((a, b) => b.nome.length - a.nome.length)
        .map((a) => ({
            expressao: new RegExp(`\\b${escapaExpressao(semAcento(a.nome))}\\b`),
            valor: { tipo: 'atividade', valor: a.id } as Faceta,
        }));
}

/** O selo de cada situação — cor com significado, não decoração. */
function seloDaSituacao(situacao: string): { classe: string; Icone: typeof CircleCheck } {
    if (situacao === 'Regular') {
        return { classe: 'selo-ok', Icone: CircleCheck };
    }

    if (situacao === 'Irregular') {
        return { classe: 'selo-perigo', Icone: TriangleAlert };
    }

    // Cadastrado em campo: está esperando alguém decidir — nem certo, nem errado.
    return { classe: 'selo-aviso', Icone: CircleSlash };
}

/** As iniciais de quem não tem foto: identidade mínima, nunca um vazio. */
function iniciais(nome: string, apelido: string | null): string {
    const base = (apelido ?? nome).trim().split(/\s+/);

    return `${base[0]?.[0] ?? ''}${base.length > 1 ? (base[base.length - 1][0] ?? '') : ''}`.toUpperCase();
}

/** O retrato da pessoa — foto, ou as iniciais dela. */
function Retrato({
    p,
    tamanho = 34,
}: {
    p: Pick<Permissionario, 'nome' | 'apelido' | 'foto_url'>;
    tamanho?: number;
}) {
    const estilo = {
        width: tamanho,
        height: tamanho,
        borderRadius: '50%',
        objectFit: 'cover' as const,
        flexShrink: 0,
        border: '1px solid var(--sm-borda)',
    };

    if (p.foto_url) {
        return (
            <img
                src={p.foto_url}
                alt={`Foto de ${p.apelido ?? p.nome}`}
                style={estilo}
            />
        );
    }

    return (
        <span
            aria-hidden
            style={{
                ...estilo,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: 'var(--sm-fundo-suave, #eef2f7)',
                color: 'var(--sm-texto-fraco)',
                fontSize: Math.round(tamanho / 2.8),
                fontWeight: 700,
            }}
        >
            {iniciais(p.nome, p.apelido) || <UserRound size={Math.round(tamanho / 2)} />}
        </span>
    );
}

/** Os valores do formulário — tudo texto, como o campo entrega. */
interface Formulario {
    nome: string;
    apelido: string;
    documento: string;
    rg: string;
    telefone: string;
    numero_permissao: string;
    validade_permissao: string;
    atividade_id: string;
    situacao: string;
}

function formularioDe(p: Permissionario | null, situacaoPadrao: string): Formulario {
    return {
        nome: p?.nome ?? '',
        apelido: p?.apelido ?? '',
        documento: p?.documento_formatado ?? '',
        rg: p?.rg ?? '',
        telefone: p?.telefone ?? '',
        numero_permissao: p?.numero_permissao ?? '',
        validade_permissao: p?.validade_permissao ?? '',
        atividade_id: p ? String(p.atividade_id) : '',
        situacao: p?.situacao ?? situacaoPadrao,
    };
}

export default function CadastroDePermissionario({
    permissionarios,
    atividades,
    situacoes,
}: {
    permissionarios: Permissionario[];
    atividades: Atividade[];
    situacoes: string[];
}) {
    const [aba, setAba] = useState<Aba>('localizar');
    const [aberto, setAberto] = useState<Permissionario | null>(null);
    const [modo, setModo] = useState<Modo>('edicao');
    const [form, setForm] = useState<Formulario>(() => formularioDe(null, situacoes[0] ?? ''));
    const [foto, setFoto] = useState<File | null>(null);
    const [removerFoto, setRemoverFoto] = useState(false);
    const [erros, setErros] = useState<Record<string, string>>({});
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);
    const [busca, setBusca] = useState('');

    const campoFoto = useRef<HTMLInputElement>(null);
    const { enviando, ocupado, enviar, guardar } = useEnvio();

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, [
            // O vocabulário fixo do domínio primeiro: nome de atividade é texto
            // que o gestor digita, e não pode redefinir "regular" ou "vencida".
            ...FACETAS,
            ...facetasDeAtividade(atividades),
        ]);
        const hoje = hojeISO();

        // O texto digitado pode SER um documento: comparar sem máscara faz
        // "123.456.789-09" achar quem está gravado como "12345678909".
        const termosDocumento = termos.map((t) => t.replace(/[^0-9a-z]/g, ''));

        return permissionarios.filter((p) => {
            for (const faceta of facetas) {
                if (faceta.tipo === 'situacao' && p.situacao !== faceta.valor) {
                    return false;
                }

                if (faceta.tipo === 'atividade' && p.atividade_id !== faceta.valor) {
                    return false;
                }

                if (faceta.tipo === 'sem-documento' && p.documento !== null) {
                    return false;
                }

                if (faceta.tipo === 'com-documento' && p.documento === null) {
                    return false;
                }

                if (
                    faceta.tipo === 'permissao-vencida' &&
                    (p.validade_permissao === null || p.validade_permissao >= hoje)
                ) {
                    return false;
                }
            }

            const casaTexto = casaTermos(termos, [
                p.nome,
                p.apelido,
                p.codigo,
                p.numero_permissao,
                p.atividade,
                p.situacao,
            ]);

            const casaDocumento =
                p.documento !== null &&
                termosDocumento.every(
                    (t) => t.length > 1 && semAcento(p.documento).includes(t),
                );

            return casaTexto || casaDocumento;
        });
    }, [permissionarios, atividades, busca]);

    const ord = useOrdenacao(filtrados, { campo: 'nome', acessor: 'nome' });
    const pag = usePaginacao(ord.itens);

    function abrir(p: Permissionario) {
        setAberto(p);
        setForm(formularioDe(p, situacoes[0] ?? ''));
        limparAnexo();
        setErros({});
        setModo('navegacao');
        setAba('registro');
    }

    function incluir() {
        setAberto(null);
        setForm(formularioDe(null, situacoes[0] ?? ''));
        limparAnexo();
        setErros({});
        setModo('edicao');
        setAba('registro');
    }

    function limparAnexo() {
        setFoto(null);
        setRemoverFoto(false);

        if (campoFoto.current) {
            campoFoto.current.value = '';
        }
    }

    function voltarParaLista() {
        setAba('localizar');
        setAberto(null);
        limparAnexo();
        setErros({});
    }

    function campo<K extends keyof Formulario>(chave: K, valor: string) {
        setForm((atual) => ({ ...atual, [chave]: valor }));
    }

    function salvar() {
        const dados: Record<string, string | boolean | File> = {
            ...form,
            // Enviado só quando há arquivo: campo ausente significa "não mexi na
            // foto", e é o caso de quem entrou para corrigir o telefone.
            ...(foto ? { foto } : {}),
            ...(removerFoto ? { remover_foto: true } : {}),
        };

        const opcoes = {
            onSuccess: () => voltarParaLista(),
            onError: (recebidos: Record<string, string>) => setErros(recebidos),
        };

        if (aberto === null) {
            enviar('salvar', store().url, dados, opcoes);

            return;
        }

        /*
         * POST com `_method: 'put'`, e não `router.put`: navegador nenhum envia
         * arquivo num PUT de formulário. É a forma que o Laravel entende, e a
         * mesma serve ao caso sem foto — um caminho só, sem ramo que só roda
         * quando alguém anexa imagem.
         */
        router.post(
            update(aberto.id).url,
            { ...dados, _method: 'put' },
            guardar('salvar', opcoes),
        );
    }

    function excluir() {
        if (aberto === null) {
            return;
        }

        router.delete(
            destroy(aberto.id).url,
            guardar('excluir', {
                onSuccess: () => {
                    setConfirmandoExclusao(false);
                    voltarParaLista();
                },
            }),
        );
    }

    // Só o que uma pessoa leria fora do sistema: nada de id, caminho de arquivo
    // ou forma ISO de data.
    const linhasExportacao = ord.itens.map((p) => ({
        codigo: p.codigo,
        nome: p.nome,
        apelido: p.apelido || VAZIO,
        documento: p.documento_formatado || VAZIO,
        atividade: p.atividade || VAZIO,
        situacao: p.situacao,
        numero_permissao: p.numero_permissao || VAZIO,
        validade_permissao: dataBR(p.validade_permissao),
    }));

    const listaDeErros = Object.values(erros);
    const emEdicao = modo === 'edicao';
    const fotoAtual = aberto?.foto_url ?? null;

    return (
        <>
            <Head title="Permissionários" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Fiscalização</p>
                    <h1>Permissionários</h1>
                    <p>
                        Quem é fiscalizado em rua. O documento é opcional: muita
                        gente é cadastrada em campo pela foto e pelo apelido — e
                        esse cadastro fica marcado como <strong>Cadastrado em
                        campo</strong> até alguém conferir.
                    </p>
                </div>
            </div>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Permissionários">
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
                            {aberto === null ? 'Incluir' : (aberto.apelido ?? aberto.nome)}
                        </span>
                    </button>
                </div>

                {aba === 'localizar' ? (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder="Procure por nome, apelido, documento ou nº da permissão"
                            exemplos={[
                                'cadastrado em campo',
                                'sem documento',
                                'irregular',
                                'permissão vencida',
                                // Um ramo de verdade, tirado da lista que o
                                // gestor mantém — exemplo escrito à mão aqui
                                // envelheceria na primeira atividade renomeada.
                                ...atividades
                                    .filter((a) => a.ativo)
                                    .slice(0, 1)
                                    .map((a) => a.nome.toLowerCase()),
                            ]}
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
                                titulo="Permissionários"
                                subtitulo="Fiscalização › Permissionários"
                                contexto={
                                    busca.trim()
                                        ? `Busca: "${busca.trim()}"`
                                        : 'Base completa'
                                }
                                colunas={[
                                    { chave: 'codigo', titulo: 'Código' },
                                    { chave: 'nome', titulo: 'Nome' },
                                    { chave: 'apelido', titulo: 'Apelido' },
                                    { chave: 'documento', titulo: 'Documento' },
                                    { chave: 'atividade', titulo: 'Atividade' },
                                    { chave: 'situacao', titulo: 'Situação' },
                                    {
                                        chave: 'numero_permissao',
                                        titulo: 'Nº da permissão',
                                    },
                                    {
                                        chave: 'validade_permissao',
                                        titulo: 'Validade',
                                        alinhar: 'center',
                                    },
                                ]}
                                linhas={linhasExportacao}
                            />
                        </div>

                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <ThOrdenavel campo="nome" acessor="nome" ord={ord}>
                                            Permissionário
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="documento"
                                            acessor={(p: Permissionario) =>
                                                p.documento_formatado
                                            }
                                            ord={ord}
                                        >
                                            Documento
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="atividade"
                                            acessor={(p: Permissionario) =>
                                                p.atividade ?? ''
                                            }
                                            ord={ord}
                                        >
                                            Atividade
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="validade_permissao"
                                            acessor={(p: Permissionario) =>
                                                p.validade_permissao ?? ''
                                            }
                                            ord={ord}
                                        >
                                            Validade da permissão
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="situacao"
                                            acessor={(p: Permissionario) => p.situacao}
                                            ord={ord}
                                        >
                                            Situação
                                        </ThOrdenavel>
                                    </tr>
                                </thead>

                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="tabela-vazia">
                                                {permissionarios.length === 0
                                                    ? 'Nenhum permissionário cadastrado ainda. Use "Incluir" para cadastrar o primeiro — é dele que a fiscalização parte.'
                                                    : 'Ninguém casa com a busca. Limpe o campo para ver a base inteira.'}
                                            </td>
                                        </tr>
                                    )}

                                    {pag.visiveis.map((p) => {
                                        const selo = seloDaSituacao(p.situacao);

                                        return (
                                            <tr
                                                key={p.id}
                                                className="clicavel"
                                                onClick={() => abrir(p)}
                                            >
                                                <td>
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            gap: 10,
                                                        }}
                                                    >
                                                        <Retrato p={p} />
                                                        <div>
                                                            <strong>{p.nome}</strong>
                                                            <div
                                                                style={{
                                                                    fontSize: 12,
                                                                    color: 'var(--sm-texto-fraco)',
                                                                }}
                                                            >
                                                                {p.apelido
                                                                    ? `“${p.apelido}” · `
                                                                    : ''}
                                                                {p.codigo}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{p.documento_formatado || VAZIO}</td>
                                                <td>{p.atividade || VAZIO}</td>
                                                <td>{dataBR(p.validade_permissao)}</td>
                                                <td>
                                                    <span
                                                        className={cn('selo', selo.classe)}
                                                    >
                                                        <selo.Icone
                                                            size={13}
                                                            aria-hidden
                                                        />{' '}
                                                        {p.situacao}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
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

                        {/* A identidade de campo primeiro: é a foto e o apelido
                            que o fiscal usa para reconhecer a pessoa. */}
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 16,
                                marginBottom: 18,
                                flexWrap: 'wrap',
                            }}
                        >
                            <Retrato
                                p={{
                                    nome: form.nome || '?',
                                    apelido: form.apelido || null,
                                    foto_url: removerFoto ? null : fotoAtual,
                                }}
                                tamanho={72}
                            />

                            <div style={{ flex: 1, minWidth: 240 }}>
                                <label className="form-label" htmlFor="perm-foto">
                                    Foto
                                </label>
                                <input
                                    id="perm-foto"
                                    ref={campoFoto}
                                    type="file"
                                    className="form-control"
                                    accept="image/jpeg,image/png"
                                    disabled={!emEdicao}
                                    onChange={(e) => {
                                        setFoto(e.target.files?.[0] ?? null);
                                        setRemoverFoto(false);
                                    }}
                                />
                                <p className="form-ajuda">
                                    JPG ou PNG, até 5 MB. Sem foto, a listagem
                                    mostra as iniciais.
                                    {foto ? ` Escolhida: ${foto.name}.` : ''}
                                </p>

                                {fotoAtual && emEdicao && (
                                    <label
                                        className="form-label"
                                        htmlFor="perm-remover-foto"
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 8,
                                        }}
                                    >
                                        <input
                                            id="perm-remover-foto"
                                            type="checkbox"
                                            checked={removerFoto}
                                            onChange={(e) => {
                                                setRemoverFoto(e.target.checked);

                                                if (e.target.checked) {
                                                    setFoto(null);

                                                    if (campoFoto.current) {
                                                        campoFoto.current.value = '';
                                                    }
                                                }
                                            }}
                                            style={{ width: 16, height: 16 }}
                                        />
                                        Remover a foto atual
                                    </label>
                                )}

                                {erros.foto && <p className="form-erro">{erros.foto}</p>}
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fit, minmax(240px, 1fr))',
                                gap: '0 16px',
                            }}
                        >
                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-nome">
                                    Nome <span aria-hidden>*</span>
                                </label>
                                <input
                                    id="perm-nome"
                                    className="form-control"
                                    value={form.nome}
                                    maxLength={150}
                                    disabled={!emEdicao}
                                    placeholder="Ex.: João da Silva"
                                    onChange={(e) => campo('nome', e.target.value)}
                                />
                                {erros.nome && <p className="form-erro">{erros.nome}</p>}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-apelido">
                                    Apelido
                                </label>
                                <input
                                    id="perm-apelido"
                                    className="form-control"
                                    value={form.apelido}
                                    maxLength={100}
                                    disabled={!emEdicao}
                                    placeholder="Ex.: João do Acarajé"
                                    onChange={(e) => campo('apelido', e.target.value)}
                                />
                                <p className="form-ajuda">
                                    Como a pessoa é conhecida no ponto — em rua,
                                    é por aqui que ela é encontrada.
                                </p>
                                {erros.apelido && (
                                    <p className="form-erro">{erros.apelido}</p>
                                )}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-documento">
                                    CPF ou CNPJ
                                </label>
                                {/* Sem `inputMode="numeric"`: o CNPJ novo tem
                                    LETRAS nas 12 primeiras posições. */}
                                <input
                                    id="perm-documento"
                                    className="form-control"
                                    value={form.documento}
                                    disabled={!emEdicao}
                                    placeholder="Opcional"
                                    onChange={(e) =>
                                        campo('documento', maskCpfCnpj(e.target.value))
                                    }
                                />
                                <p className="form-ajuda">
                                    Opcional. Muita gente é cadastrada em campo
                                    sem documento em mãos.
                                </p>
                                {erros.documento && (
                                    <p className="form-erro">{erros.documento}</p>
                                )}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-rg">
                                    RG
                                </label>
                                <input
                                    id="perm-rg"
                                    className="form-control"
                                    value={form.rg}
                                    maxLength={20}
                                    disabled={!emEdicao}
                                    onChange={(e) => campo('rg', e.target.value)}
                                />
                                {erros.rg && <p className="form-erro">{erros.rg}</p>}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-telefone">
                                    Telefone
                                </label>
                                <input
                                    id="perm-telefone"
                                    className="form-control"
                                    value={form.telefone}
                                    inputMode="numeric"
                                    disabled={!emEdicao}
                                    placeholder="(71) 99999-0000"
                                    onChange={(e) =>
                                        campo('telefone', maskTelefone(e.target.value))
                                    }
                                />
                                {erros.telefone && (
                                    <p className="form-erro">{erros.telefone}</p>
                                )}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-atividade">
                                    Atividade autorizada <span aria-hidden>*</span>
                                </label>
                                <select
                                    id="perm-atividade"
                                    className="form-control"
                                    value={form.atividade_id}
                                    disabled={!emEdicao}
                                    onChange={(e) =>
                                        campo('atividade_id', e.target.value)
                                    }
                                >
                                    <option value="">Escolha…</option>
                                    {atividades
                                        // Inativa some das escolhas novas e
                                        // continua à vista no cadastro que já a
                                        // apontava — senão o campo apareceria em
                                        // branco, como se o dado tivesse sumido.
                                        .filter(
                                            (a) =>
                                                a.ativo ||
                                                String(a.id) === form.atividade_id,
                                        )
                                        .map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.nome}
                                                {a.ativo ? '' : ' (fora de uso)'}
                                            </option>
                                        ))}
                                </select>
                                {erros.atividade_id && (
                                    <p className="form-erro">{erros.atividade_id}</p>
                                )}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-permissao">
                                    Nº da permissão
                                </label>
                                <input
                                    id="perm-permissao"
                                    className="form-control"
                                    value={form.numero_permissao}
                                    maxLength={30}
                                    disabled={!emEdicao}
                                    onChange={(e) =>
                                        campo('numero_permissao', e.target.value)
                                    }
                                />
                                {erros.numero_permissao && (
                                    <p className="form-erro">
                                        {erros.numero_permissao}
                                    </p>
                                )}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-validade">
                                    Validade da permissão
                                </label>
                                {/* A forma ISO existe só aqui dentro, que é o que
                                    o campo de data do navegador entende; o que a
                                    tela MOSTRA é sempre dd/mm/aaaa. */}
                                <input
                                    id="perm-validade"
                                    type="date"
                                    className="form-control"
                                    value={form.validade_permissao}
                                    disabled={!emEdicao}
                                    onChange={(e) =>
                                        campo('validade_permissao', e.target.value)
                                    }
                                />
                                {form.validade_permissao && (
                                    <p className="form-ajuda">
                                        Vence em {dataBR(form.validade_permissao)}.
                                    </p>
                                )}
                                {erros.validade_permissao && (
                                    <p className="form-erro">
                                        {erros.validade_permissao}
                                    </p>
                                )}
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="perm-situacao">
                                    Situação <span aria-hidden>*</span>
                                </label>
                                <select
                                    id="perm-situacao"
                                    className="form-control"
                                    value={form.situacao}
                                    disabled={!emEdicao}
                                    onChange={(e) => campo('situacao', e.target.value)}
                                >
                                    {situacoes.map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </select>
                                <p className="form-ajuda">
                                    “Cadastrado em campo” é o cadastro feito em
                                    rua, ainda sem conferência.
                                </p>
                                {erros.situacao && (
                                    <p className="form-erro">{erros.situacao}</p>
                                )}
                            </div>
                        </div>

                        {aberto && (
                            <p className="form-ajuda">
                                Código <strong>{aberto.codigo}</strong> · cadastrado
                                em {dataBR(aberto.cadastrado_em)}
                            </p>
                        )}

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

                            {emEdicao && (
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
                    titulo={`Excluir "${aberto.apelido ?? aberto.nome}"?`}
                    mensagem={
                        <>
                            O cadastro <strong>{aberto.codigo}</strong> sai do
                            sistema para sempre, junto com a foto. Se a pessoa
                            apenas deixou de trabalhar, o certo é mudar a{' '}
                            <strong>situação</strong> em vez de excluir.
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

CadastroDePermissionario.layout = {
    breadcrumbs: [{ title: 'Permissionários', href: index() }],
};

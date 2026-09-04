import { Head, router } from '@inertiajs/react';
import {
    Check,
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
import { useAcoes } from '@/hooks/use-acoes';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta, semAcento } from '@/lib/busca';
import { dataBR, hojeISO, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { maskCpfCnpj, maskTelefone } from '@/lib/masks';
import { index, store, update, destroy } from '@/routes/retaguarda/ambulantes';
import { cn } from '@/lib/utils';

/**
 * Cadastro de Ambulante — a identidade de quem é fiscalizado.
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

interface Ambulante {
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
    /** Tem permissão da SEMOP? É atributo, não categoria: a base tem os dois. */
    permissionario: boolean;
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
    | { tipo: 'permissionario' }
    | { tipo: 'sem-permissao' }
    | { tipo: 'permissao-vencida' };

/**
 * As facetas do domínio, das mais específicas para as mais genéricas — a ordem
 * importa: declarada ao contrário, a genérica engole a outra.
 */
const FACETAS: { expressao: RegExp; valor: Faceta }[] = [
    { expressao: /\bcadastrad\w* em campo\b|\bquarentena\b|\baguardando validacao\b/, valor: { tipo: 'situacao', valor: 'Cadastrado em campo' } },
    { expressao: /\bsem documento\b|\bsem cpf\b|\bnao identificad\w*\b/, valor: { tipo: 'sem-documento' } },
    { expressao: /\bcom documento\b|\bcom cpf\b/, valor: { tipo: 'com-documento' } },
    /* "permissao vencida" ANTES de "sem permissao" e de "permissionario": as
       três dividem a palavra "permissão", e a mais específica tem de ganhar. */
    { expressao: /\bpermissao vencida\b|\bvencid\w*\b/, valor: { tipo: 'permissao-vencida' } },
    { expressao: /\bsem permissao\b|\bnao permissionari\w*\b|\bsem licenca\b/, valor: { tipo: 'sem-permissao' } },
    { expressao: /\bpermissionari\w*\b|\bcom permissao\b/, valor: { tipo: 'permissionario' } },
    { expressao: /\bregular(es)?\b/, valor: { tipo: 'situacao', valor: 'Regular' } },
    { expressao: /\birregular(es)?\b/, valor: { tipo: 'situacao', valor: 'Irregular' } },
];

/** Escapa o que, num nome de atividade, o motor de expressões leria como sintaxe. */
function escapaExpressao(valor: string): string {
    return valor.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * As atividades viram facetas em tempo de execução — a lista é mantida pelo
 * Chefe de Setor, então não há como escrevê-las aqui.
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

/**
 * Um termo casa no documento da pessoa?
 *
 * Compara sem máscara dos dois lados (o digitado já vem limpo), exige mais de um
 * caractere (um dígito só casaria com quase todo mundo) e casa pelo **começo**,
 * não por trecho no meio.
 *
 * O começo é o que a pessoa digita: ela lê o documento da esquerda para a
 * direita e para quando já achou. Casar no meio fazia `529982` encontrar
 * `77852998224`, e é assim que se abre o prontuário de quem não se procurava —
 * defeito que no sistema irmão virou card de retorno da Qualidade.
 */
function casaNoDocumento(termoSemMascara: string, documento: string | null): boolean {
    return (
        documento !== null &&
        termoSemMascara.length > 1 &&
        semAcento(documento).startsWith(termoSemMascara)
    );
}

/** O selo de cada situação — cor com significado, não decoração. */
function seloDaSituacao(situacao: string): string {
    if (situacao === 'Regular') {
        return 'selo-ok';
    }

    if (situacao === 'Irregular') {
        return 'selo-perigo';
    }

    // Cadastrado em campo: está esperando alguém decidir — nem certo, nem errado.
    return 'selo-aviso';
}

/**
 * As iniciais de quem não tem foto: identidade mínima, nunca um vazio.
 *
 * Só letra e número entram. Sem esse filtro, a primeira letra vinha do que
 * estivesse na posição 0 — e um nome que começasse por pontuação rendia uma
 * inicial como "Z<", que não identifica ninguém. A validação do nome já barra
 * markup; isto é a segunda camada, para o que já está gravado.
 */
function iniciais(nome: string, apelido: string | null): string {
    const palavras = (apelido ?? nome)
        .split(/\s+/)
        .map((palavra) => palavra.replace(/[^\p{L}\p{N}]/gu, ''))
        .filter((palavra) => palavra !== '');

    if (palavras.length === 0) {
        return '';
    }

    const primeira = palavras[0][0] ?? '';
    const ultima = palavras.length > 1 ? (palavras[palavras.length - 1][0] ?? '') : '';

    return `${primeira}${ultima}`.toUpperCase();
}

/** O retrato da pessoa — foto, ou as iniciais dela. */
function Retrato({
    p,
    tamanho = 34,
}: {
    p: Pick<Ambulante, 'nome' | 'apelido' | 'foto_url'>;
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
    /** Texto porque o corpo é multipart (a foto viaja junto) — "1" ou "0". */
    permissionario: string;
    numero_permissao: string;
    validade_permissao: string;
    atividade_id: string;
    situacao: string;
}

function formularioDe(p: Ambulante | null, situacaoPadrao: string): Formulario {
    return {
        nome: p?.nome ?? '',
        apelido: p?.apelido ?? '',
        documento: p?.documento_formatado ?? '',
        rg: p?.rg ?? '',
        telefone: p?.telefone ?? '',
        // Cadastro novo nasce SEM permissão: é a resposta honesta para quem
        // ninguém conferiu, e é também o caso mais comum na rua.
        permissionario: p?.permissionario ? '1' : '0',
        numero_permissao: p?.numero_permissao ?? '',
        validade_permissao: p?.validade_permissao ?? '',
        atividade_id: p ? String(p.atividade_id) : '',
        situacao: p?.situacao ?? situacaoPadrao,
    };
}

export default function CadastroDeAmbulante({
    ambulantes,
    atividades,
    situacoes,
    situacoesDeInclusao,
}: {
    ambulantes: Ambulante[];
    atividades: Atividade[];
    /** As três, para o registro já existente. */
    situacoes: string[];
    /**
     * As que um cadastro pode NASCER com, pela Retaguarda — sem a quarentena,
     * que é estado de origem de rua. Vem do servidor: é a mesma lista que a
     * validação exige.
     */
    situacoesDeInclusao: string[];
}) {
    const [aba, setAba] = useState<Aba>('localizar');
    const [aberto, setAberto] = useState<Ambulante | null>(null);
    const [modo, setModo] = useState<Modo>('edicao');
    // A inclusão de mesa PROPÕE "Regular": é o caso comum de quem cadastra com o
    // documento em mão.
    const [form, setForm] = useState<Formulario>(() =>
        formularioDe(null, situacoesDeInclusao[0] ?? ''),
    );
    const [foto, setFoto] = useState<File | null>(null);
    const [removerFoto, setRemoverFoto] = useState(false);
    const [erros, setErros] = useState<Record<string, string>>({});
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);
    const [busca, setBusca] = useState('');

    const campoFoto = useRef<HTMLInputElement>(null);
    const { enviando, ocupado, enviar, guardar } = useEnvio();

    /*
     * O que esta pessoa pode fazer AQUI, respondido pelo servidor.
     *
     * O fiscal, por exemplo, abre esta tela para consultar — ele cadastra em RUA,
     * pelo aplicativo, e o que nasce em rua espera a conferência do Chefe de Setor. Sem
     * isto a tela oferecia Incluir, Editar e Excluir a ele, e a recusa só aparecia
     * depois do formulário preenchido.
     *
     * As guardas do servidor continuam sendo a fronteira: esconder botão é o
     * conforto de saber antes, nunca a autorização.
     */
    const acoes = useAcoes();

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, [
            // O vocabulário fixo do domínio primeiro: nome de atividade é texto
            // que a chefia digita, e não pode redefinir "regular" ou "vencida".
            ...FACETAS,
            ...facetasDeAtividade(atividades),
        ]);
        const hoje = hojeISO();

        // O texto digitado pode SER um documento: comparar sem máscara faz
        // "123.456.789-09" achar quem está gravado como "12345678909". Fica na
        // mesma ordem dos termos, para o casamento ser por termo (ver abaixo).
        const termosSemMascara = termos.map((t) => t.replace(/[^0-9a-z]/g, ''));

        return ambulantes.filter((p) => {
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

                if (faceta.tipo === 'permissionario' && !p.permissionario) {
                    return false;
                }

                if (faceta.tipo === 'sem-permissao' && p.permissionario) {
                    return false;
                }

                if (
                    faceta.tipo === 'permissao-vencida' &&
                    (p.validade_permissao === null || p.validade_permissao >= hoje)
                ) {
                    return false;
                }
            }

            /*
             * TERMO A TERMO: cada palavra digitada casa no texto OU no documento,
             * e todas têm de casar (E entre termos). Conferir "todos no texto OU
             * todos no documento" quebrava a consulta MISTA — `acaraje
             * 12345678909` não achava ninguém, porque nenhum dos dois lados tinha
             * as duas coisas.
             */
            return termos.every((termo, i) =>
                casaTermos([termo], [
                    p.nome,
                    p.apelido,
                    p.codigo,
                    p.numero_permissao,
                    p.atividade,
                    p.situacao,
                ]) || casaNoDocumento(termosSemMascara[i], p.documento),
            );
        });
    }, [ambulantes, atividades, busca]);

    const ord = useOrdenacao(filtrados, { campo: 'nome', acessor: 'nome' });
    const pag = usePaginacao(ord.itens);

    /*
     * Os números do cabeçalho. Contados sobre a lista INTEIRA que o servidor
     * mandou — não sobre `filtrados` —, porque eles respondem "como está o
     * cadastro", e não "quantos casaram com a busca": mudar de resposta enquanto
     * alguém digita faria o painel de números virar um segundo resultado de busca.
     *
     * As situações vêm do servidor (`situacoes`), então a conta não repete aqui os
     * textos do catálogo — que é o que faria a soma parar de fechar no dia em que
     * uma situação fosse renomeada.
     */
    /*
     * A quarentena, pelo nome que o SERVIDOR usa: é a terceira do catálogo
     * (`Ambulante::SITUACOES`). Escrever "Cadastrado em campo" aqui daria dois
     * donos ao mesmo texto — e no dia em que ele mudasse, a marca de pendência
     * sumiria da grade sem nada quebrar.
     */
    const situacaoDeCampo = situacoes[2] ?? '';

    const numeros = useMemo(() => {
        const [regular, irregular, campo] = situacoes;

        return {
            total: ambulantes.length,
            regulares: ambulantes.filter((p) => p.situacao === regular).length,
            irregulares: ambulantes.filter((p) => p.situacao === irregular)
                .length,
            emCampo: ambulantes.filter((p) => p.situacao === campo).length,
            // Quantos têm permissão da SEMOP. É o número que o cenário novo
            // pede: quem NÃO tem é a maior parte do trabalho de campo, e ler
            // isso de relance é o que a antiga tela de "permissionários" não
            // permitia — ali todo mundo parecia ter.
            comPermissao: ambulantes.filter((p) => p.permissionario).length,
        };
    }, [ambulantes, situacoes]);

    function abrir(p: Ambulante) {
        setAberto(p);
        setForm(formularioDe(p, situacoes[0] ?? ''));
        limparAnexo();
        setErros({});
        setModo('navegacao');
        setAba('registro');
    }

    function incluir() {
        // Quem não inclui não abre o formulário em branco. A tela já esconde as
        // duas portas (a aba e o botão); isto fecha a terceira — uma chamada que
        // sobre de um ajuste futuro.
        if (!acoes.incluir) {
            return;
        }

        setAberto(null);
        setForm(formularioDe(null, situacoesDeInclusao[0] ?? ''));
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
        /*
         * O mesmo piso que o `enviar()` do hook já garante — repetido aqui porque
         * a alteração sai por `router.post` (arquivo não viaja em PUT) e passaria
         * ao lado dessa guarda. Sem isto, dois cliques rápidos no botão de salvar
         * mandariam duas gravações.
         */
        if (ocupado) {
            return;
        }

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
        // Idem: `router.delete` não passa pelo `enviar()`, e exclusão disparada
        // duas vezes é a que menos perdoa.
        if (ocupado || aberto === null) {
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
        // "Não" é resposta, não ausência de resposta — por isso não vira traço.
        permissionario: p.permissionario ? 'Sim' : 'Não',
        numero_permissao: p.numero_permissao || VAZIO,
        validade_permissao: dataBR(p.validade_permissao),
    }));

    const listaDeErros = Object.values(erros);
    const emEdicao = modo === 'edicao';
    // Gravar um cadastro NOVO é "incluir"; gravar alteração no que já existe é
    // "operar" — são ações diferentes na matriz, e o fiscal tem as duas negadas.
    const podeGravar = aberto === null ? acoes.incluir : acoes.habilitado;
    const fotoAtual = aberto?.foto_url ?? null;

    return (
        <>
            <Head title="Ambulantes" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Fiscalização</p>
                    <h1>Ambulantes</h1>
                    <p>
                        Quem é fiscalizado em rua — <strong>com permissão da
                        SEMOP ou sem</strong>. O documento é opcional: muita
                        gente é cadastrada em campo pela foto e pelo apelido — e
                        esse cadastro fica marcado como <strong>Cadastrado em
                        campo</strong> até alguém conferir.
                    </p>
                </div>

                {/* Os números do dia. Saem da MESMA lista que a grade desenha, e
                    não de uma consulta própria: assim eles não podem discordar do
                    que está logo abaixo — e não custam nada ao servidor. */}
                <div className="rt-numeros">
                    <div className="rt-numero">
                        <strong>{numeros.total}</strong>
                        <span>cadastrados</span>
                    </div>
                    <div className="rt-numeros-separador" />
                    {/* Com permissão da SEMOP. Neutro de propósito: não ter
                        permissão não é irregularidade — é o público do trabalho
                        educativo, e pintá-lo de vermelho já daria o veredito. */}
                    <div className="rt-numero">
                        <strong>{numeros.comPermissao}</strong>
                        <span>permissionários</span>
                    </div>
                    <div className="rt-numeros-separador" />
                    <div className="rt-numero ok">
                        <strong>{numeros.regulares}</strong>
                        <span>regulares</span>
                    </div>
                    <div className="rt-numeros-separador" />
                    <div className="rt-numero alerta">
                        <strong>{numeros.irregulares}</strong>
                        <span>irregulares</span>
                    </div>
                    <div className="rt-numeros-separador" />
                    <div className="rt-numero info">
                        <strong>{numeros.emCampo}</strong>
                        <span>a conferir</span>
                    </div>
                </div>
            </div>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Ambulantes">
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

                    {/* A aba de INCLUIR só existe para quem inclui. Com um
                        registro aberto ela é a vista dele, e aí vale para todo
                        mundo que chegou até aqui — inclusive quem só consulta. */}
                    {(aberto !== null || acoes.incluir) && (
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
                    )}
                </div>

                {aba === 'localizar' ? (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            /* O exemplo entra no próprio campo: "procure por
                               nome, apelido…" ensina o que a busca aceita, e a
                               frase de exemplo ensina COMO se pergunta. */
                            placeholder='Nome, apelido, documento, atividade, permissão ou situação — ex.: "irregulares sem permissão"'
                            exemplos={[
                                'permissionários',
                                'sem permissão',
                                'cadastrado em campo',
                                'sem documento',
                                'irregular',
                                'permissão vencida',
                                // Um ramo de verdade, tirado da lista que o
                                // chefia mantém — exemplo escrito à mão aqui
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
                            {acoes.incluir && (
                                <BotaoAcao
                                    icone={<Plus size={16} aria-hidden />}
                                    ocupado={ocupado}
                                    onClick={incluir}
                                >
                                    Incluir
                                </BotaoAcao>
                            )}

                            {/* Exportar é LEITURA: sai o mesmo recorte que a tela
                                já mostrou, então vale para quem só consulta. */}
                            <BotaoExportar
                                titulo="Ambulantes"
                                subtitulo="Fiscalização › Ambulantes"
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
                                        chave: 'permissionario',
                                        titulo: 'Permissionário',
                                        alinhar: 'center',
                                    },
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

                        {/* A pista: a linha abre o cadastro. Cursor em forma de
                            mão é dica de mouse — não existe para quem usa
                            teclado nem para quem lê a tela por leitor. */}
                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela —
                                para abrir o cadastro.
                            </p>
                        )}

                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <ThOrdenavel campo="nome" acessor="nome" ord={ord}>
                                            Ambulante
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="documento"
                                            acessor={(p: Ambulante) =>
                                                p.documento_formatado
                                            }
                                            ord={ord}
                                        >
                                            Documento
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="atividade"
                                            acessor={(p: Ambulante) =>
                                                p.atividade ?? ''
                                            }
                                            ord={ord}
                                        >
                                            Atividade
                                        </ThOrdenavel>
                                        {/* A coluna que o rename tornou
                                            necessária: a grade tem os dois
                                            públicos, e sem ela não se sabe qual
                                            é qual — a validade em branco pode
                                            ser "não tem permissão" ou "tem, mas
                                            ninguém anotou a data". */}
                                        <ThOrdenavel
                                            campo="permissionario"
                                            acessor={(p: Ambulante) =>
                                                p.permissionario ? '0' : '1'
                                            }
                                            ord={ord}
                                        >
                                            Permissão
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="validade_permissao"
                                            acessor={(p: Ambulante) =>
                                                p.validade_permissao ?? ''
                                            }
                                            ord={ord}
                                        >
                                            Validade da permissão
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="situacao"
                                            acessor={(p: Ambulante) => p.situacao}
                                            ord={ord}
                                        >
                                            Situação
                                        </ThOrdenavel>
                                    </tr>
                                </thead>

                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="tabela-vazia">
                                                {ambulantes.length === 0
                                                    ? 'Nenhum ambulante cadastrado ainda. Use "Incluir" para cadastrar o primeiro — é dele que a fiscalização parte.'
                                                    : 'Ninguém casa com a busca. Limpe o campo para ver a base inteira.'}
                                            </td>
                                        </tr>
                                    )}

                                    {pag.visiveis.map((p) => {
                                        const selo = seloDaSituacao(p.situacao);

                                        return (
                                            <tr
                                                key={p.id}
                                                {...linhaClicavel(
                                                    () => abrir(p),
                                                    `Abrir o cadastro de ${p.apelido ?? p.nome}`,
                                                    /* Cadastro nascido em rua
                                                       espera conferência: a linha
                                                       ganha a marca laranja na
                                                       ponta, lida de relance sem
                                                       chegar até a coluna de
                                                       situação. */
                                                    p.situacao === situacaoDeCampo &&
                                                        'pendente',
                                                )}
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
                                                <td>
                                                    {/* Sem permissão NÃO é um
                                                        vazio: é a resposta, e é
                                                        o caso da maioria. Um
                                                        traço aqui seria lido
                                                        como "não sei". */}
                                                    <span
                                                        className={cn(
                                                            'selo',
                                                            p.permissionario
                                                                ? 'selo-info'
                                                                : 'selo-neutro',
                                                        )}
                                                    >
                                                        <span
                                                            className="selo-dot"
                                                            aria-hidden
                                                        />
                                                        {p.permissionario
                                                            ? 'Permissionário'
                                                            : 'Sem permissão'}
                                                    </span>
                                                </td>
                                                <td>{dataBR(p.validade_permissao)}</td>
                                                <td>
                                                    {/* Ponto de cor antes da
                                                        palavra: o estado é lido de
                                                        relance, e a palavra
                                                        confirma. */}
                                                    <span
                                                        className={cn('selo', selo)}
                                                    >
                                                        <span
                                                            className="selo-dot"
                                                            aria-hidden
                                                        />
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
                            {/* Sem nome ainda, a prévia mostra o ícone de pessoa,
                                não um "?": ali entra a FOTO, e uma interrogação
                                de 72px parece erro, não espaço reservado. */}
                            <Retrato
                                p={{
                                    nome: form.nome,
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

                            {/* A pergunta que governa as duas seguintes. Fica
                                ANTES delas de propósito: perguntar o número da
                                permissão para depois perguntar se ela existe
                                seria pedir o dado na ordem contrária. */}
                            <div className="form-group">
                                <label
                                    className="form-label"
                                    htmlFor="perm-permissionario"
                                >
                                    É permissionário da SEMOP?{' '}
                                    <span aria-hidden>*</span>
                                </label>
                                <select
                                    id="perm-permissionario"
                                    className="form-control"
                                    value={form.permissionario}
                                    disabled={!emEdicao}
                                    onChange={(e) => {
                                        const marcado = e.target.value === '1';

                                        /*
                                         * Desmarcar LIMPA número e validade na
                                         * hora. Sem isso os dois seguiriam
                                         * preenchidos, escondidos, e voltariam
                                         * no envio — o servidor os anula, mas
                                         * quem está na tela não veria isso
                                         * acontecer e acharia que a permissão
                                         * continua guardada.
                                         */
                                        setForm((atual) => ({
                                            ...atual,
                                            permissionario: marcado ? '1' : '0',
                                            numero_permissao: marcado
                                                ? atual.numero_permissao
                                                : '',
                                            validade_permissao: marcado
                                                ? atual.validade_permissao
                                                : '',
                                        }));
                                    }}
                                >
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                                <p className="form-ajuda">
                                    A fiscalização encontra os dois na rua. Quem
                                    não tem permissão pode estar regular por
                                    outra via — a <strong>situação</strong> é
                                    outra pergunta.
                                </p>
                                {erros.permissionario && (
                                    <p className="form-erro">
                                        {erros.permissionario}
                                    </p>
                                )}
                            </div>

                            {/* Número e validade só existem para quem TEM
                                permissão. Mostrá-los sempre era o que fazia todo
                                cadastro parecer permissionário. */}
                            {form.permissionario === '1' && (
                                <>
                                    <div className="form-group">
                                        <label
                                            className="form-label"
                                            htmlFor="perm-permissao"
                                        >
                                            Nº da permissão{' '}
                                            <span aria-hidden>*</span>
                                        </label>
                                        <input
                                            id="perm-permissao"
                                            className="form-control"
                                            value={form.numero_permissao}
                                            maxLength={30}
                                            disabled={!emEdicao}
                                            onChange={(e) =>
                                                campo(
                                                    'numero_permissao',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <p className="form-ajuda">
                                            É o número que sustenta a permissão —
                                            sem ele, "é permissionário" não se
                                            confere depois.
                                        </p>
                                        {erros.numero_permissao && (
                                            <p className="form-erro">
                                                {erros.numero_permissao}
                                            </p>
                                        )}
                                    </div>

                                    <div className="form-group">
                                        <label
                                            className="form-label"
                                            htmlFor="perm-validade"
                                        >
                                            Validade da permissão
                                        </label>
                                        {/* A forma ISO existe só aqui dentro, que
                                            é o que o campo de data do navegador
                                            entende; o que a tela MOSTRA é sempre
                                            dd/mm/aaaa. */}
                                        <input
                                            id="perm-validade"
                                            type="date"
                                            className="form-control"
                                            value={form.validade_permissao}
                                            disabled={!emEdicao}
                                            onChange={(e) =>
                                                campo(
                                                    'validade_permissao',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <p className="form-ajuda">
                                            {form.validade_permissao
                                                ? `Vence em ${dataBR(form.validade_permissao)}.`
                                                : 'Pode ficar em branco: em rua o papel está desbotado ou não está com a pessoa, e data inventada faria a busca acusar quem está em dia.'}
                                        </p>
                                        {erros.validade_permissao && (
                                            <p className="form-erro">
                                                {erros.validade_permissao}
                                            </p>
                                        )}
                                    </div>
                                </>
                            )}

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
                                    {/* Na inclusão, sem a quarentena: ela é o
                                        estado de quem foi cadastrado em RUA, e o
                                        servidor recusa (não é só a tela
                                        escondendo). Num registro já existente as
                                        três aparecem — é como o Chefe de Setor devolve à
                                        fila um cadastro duvidoso. */}
                                    {(aberto === null
                                        ? situacoesDeInclusao
                                        : situacoes
                                    ).map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </select>
                                <p className="form-ajuda">
                                    {aberto === null
                                        ? 'Cadastro feito aqui nasce Regular ou Irregular. “Cadastrado em campo” é dos que chegam da rua, pelo aplicativo.'
                                        : '“Cadastrado em campo” devolve o cadastro à fila de conferência.'}
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
                                        <BotaoAcao
                                            icone={<Pencil size={16} aria-hidden />}
                                            ocupado={ocupado}
                                            onClick={() => setModo('edicao')}
                                        >
                                            Editar
                                        </BotaoAcao>
                                    )}
                                </>
                            )}

                            {emEdicao && podeGravar && (
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

CadastroDeAmbulante.layout = {
    breadcrumbs: [{ title: 'Ambulantes', href: index() }],
};

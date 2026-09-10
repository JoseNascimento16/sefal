import { Head } from '@inertiajs/react';
import { Eye, List, Undo2, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import {
    Paginacao,
    useOrdenacao,
    usePaginacao,
} from '@/components/retaguarda/th-ordenavel';
import { casaTermos, parseConsulta, semAcento } from '@/lib/busca';
import { dataBR, hojeISO, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { maskTelefone } from '@/lib/masks';
import { index } from '@/routes/retaguarda/ambulantes';
import { cn } from '@/lib/utils';

/**
 * Ambulantes — CONSULTA da base que o SGCI entrega.
 *
 * ⚠️ Esta tela NÃO CADASTRA. Decisão do dono (10/09/2026): "a tela de Ambulantes
 * não será CRUD, só irá receber os registros do SGCI via integração". O
 * cadastro-mestre é do **SGCI** (o sistema do comércio informal) e aqui ele é
 * espelho de LEITURA — incluir, alterar e excluir saíram da tela **e do servidor**
 * (as rotas deixaram de existir; ver o controller).
 *
 * Duas vistas, em abas: **Localizar** (a base inteira, com busca e exportação) e a
 * **ficha** do ambulante aberto — em `<dl className="rt-ficha">`, e não num
 * formulário desabilitado, de propósito: campo cinza convida a tentar editar e não
 * deixa, o que é exatamente a pessoa procurando o botão que não existe.
 *
 * O desenho continua respondendo à realidade da rua: **o alvo muitas vezes não tem
 * documento**, e a identidade que a tela destaca é a de campo — **foto + apelido**.
 * Sem foto, mostra as iniciais, nunca um espaço vazio.
 *
 * E a tela DIZ de onde vem o dado, no selo do topo e outra vez na ficha: quem não
 * acha o botão de editar precisa descobrir na hora que a correção se faz na ORIGEM,
 * e não que o sistema está incompleto.
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

type Aba = 'localizar' | 'ficha';

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
 * As atividades viram facetas em tempo de execução — a lista é mantida pela
 * Parametrização, então não há como escrevê-las aqui.
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
 * inicial como "Z<", que não identifica ninguém.
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

/**
 * Um campo da ficha de leitura.
 *
 * Ausência sai como traço e com o porquê na dica, nunca como espaço em branco:
 * célula vazia é lida como "o sistema perdeu o dado", e aqui quase sempre
 * significa "o SGCI não informou".
 */
function Campo({ rotulo, children }: { rotulo: string; children: ReactNode }) {
    return (
        <div>
            <dt>{rotulo}</dt>
            <dd>{children}</dd>
        </div>
    );
}

/** O valor, ou o traço cinza de "isto não veio". */
function Valor({ children }: { children: string | null }) {
    return children === null || children === '' ? (
        <span style={{ color: 'var(--sm-texto-fraco)' }}>{VAZIO}</span>
    ) : (
        <>{children}</>
    );
}

export default function Ambulantes({
    ambulantes,
    atividades,
    situacoes,
    listagens,
}: {
    ambulantes: Ambulante[];
    atividades: Atividade[];
    /**
     * O catálogo de situações, do servidor. Não há formulário para alimentar: ele
     * serve aos NÚMEROS do cabeçalho e à marca da fila de conferência na linha —
     * escrito aqui, discordaria do servidor no dia em que uma situação mudasse de
     * nome, e a marca sumiria da grade sem nada quebrar.
     */
    situacoes: string[];
    /**
     * As colunas da aba "Localizar" e as do arquivo, declaradas no servidor. Ver
     * `docs/padroes/listagem-clean.md`.
     */
    listagens: Listagens;
}) {
    const [aba, setAba] = useState<Aba>('localizar');
    const [aberto, setAberto] = useState<Ambulante | null>(null);
    const [busca, setBusca] = useState('');

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, [
            // O vocabulário fixo do domínio primeiro: nome de atividade é texto
            // que a Parametrização digita, e não pode redefinir "regular" ou
            // "vencida".
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
     * A quarentena, pelo nome que o SERVIDOR usa: é a terceira do catálogo
     * (`Ambulante::SITUACOES`). Escrever "Cadastrado em campo" aqui daria dois
     * donos ao mesmo texto — e no dia em que ele mudasse, a marca de pendência
     * sumiria da grade sem nada quebrar.
     */
    const situacaoDeCampo = situacoes[2] ?? '';

    /*
     * Os números do cabeçalho. Contados sobre a lista INTEIRA que o servidor
     * mandou — não sobre `filtrados` —, porque eles respondem "como está a base",
     * e não "quantos casaram com a busca": mudar de resposta enquanto alguém
     * digita faria o painel de números virar um segundo resultado de busca.
     */
    const numeros = useMemo(() => {
        const [regular, irregular, campo] = situacoes;

        return {
            total: ambulantes.length,
            regulares: ambulantes.filter((p) => p.situacao === regular).length,
            irregulares: ambulantes.filter((p) => p.situacao === irregular)
                .length,
            emCampo: ambulantes.filter((p) => p.situacao === campo).length,
            // Quantos têm permissão da SEMOP. Quem NÃO tem é a maior parte do
            // trabalho de campo, e ler isso de relance é o que a antiga tela de
            // "permissionários" não permitia — ali todo mundo parecia ter.
            comPermissao: ambulantes.filter((p) => p.permissionario).length,
        };
    }, [ambulantes, situacoes]);

    function abrir(p: Ambulante) {
        setAberto(p);
        setAba('ficha');
    }

    function voltarParaLista() {
        setAba('localizar');
        setAberto(null);
    }

    /*
     * ── A GRADE ENXUTA ───────────────────────────────────────────────────────
     *
     * Régua em `docs/padroes/listagem-clean.md`. Quem varre esta aba está
     * procurando uma PESSOA, e a identidade prática de campo é foto + apelido
     * (lei do domínio). Então o apelido ganha COLUNA própria em vez de virar a
     * sub-linha embaixo do nome. O código do cadastro desceu com ela.
     *
     * Documento e validade da permissão são chaves de BUSCA (a barra casa o
     * documento; "permissão vencida" é faceta), não colunas de varredura: descem
     * para a ficha aberta e continuam no arquivo exportado.
     */
    const listagem = listagens.ambulantes;

    /** Cinza de apoio — o mesmo em toda célula que diz "isto não existe". */
    const fraco = { color: 'var(--sm-texto-fraco)' };

    /** Como ORDENAR por cada coluna. */
    const acessores: Record<string, AcessorOrd<Ambulante> | undefined> = {
        nome: 'nome',
        apelido: (p) => p.apelido ?? '',
        atividade: (p) => p.atividade ?? '',
        // Permissionário primeiro: quem ordena por esta coluna quer ver quem TEM
        // permissão, não a ordem alfabética de "Não".
        permissionario: (p) => (p.permissionario ? '0' : '1'),
        situacao: 'situacao',
    };

    /** O que cada célula desenha, com o texto inteiro para a dica. */
    function celula(p: Ambulante, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'nome') {
            // O retrato fica: é a identidade de campo, e caber numa linha só é
            // questão de o texto ao lado não ter sub-linha.
            return {
                conteudo: (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 10,
                            minWidth: 0,
                        }}
                    >
                        <Retrato p={p} />
                        <strong
                            style={{
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {p.nome}
                        </strong>
                    </span>
                ),
                dica: `${p.nome} · ${p.codigo}`,
            };
        }

        if (chave === 'apelido') {
            return p.apelido
                ? { conteudo: `“${p.apelido}”`, dica: `Apelido: ${p.apelido}` }
                : {
                      conteudo: <span style={fraco}>{VAZIO}</span>,
                      dica: 'O SGCI não informou apelido para esta pessoa.',
                  };
        }

        if (chave === 'atividade') {
            return { conteudo: p.atividade || VAZIO, dica: p.atividade ?? undefined };
        }

        if (chave === 'permissionario') {
            /* Sem permissão NÃO é um vazio: é a resposta, e é o caso da maioria.
               Um traço aqui seria lido como "não sei". Vai como TEXTO, e não
               como chip: o selo desta linha é o da situação. */
            return {
                conteudo: p.permissionario ? (
                    'Permissionário'
                ) : (
                    <span style={fraco}>Sem permissão</span>
                ),
                dica: p.permissionario
                    ? `Permissão ${p.numero_permissao ?? 'sem número anotado'}`
                      + (p.validade_permissao
                          ? ` · válida até ${dataBR(p.validade_permissao)}`
                          : ' · sem validade anotada')
                    : 'Não tem permissão da SEMOP.',
            };
        }

        return {
            /* Ponto de cor antes da palavra: o estado é lido de relance, e a
               palavra confirma. */
            conteudo: (
                <span className={cn('selo', seloDaSituacao(p.situacao))}>
                    <span className="selo-dot" aria-hidden />
                    {p.situacao}
                </span>
            ),
            dica: p.situacao,
        };
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

    return (
        <>
            <Head title="Ambulantes" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Fiscalização</p>
                    <h1>Ambulantes</h1>
                    <p>
                        Quem é fiscalizado em rua — <strong>com permissão da
                        SEMOP ou sem</strong>. O cadastro é do{' '}
                        <strong>SGCI</strong> e chega aqui por integração: esta
                        tela é <strong>consulta</strong>, e nela você procura a
                        pessoa, abre a ficha dela e exporta o recorte que estiver
                        vendo.
                    </p>
                </div>

                {/* Os números da base. Saem da MESMA lista que a grade desenha, e
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

            {/*
                A ORIGEM DO DADO, dita em cima e sem rodeio.
                Sem isto, quem abre a tela procura o botão de incluir, não acha e
                conclui que o sistema está pela metade — e o card "não consigo
                editar o ambulante" nasce daí. O que se diz são três coisas: de
                onde o dado vem, que aqui é leitura, e onde se corrige.
            */}
            <SeloPrototipo>
                O cadastro de ambulantes é do <strong>SGCI</strong> — o sistema do
                comércio informal — e chega ao SEFAL por{' '}
                <strong>integração</strong>. Aqui ele é <strong>espelho de
                leitura</strong>: não há incluir, alterar nem excluir, e{' '}
                <strong>a correção se faz na origem, no SGCI</strong> — feita aqui,
                ela seria desfeita na carga seguinte. ⚠️ A integração{' '}
                <strong>ainda não existe</strong> (PEND-001): o que você vê são
                registros de exemplo, para aprovar a forma da consulta.
            </SeloPrototipo>

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

                    {/* A segunda aba só existe quando há ficha aberta: sem
                        cadastro para incluir, não há vista em branco a oferecer. */}
                    {aberto !== null && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'ficha'}
                            onClick={() => setAba('ficha')}
                        >
                            <Eye size={16} aria-hidden />
                            <span className="aba-rotulo">
                                {aberto.apelido ?? aberto.nome}
                            </span>
                        </button>
                    )}
                </div>

                {aba === 'localizar' || aberto === null ? (
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
                                // Um ramo de verdade, tirado da lista da
                                // Parametrização — exemplo escrito à mão aqui
                                // envelheceria no primeiro ramo renomeado.
                                ...atividades
                                    .filter((a) => a.ativo)
                                    .slice(0, 1)
                                    .map((a) => a.nome.toLowerCase()),
                            ]}
                        />

                        {/* Exportar é a única ação da tela, e é LEITURA: sai o
                            mesmo recorte que a grade já mostrou. Fica à direita,
                            logo acima da tabela — exportar é conveniência, não
                            ação central. */}
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                marginBottom: 10,
                            }}
                        >
                            <BotaoExportar
                                titulo="Ambulantes"
                                subtitulo="Fiscalização › Ambulantes"
                                contexto={
                                    busca.trim()
                                        ? `Busca: "${busca.trim()}"`
                                        : 'Base completa'
                                }
                                colunas={listagem.exportacao}
                                linhas={linhasExportacao}
                            />
                        </div>

                        {/* A pista: a linha abre a ficha. Cursor em forma de
                            mão é dica de mouse — não existe para quem usa
                            teclado nem para quem lê a tela por leitor. */}
                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela —
                                para abrir a ficha.
                            </p>
                        )}

                        <div className="table-wrap">
                            <table className="data-table enxuta">
                                <thead>
                                    <tr>
                                        {/* Cabeçalho e células saem da MESMA lista
                                            de colunas: escritos em dois lugares,
                                            uma coluna nova entra só num deles e a
                                            grade mostra o valor sob o título
                                            errado. */}
                                        <CabecaDaGrade
                                            grade={listagem.grade}
                                            ord={ord}
                                            acessores={acessores}
                                        />
                                    </tr>
                                </thead>

                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={listagem.grade.length}
                                                className="tabela-vazia"
                                            >
                                                {ambulantes.length === 0
                                                    ? 'Nenhum ambulante recebido ainda. Esta base vem do SGCI, por integração — e a integração não existe até agora (PEND-001). Não há como cadastrar por aqui.'
                                                    : 'Ninguém casa com a busca. Limpe o campo para ver a base inteira.'}
                                            </td>
                                        </tr>
                                    )}

                                    {pag.visiveis.map((p) => (
                                            <tr
                                                key={p.id}
                                                {...linhaClicavel(
                                                    () => abrir(p),
                                                    `Abrir a ficha de ${p.apelido ?? p.nome}`,
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
                                                {listagem.grade.map((coluna) => {
                                                    const { conteudo, dica } = celula(
                                                        p,
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

                        <Paginacao {...pag.props} />
                    </>
                ) : (
                    <>
                        {/* A identidade de campo primeiro: é a foto e o apelido
                            que o fiscal usa para reconhecer a pessoa. */}
                        <div className="rt-detalhe-cabeca">
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 16,
                                    minWidth: 0,
                                }}
                            >
                                <Retrato p={aberto} tamanho={72} />

                                <div style={{ minWidth: 0 }}>
                                    <h2 style={{ margin: 0, fontSize: 19 }}>
                                        {aberto.nome}
                                    </h2>
                                    <p
                                        className="form-ajuda"
                                        style={{ margin: '2px 0 0' }}
                                    >
                                        {aberto.apelido
                                            ? `“${aberto.apelido}” · `
                                            : ''}
                                        Código <strong>{aberto.codigo}</strong> ·
                                        recebido em {dataBR(aberto.cadastrado_em)}
                                    </p>
                                </div>
                            </div>

                            <span
                                className={cn('selo', seloDaSituacao(aberto.situacao))}
                            >
                                <span className="selo-dot" aria-hidden />
                                {aberto.situacao}
                            </span>
                        </div>

                        {/* Ficha de LEITURA (`rt-ficha`), e não formulário
                            desabilitado: campo cinza convida a tentar editar e
                            não deixa — que é justamente a pessoa procurando o
                            botão que não existe mais. */}
                        <dl className="rt-ficha">
                            <Campo rotulo="CPF ou CNPJ">
                                <Valor>{aberto.documento_formatado}</Valor>
                            </Campo>

                            <Campo rotulo="RG">
                                <Valor>{aberto.rg}</Valor>
                            </Campo>

                            <Campo rotulo="Telefone">
                                <Valor>
                                    {aberto.telefone
                                        ? maskTelefone(aberto.telefone)
                                        : null}
                                </Valor>
                            </Campo>

                            <Campo rotulo="Atividade">
                                <Valor>{aberto.atividade}</Valor>
                            </Campo>

                            <Campo rotulo="Permissão da SEMOP">
                                {aberto.permissionario ? (
                                    <>
                                        Permissionário
                                        {aberto.numero_permissao
                                            ? ` · nº ${aberto.numero_permissao}`
                                            : ' · sem número informado'}
                                    </>
                                ) : (
                                    /* "Sem permissão" é a RESPOSTA, e é o caso da
                                       maioria — não é ausência de dado. */
                                    'Sem permissão'
                                )}
                            </Campo>

                            <Campo rotulo="Validade da permissão">
                                {aberto.permissionario ? (
                                    <Valor>
                                        {aberto.validade_permissao
                                            ? dataBR(aberto.validade_permissao)
                                            : null}
                                    </Valor>
                                ) : (
                                    <span style={fraco}>
                                        não se aplica
                                    </span>
                                )}
                            </Campo>
                        </dl>

                        {/*
                            A ORIGEM outra vez, aqui na ficha.
                            É neste ponto que a pessoa procura o botão de editar —
                            o selo do topo já rolou para fora da tela. Repetir a
                            frase onde a dúvida nasce é o que evita a conclusão de
                            que o sistema quebrou.
                        */}
                        <p className="form-ajuda" style={{ marginTop: 18 }}>
                            Estes dados vêm do <strong>SGCI</strong> e não são
                            editáveis aqui. Achou algo errado? A correção se faz
                            no SGCI — na carga seguinte ela aparece nesta tela.
                            {aberto.situacao === situacaoDeCampo
                                ? ' Este cadastro nasceu em rua e ainda espera conferência.'
                                : ''}
                        </p>

                        {/* Botão CRU, e não o `<BotaoAcao>` do Design System: ele
                            existe para ação de ESCRITA (traz a guarda de duplo
                            clique e o spinner), e nesta tela não há nenhuma. */}
                        <div style={{ marginTop: 20 }}>
                            <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                onClick={voltarParaLista}
                            >
                                <Undo2 size={16} aria-hidden /> Voltar
                            </button>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Ambulantes.layout = {
    breadcrumbs: [{ title: 'Ambulantes', href: index() }],
};

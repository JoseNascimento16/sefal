import { router } from '@inertiajs/react';
import { KeyRound, PencilLine, Save, ShieldAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import { Sobreposicao } from '@/components/retaguarda/sobreposicao';
import { useEnvio } from '@/hooks/use-envio';
import { index, salvar } from '@/routes/retaguarda/modo-gerente';

/**
 * Modo Gerente — quem entra onde.
 *
 * Abre SOBRE a tela em que a pessoa está, a partir do item do menu Sistema: quem
 * distribui acesso está conferindo alguma coisa, e mandá-la para outra página
 * fazia perder o lugar. O container é modal; o conteúdo é o mesmo.
 *
 * Uma tabela por tela: as linhas são os setores, as colunas são as cinco ações.
 * A matriz inteira fica à vista de propósito — quem distribui acesso precisa ver
 * o conjunto para notar o que ficou aberto demais —, mas cada tela é salva por
 * si, para que um ajuste num canto não regrave a casa toda.
 *
 * A decisão NÃO mora aqui: este painel só desenha e envia. Quem responde "pode?"
 * é o servidor, nas duas guardas de acesso — inclusive na leitura que alimenta
 * este painel. Esconder um botão é conforto, nunca fronteira.
 */

type Acoes = Record<string, boolean>;

/** As que gravam — e que "Só consulta" derruba. */
const ACOES_DE_ESCRITA = ['habilitado', 'incluir', 'excluir'];

/**
 * Tudo que depende de "Vê". Ele é pré-requisito das demais: sem ele, o servidor
 * zera a linha inteira ao gravar ({@see PermissaoService::normalizar}).
 */
const ACOES_DEPENDENTES = ['habilitado', 'apenas_leitura', 'incluir', 'excluir'];

interface Setor {
    slug: string;
    nome: string;
    /** O administrador aparece marcado e travado: é desvio no código. */
    travado: boolean;
}

interface Funcionalidade {
    slug: string;
    rotulo: string;
    secao: string;
}

interface AcaoDaTela {
    chave: string;
    rotulo: string;
    ajuda: string;
}

interface Registro {
    quando: string;
    quem: string;
    tela: string;
    descricao: string;
}

type Matriz = Record<string, Record<string, Acoes>>;

/** O que o servidor entrega quando este painel pede a matriz. */
interface Dados {
    setores: Setor[];
    funcionalidades: Funcionalidade[];
    matriz: Matriz;
    acoes: AcaoDaTela[];
    enforce: string;
    historico: Registro[];
}

/**
 * O estado do bloqueio dito em voz alta. Sem isto, quem configura acha que tirou
 * um acesso e não tirou: a matriz já vale, mas o modo de observação só observa.
 * Esta tela é exceção — ela é barrada de verdade em qualquer modo.
 */
const AVISO_ENFORCE: Record<string, string | null> = {
    off: 'A conferência de acesso está DESLIGADA neste ambiente: o que você marcar aqui fica guardado, mas ninguém é barrado nas outras telas por enquanto.',
    log: 'A conferência está em modo de observação: nas outras telas o sistema registra quem seria barrado, mas ainda não barra — o bloqueio é ligado no fim desta fase. Esta tela, por ser a que distribui acesso, já barra de verdade.',
    block: null,
};

export function ModoGerentePermissoes({ onFechar }: { onFechar: () => void }) {
    const [dados, setDados] = useState<Dados | null>(null);
    const [carregando, setCarregando] = useState(true);
    const [erro, setErro] = useState<string | null>(null);
    const [rascunho, setRascunho] = useState<Matriz>({});
    const [confirmando, setConfirmando] = useState<Funcionalidade | null>(null);
    const [fechandoComPendencia, setFechandoComPendencia] = useState(false);
    const [saindoPara, setSaindoPara] = useState<string | null>(null);
    const { enviando, ocupado, enviar } = useEnvio();

    const aviso = dados ? (AVISO_ENFORCE[dados.enforce] ?? null) : null;

    /**
     * A matriz vem do servidor por leitura à parte, e não em prop de página: o
     * painel abre sobre QUALQUER tela, e mandar a matriz inteira em toda
     * requisição da Retaguarda seria carregá-la nas 99% das vezes em que ninguém
     * vai abrir o painel.
     *
     * Quem responde é a MESMA rota da guarda de leitura (`GET` do Modo Gerente),
     * então a permissão continua sendo conferida no servidor.
     */
    async function carregar(): Promise<Dados | null> {
        const resposta = await fetch(index().url, {
            headers: { Accept: 'application/json' },
        });

        const corpo = await resposta.json().catch(() => null);

        if (!resposta.ok || corpo === null) {
            // A negativa vem com o motivo escrito pelo servidor: a lei do projeto
            // é que ninguém é barrado em silêncio.
            setErro(
                (corpo as { erro?: string } | null)?.erro ??
                    'Não foi possível carregar as permissões.',
            );

            return null;
        }

        const dados = corpo as Dados;

        setDados(dados);
        setRascunho(dados.matriz);

        return dados;
    }

    useEffect(() => {
        let vivo = true;

        carregar()
            .catch(() => vivo && setErro('Falha ao carregar as permissões.'))
            .finally(() => vivo && setCarregando(false));

        return () => {
            vivo = false;
        };
        // Uma carga só, na abertura: o painel é montado quando abre e
        // desmontado quando fecha.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    /**
     * Quais seções têm alteração ainda não salva.
     *
     * A comparação é com a matriz que veio do servidor, seção por seção — é o que
     * permite dizer ONDE está a pendência. Cada seção tem o seu botão (um ajuste
     * num canto não regrava a casa toda), e sem este aviso quem mexesse em três
     * seções e salvasse uma perdia as outras duas sem nunca saber.
     */
    const pendentes = (dados?.funcionalidades ?? [])
        .filter(
            (tela) =>
                JSON.stringify(rascunho[tela.slug]) !==
                JSON.stringify(dados?.matriz[tela.slug]),
        )
        .map((tela) => tela.slug);

    // Sair com pendência: o pedido de navegação é interrompido e a decisão vai
    // para quem está no painel. Sem isto, a alteração é descartada em silêncio.
    const liberado = useRef(false);

    useEffect(() => {
        if (pendentes.length === 0) {
            return;
        }

        // Recarregar, fechar a aba ou ir para fora do sistema: aqui só o próprio
        // navegador pode perguntar.
        const aoDescarregar = (e: BeforeUnloadEvent) => e.preventDefault();

        window.addEventListener('beforeunload', aoDescarregar);

        // Navegação dentro do sistema: interrompe e pergunta com o diálogo do
        // Design System. Só GET — o POST deste painel é justamente o que salva.
        const remover = router.on('before', (evento) => {
            const visita = evento.detail.visit;

            /*
             * Busca ANTECIPADA não é a pessoa saindo da tela: é o Inertia
             * carregando por baixo uma página que ela talvez visite. Ela dispara
             * este mesmo evento, então sem esta linha o simples passar do mouse
             * sobre um item do menu abriria "sair sem salvar?" — e o pior é que
             * cancelar a visita mataria a busca antecipada de brinde.
             *
             * Hoje nenhum link do projeto pede busca antecipada; a guarda está
             * aqui porque o dia em que alguém puser um `prefetch` num link do
             * menu, o efeito apareceria neste painel e ninguém ligaria as duas
             * coisas.
             */
            if (visita.prefetch) {
                return;
            }

            if (
                liberado.current ||
                String(visita.method).toLowerCase() !== 'get'
            ) {
                return;
            }

            setSaindoPara(String(visita.url));

            return false;
        });

        return () => {
            window.removeEventListener('beforeunload', aoDescarregar);
            remover();
        };
    }, [pendentes.length]);

    function marcar(tela: string, setor: string, acao: string, valor: boolean) {
        setRascunho((atual) => {
            const linha = { ...atual[tela][setor], [acao]: valor };

            // "Só consulta" derruba na hora o que grava — a mesma regra que o
            // servidor aplica ao gravar. Sem isto, a tela mostraria por um
            // instante um estado que o servidor vai recusar, e quem marcou
            // pensaria que concedeu.
            if (acao === 'apenas_leitura' && valor) {
                for (const escrita of ACOES_DE_ESCRITA) {
                    linha[escrita] = false;
                }
            }

            /*
             * Tirar o "Vê" derruba TODO o resto — é a mesma normalização que o
             * servidor faz ao gravar, e a legenda deste painel já prometia isso
             * em voz alta ("sem ele, nada mais vale").
             *
             * Faltava aqui, e o efeito era pior que uma inconsistência de
             * desenho: quem desmarcava "Vê" via "Inclui" e "Exclui" continuarem
             * marcados, salvava, e saía convencido de ter concedido as duas ao
             * setor — quando o que foi gravado nega tudo.
             */
            if (acao === 'visivel' && !valor) {
                for (const dependente of ACOES_DEPENDENTES) {
                    linha[dependente] = false;
                }
            }

            return {
                ...atual,
                [tela]: { ...atual[tela], [setor]: linha },
            };
        });
    }

    function gravar(tela: Funcionalidade) {
        enviar(
            tela.slug,
            salvar(),
            {
                slug: tela.slug,
                matriz: (dados?.setores ?? [])
                    // O administrador não é concessão: mandá-lo não faria nada
                    // no servidor, e mandar o que não tem efeito confunde quem
                    // for ler a requisição amanhã.
                    .filter((setor) => !setor.travado)
                    .map((setor) => ({
                        setor: setor.slug,
                        ...rascunho[tela.slug][setor.slug],
                    })),
            },
            {
                // O painel fica onde está — quem salvou está no meio de uma
                // conferência e a tela por baixo não tem nada a ver com isso.
                preserveScroll: true,
                preserveState: true,
                // A matriz é recarregada em vez de espelhada a partir do
                // rascunho: o servidor NORMALIZA a linha ao gravar, e assumir
                // que o gravado é igual ao pedido mostraria como salvo um estado
                // que o banco recusou. O histórico também é relido — a
                // alteração que se acabou de fazer aparece nele.
                onSuccess: () => void carregar(),
                onFinish: () => setConfirmando(null),
            },
        );
    }

    /** Fechar com alteração pendente pergunta antes de descartar. */
    function pedirFechamento() {
        if (pendentes.length > 0) {
            setFechandoComPendencia(true);

            return;
        }

        onFechar();
    }

    /*
     * Esc fecha — mas só quando o painel é a camada de cima. Com uma confirmação
     * aberta por cima dele, é ela quem responde ao Esc (`ModalConfirm` já trata):
     * sem esta condição, uma tecla fecharia as duas camadas de uma vez e a pessoa
     * ficaria sem saber se a gravação aconteceu.
     */
    const temCamadaEmCima =
        confirmando !== null || fechandoComPendencia || saindoPara !== null;

    useEffect(() => {
        if (temCamadaEmCima) {
            return;
        }

        const tecla = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                pedirFechamento();
            }
        };

        document.addEventListener('keydown', tecla);

        return () => document.removeEventListener('keydown', tecla);
        // `pedirFechamento` lê o número de pendências no momento do clique.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [temCamadaEmCima, pendentes.length]);

    return (
        <>
            {/* A camada é a MESMA de toda janela sobreposta da Retaguarda: é ela
                que trava a rolagem de trás, neutraliza o fundo para o teclado e
                sai do lugar onde foi declarada. Uma camada própria aqui daria
                dois donos à mesma responsabilidade. */}
            <Sobreposicao clicandoFora={pedirFechamento}>
                <div
                    className="card-premium mg-painel"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Modo Gerente — quem entra onde"
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="mg-cabeca">
                        <div>
                            <p className="sobrancelha">Sistema</p>
                            <p className="card-titulo" style={{ fontSize: 19 }}>
                                <KeyRound size={19} aria-hidden />
                                Modo Gerente
                            </p>
                            <p className="card-sub">
                                Quem entra em cada tela, por setor. O
                                administrador enxerga tudo e não entra na conta.
                            </p>
                        </div>

                        <button
                            type="button"
                            className="icon-btn"
                            title="Fechar"
                            aria-label="Fechar"
                            onClick={pedirFechamento}
                        >
                            <X size={17} aria-hidden />
                        </button>
                    </div>

                    <div className="mg-corpo">
                        {carregando ? (
                            <p className="form-ajuda">Carregando…</p>
                        ) : erro !== null ? (
                            <p className="form-erro">
                                <ShieldAlert size={15} aria-hidden />
                                {erro}
                            </p>
                        ) : dados === null ? null : (
                            <>
                                {aviso && (
                                    <p
                                        className="card-sub"
                                        style={{
                                            display: 'flex',
                                            alignItems: 'flex-start',
                                            gap: 10,
                                            marginBottom: 18,
                                        }}
                                    >
                                        <ShieldAlert
                                            size={18}
                                            aria-hidden
                                            style={{
                                                flexShrink: 0,
                                                marginTop: 2,
                                            }}
                                        />
                                        {aviso}
                                    </p>
                                )}

                                {dados.funcionalidades.length === 0 ? (
                                    <>
                                        <p className="card-titulo">
                                            Nenhuma tela sob controle ainda
                                        </p>
                                        <p className="card-sub">
                                            As telas entram aqui à medida que são
                                            construídas. Não há nada a conceder
                                            por enquanto.
                                        </p>
                                    </>
                                ) : (
                                    dados.funcionalidades.map((tela) => (
                                        <section
                                            key={tela.slug}
                                            className="mg-tela"
                                        >
                                            <div className="mg-tela-cabeca">
                                                <div>
                                                    <p className="card-titulo">
                                                        {tela.rotulo}
                                                    </p>
                                                    <p className="card-sub">
                                                        {tela.secao}
                                                    </p>
                                                </div>

                                                {pendentes.includes(
                                                    tela.slug,
                                                ) && (
                                                    <span
                                                        className="selo selo-aviso"
                                                        style={{
                                                            marginLeft: 'auto',
                                                        }}
                                                    >
                                                        <PencilLine
                                                            size={13}
                                                            aria-hidden
                                                        />
                                                        alteração não salva
                                                    </span>
                                                )}
                                            </div>

                                            <div className="table-wrap">
                                                <table className="data-table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">
                                                                Setor
                                                            </th>
                                                            {dados.acoes.map(
                                                                (acao) => (
                                                                    <th
                                                                        key={
                                                                            acao.chave
                                                                        }
                                                                        scope="col"
                                                                        title={
                                                                            acao.ajuda
                                                                        }
                                                                    >
                                                                        {
                                                                            acao.rotulo
                                                                        }
                                                                    </th>
                                                                ),
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {dados.setores.map(
                                                            (setor) => (
                                                                <tr
                                                                    key={
                                                                        setor.slug
                                                                    }
                                                                >
                                                                    <td>
                                                                        {
                                                                            setor.nome
                                                                        }
                                                                        {setor.travado && (
                                                                            <span
                                                                                className="selo selo-info"
                                                                                style={{
                                                                                    marginLeft: 8,
                                                                                }}
                                                                            >
                                                                                acesso
                                                                                total
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    {dados.acoes.map(
                                                                        (
                                                                            acao,
                                                                        ) => {
                                                                            const linha =
                                                                                rascunho[
                                                                                    tela
                                                                                        .slug
                                                                                ][
                                                                                    setor
                                                                                        .slug
                                                                                ];

                                                                            /*
                                                                             * Marca indisponível é a
                                                                             * regra do servidor visível
                                                                             * na tela, em vez de uma
                                                                             * recusa depois do clique.
                                                                             * Duas razões, e a ordem
                                                                             * importa: sem "Vê" nada
                                                                             * mais vale, e "Só consulta"
                                                                             * derruba o que grava.
                                                                             */
                                                                            const semVer =
                                                                                !linha.visivel &&
                                                                                ACOES_DEPENDENTES.includes(
                                                                                    acao.chave,
                                                                                );
                                                                            const soConsulta =
                                                                                linha.apenas_leitura &&
                                                                                ACOES_DE_ESCRITA.includes(
                                                                                    acao.chave,
                                                                                );
                                                                            const impedida =
                                                                                semVer ||
                                                                                soConsulta;

                                                                            return (
                                                                                <td
                                                                                    key={
                                                                                        acao.chave
                                                                                    }
                                                                                >
                                                                                    <input
                                                                                        type="checkbox"
                                                                                        aria-label={`${acao.rotulo} — ${setor.nome} em ${tela.rotulo}`}
                                                                                        checked={Boolean(
                                                                                            linha[
                                                                                                acao
                                                                                                    .chave
                                                                                            ],
                                                                                        )}
                                                                                        disabled={
                                                                                            setor.travado ||
                                                                                            impedida
                                                                                        }
                                                                                        title={
                                                                                            semVer
                                                                                                ? 'Indisponível: sem "Vê", nada mais vale para este setor.'
                                                                                                : soConsulta
                                                                                                  ? 'Indisponível: o setor está marcado como "Só consulta".'
                                                                                                  : undefined
                                                                                        }
                                                                                        onChange={(
                                                                                            e,
                                                                                        ) =>
                                                                                            marcar(
                                                                                                tela.slug,
                                                                                                setor.slug,
                                                                                                acao.chave,
                                                                                                e
                                                                                                    .target
                                                                                                    .checked,
                                                                                            )
                                                                                        }
                                                                                    />
                                                                                </td>
                                                                            );
                                                                        },
                                                                    )}
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>

                                            <p
                                                className="form-ajuda"
                                                style={{ marginTop: 12 }}
                                            >
                                                &quot;Vê&quot; é pré-requisito
                                                das demais: sem ele, nada mais
                                                vale. &quot;Só consulta&quot;
                                                derruba tudo que grava — operar,
                                                incluir e excluir.
                                            </p>

                                            <div
                                                style={{
                                                    display: 'flex',
                                                    justifyContent: 'flex-end',
                                                    marginTop: 14,
                                                }}
                                            >
                                                {/* Sem alteração, o botão não tem
                                                    o que salvar — e um botão que
                                                    grava o mesmo estado de novo só
                                                    polui o rastro de auditoria
                                                    com "nada mudou". */}
                                                <BotaoAcao
                                                    icone={<Save size={16} />}
                                                    carregando={
                                                        enviando === tela.slug
                                                    }
                                                    ocupado={ocupado}
                                                    disabled={
                                                        !pendentes.includes(
                                                            tela.slug,
                                                        )
                                                    }
                                                    title={
                                                        pendentes.includes(
                                                            tela.slug,
                                                        )
                                                            ? undefined
                                                            : 'Nada alterado nesta tela.'
                                                    }
                                                    rotuloCarregando="Salvando…"
                                                    onClick={() =>
                                                        setConfirmando(tela)
                                                    }
                                                >
                                                    Salvar permissões
                                                </BotaoAcao>
                                            </div>
                                        </section>
                                    ))
                                )}

                                {/* A seção existe SEMPRE, inclusive vazia: numa
                                    instalação nova, sem ela ninguém saberia que
                                    há trilha de auditoria — e trilha que ninguém
                                    sabe que existe não responde pergunta
                                    nenhuma. */}
                                <section className="mg-tela">
                                    <p className="card-titulo">
                                        Últimas alterações
                                    </p>
                                    <p
                                        className="card-sub"
                                        style={{ marginBottom: 14 }}
                                    >
                                        Para se poder perguntar depois quem abriu
                                        qual porta, para quem.
                                    </p>

                                    {dados.historico.length === 0 ? (
                                        <p className="form-ajuda">
                                            Nenhuma alteração de permissão
                                            registrada ainda. A primeira gravação
                                            aqui aparece nesta lista, com o que
                                            mudou em cada setor.
                                        </p>
                                    ) : (
                                        <div className="table-wrap">
                                            <table className="data-table">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">
                                                            Quando
                                                        </th>
                                                        <th scope="col">Quem</th>
                                                        <th scope="col">Tela</th>
                                                        {/* O QUE mudou é a razão
                                                            de o registro existir:
                                                            "alguém mexeu nesta
                                                            tela" não responde
                                                            nada depois de um
                                                            incidente. */}
                                                        <th scope="col">
                                                            O que mudou
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {dados.historico.map(
                                                        (registro, i) => (
                                                            <tr
                                                                key={`${registro.quando}-${i}`}
                                                            >
                                                                <td>
                                                                    {
                                                                        registro.quando
                                                                    }
                                                                </td>
                                                                <td>
                                                                    {
                                                                        registro.quem
                                                                    }
                                                                </td>
                                                                <td>
                                                                    {
                                                                        registro.tela
                                                                    }
                                                                </td>
                                                                <td
                                                                    style={{
                                                                        minWidth: 260,
                                                                    }}
                                                                >
                                                                    {
                                                                        registro.descricao
                                                                    }
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </section>
                            </>
                        )}
                    </div>
                </div>
            </Sobreposicao>

            {confirmando && (
                <ModalConfirm
                    titulo={`Salvar as permissões de ${confirmando.rotulo}?`}
                    mensagem="Quem ganhar ou perder acesso vê a mudança na próxima navegação — inclusive quem já está no sistema agora."
                    rotuloConfirmar="Salvar permissões"
                    iconeConfirmar={<Save size={16} />}
                    processando={enviando === confirmando.slug}
                    onCancelar={() => setConfirmando(null)}
                    onConfirmar={() => gravar(confirmando)}
                />
            )}

            {fechandoComPendencia && (
                <ModalConfirm
                    titulo="Fechar sem salvar as permissões?"
                    mensagem={
                        pendentes.length === 1
                            ? 'Há alteração não salva em uma das telas. Fechar agora descarta essa alteração.'
                            : `Há alterações não salvas em ${pendentes.length} telas. Fechar agora descarta todas.`
                    }
                    rotuloConfirmar="Fechar sem salvar"
                    rotuloCancelar="Continuar aqui"
                    destrutiva
                    onCancelar={() => setFechandoComPendencia(false)}
                    onConfirmar={onFechar}
                />
            )}

            {saindoPara !== null && (
                <ModalConfirm
                    titulo="Sair sem salvar as permissões?"
                    mensagem={
                        pendentes.length === 1
                            ? 'Há alteração não salva em uma das telas. Sair agora descarta essa alteração.'
                            : `Há alterações não salvas em ${pendentes.length} telas. Sair agora descarta todas.`
                    }
                    rotuloConfirmar="Sair sem salvar"
                    rotuloCancelar="Continuar aqui"
                    destrutiva
                    onCancelar={() => setSaindoPara(null)}
                    onConfirmar={() => {
                        // A guarda se abre uma vez, para a navegação que a pessoa
                        // acabou de confirmar.
                        liberado.current = true;
                        onFechar();
                        router.visit(saindoPara);
                    }}
                />
            )}
        </>
    );
}

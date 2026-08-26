import { Head, router } from '@inertiajs/react';
import { KeyRound, PencilLine, Save, ShieldAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import { useEnvio } from '@/hooks/use-envio';
import { index, salvar } from '@/routes/retaguarda/modo-gerente';

/**
 * Modo Gerente — quem entra onde.
 *
 * Uma tabela por tela: as linhas são os setores, as colunas são as cinco ações.
 * A matriz inteira fica à vista de propósito — quem distribui acesso precisa ver
 * o conjunto para notar o que ficou aberto demais —, mas cada tela é salva por
 * si, para que um ajuste num canto não regrave a casa toda.
 *
 * A decisão NÃO mora aqui: esta tela só desenha e envia. Quem responde "pode?" é
 * o servidor, nas duas guardas de acesso. Esconder um botão é conforto, nunca
 * fronteira.
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

export default function ModoGerente({
    setores,
    funcionalidades,
    matriz,
    acoes,
    enforce,
    historico,
}: {
    setores: Setor[];
    funcionalidades: Funcionalidade[];
    matriz: Matriz;
    acoes: AcaoDaTela[];
    enforce: string;
    historico: Registro[];
}) {
    const [rascunho, setRascunho] = useState<Matriz>(matriz);
    const [confirmando, setConfirmando] = useState<Funcionalidade | null>(null);
    const [saindoPara, setSaindoPara] = useState<string | null>(null);
    const { enviando, ocupado, enviar } = useEnvio();

    const aviso = AVISO_ENFORCE[enforce] ?? null;

    /**
     * Quais seções têm alteração ainda não salva.
     *
     * A comparação é com a matriz que veio do servidor, seção por seção — é o que
     * permite dizer ONDE está a pendência. Cada seção tem o seu botão (um ajuste
     * num canto não regrava a casa toda), e sem este aviso quem mexesse em três
     * seções e salvasse uma perdia as outras duas sem nunca saber.
     */
    const pendentes = funcionalidades
        .filter(
            (tela) =>
                JSON.stringify(rascunho[tela.slug]) !==
                JSON.stringify(matriz[tela.slug]),
        )
        .map((tela) => tela.slug);

    // Sair com pendência: o pedido de navegação é interrompido e a decisão vai
    // para quem está na tela. Sem isto, a alteração é descartada em silêncio.
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
        // Design System. Só GET — o POST desta tela é justamente o que salva.
        const remover = router.on('before', (evento) => {
            const visita = evento.detail.visit;

            if (liberado.current || String(visita.method).toLowerCase() !== 'get') {
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
             * servidor faz ao gravar, e a legenda desta tela já prometia isso em
             * voz alta ("sem ele, nada mais vale").
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
                matriz: setores
                    // O administrador não é concessão: mandá-lo não faria nada
                    // no servidor, e mandar o que não tem efeito confunde quem
                    // for ler a requisição amanhã.
                    .filter((setor) => !setor.travado)
                    .map((setor) => ({
                        setor: setor.slug,
                        ...rascunho[tela.slug][setor.slug],
                    })),
            },
            { onFinish: () => setConfirmando(null) },
        );
    }

    return (
        <>
            <Head title="Modo Gerente" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Modo Gerente</h1>
                    <p>
                        Quem entra em cada tela, por setor. O administrador
                        enxerga tudo e não entra na conta.
                    </p>
                </div>
            </div>

            {aviso && (
                <div className="card-premium" style={{ marginBottom: 20 }}>
                    <p
                        className="card-sub"
                        style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            gap: 10,
                        }}
                    >
                        <ShieldAlert
                            size={18}
                            aria-hidden
                            style={{ flexShrink: 0, marginTop: 2 }}
                        />
                        {aviso}
                    </p>
                </div>
            )}

            {funcionalidades.length === 0 ? (
                <div className="card-premium">
                    <p className="card-titulo">
                        Nenhuma tela sob controle ainda
                    </p>
                    <p className="card-sub">
                        As telas entram aqui à medida que são construídas. Não
                        há nada a conceder por enquanto.
                    </p>
                </div>
            ) : (
                funcionalidades.map((tela) => (
                    <section
                        key={tela.slug}
                        className="card-premium"
                        style={{ marginBottom: 20 }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                marginBottom: 14,
                            }}
                        >
                            <KeyRound size={18} aria-hidden />
                            <div>
                                <p className="card-titulo">{tela.rotulo}</p>
                                <p className="card-sub">{tela.secao}</p>
                            </div>

                            {pendentes.includes(tela.slug) && (
                                <span
                                    className="selo selo-aviso"
                                    style={{ marginLeft: 'auto' }}
                                >
                                    <PencilLine size={13} aria-hidden />
                                    alteração não salva
                                </span>
                            )}
                        </div>

                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Setor</th>
                                        {acoes.map((acao) => (
                                            <th
                                                key={acao.chave}
                                                scope="col"
                                                title={acao.ajuda}
                                            >
                                                {acao.rotulo}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {setores.map((setor) => (
                                        <tr key={setor.slug}>
                                            <td>
                                                {setor.nome}
                                                {setor.travado && (
                                                    <span
                                                        className="selo selo-info"
                                                        style={{
                                                            marginLeft: 8,
                                                        }}
                                                    >
                                                        acesso total
                                                    </span>
                                                )}
                                            </td>
                                            {acoes.map((acao) => {
                                                const linha =
                                                    rascunho[tela.slug][
                                                        setor.slug
                                                    ];

                                                /*
                                                 * Marca indisponível é a regra
                                                 * do servidor visível na tela,
                                                 * em vez de uma recusa depois do
                                                 * clique. Duas razões, e a
                                                 * ordem importa: sem "Vê" nada
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
                                                    semVer || soConsulta;

                                                return (
                                                    <td key={acao.chave}>
                                                        <input
                                                            type="checkbox"
                                                            aria-label={`${acao.rotulo} — ${setor.nome} em ${tela.rotulo}`}
                                                            checked={Boolean(
                                                                linha[
                                                                    acao.chave
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
                                                            onChange={(e) =>
                                                                marcar(
                                                                    tela.slug,
                                                                    setor.slug,
                                                                    acao.chave,
                                                                    e.target
                                                                        .checked,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <p className="form-ajuda" style={{ marginTop: 12 }}>
                            &quot;Vê&quot; é pré-requisito das demais: sem ele,
                            nada mais vale. &quot;Só consulta&quot; derruba tudo
                            que grava — operar, incluir e excluir.
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                marginTop: 14,
                            }}
                        >
                            {/* Sem alteração, o botão não tem o que salvar — e
                                um botão que grava o mesmo estado de novo só
                                polui o rastro de auditoria com "nada mudou". */}
                            <BotaoAcao
                                icone={<Save size={16} />}
                                carregando={enviando === tela.slug}
                                ocupado={ocupado}
                                disabled={!pendentes.includes(tela.slug)}
                                title={
                                    pendentes.includes(tela.slug)
                                        ? undefined
                                        : 'Nada alterado nesta tela.'
                                }
                                rotuloCarregando="Salvando…"
                                onClick={() => setConfirmando(tela)}
                            >
                                Salvar permissões
                            </BotaoAcao>
                        </div>
                    </section>
                ))
            )}

            {/* A seção existe SEMPRE, inclusive vazia: numa instalação nova, sem
                ela ninguém saberia que há trilha de auditoria — e trilha que
                ninguém sabe que existe não responde pergunta nenhuma. */}
            <section className="card-premium">
                <p className="card-titulo">Últimas alterações</p>
                <p className="card-sub" style={{ marginBottom: 14 }}>
                    Para se poder perguntar depois quem abriu qual porta, para
                    quem.
                </p>

                {historico.length === 0 ? (
                    <p className="form-ajuda">
                        Nenhuma alteração de permissão registrada ainda. A
                        primeira gravação nesta tela aparece aqui, com o que
                        mudou em cada setor.
                    </p>
                ) : (
                    <div className="table-wrap">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Quando</th>
                                    <th scope="col">Quem</th>
                                    <th scope="col">Tela</th>
                                    {/* O QUE mudou é a razão de o registro
                                        existir: "alguém mexeu nesta tela" não
                                        responde nada depois de um incidente. O
                                        servidor já gravava o delta por setor
                                        (`+incluir`, `-excluir`) — a tela é que
                                        não o mostrava. */}
                                    <th scope="col">O que mudou</th>
                                </tr>
                            </thead>
                            <tbody>
                                {historico.map((registro, i) => (
                                    <tr key={`${registro.quando}-${i}`}>
                                        <td>{registro.quando}</td>
                                        <td>{registro.quem}</td>
                                        <td>{registro.tela}</td>
                                        <td style={{ minWidth: 260 }}>
                                            {registro.descricao}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

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
                        // acabou de confirmar — e volta a valer na próxima tela.
                        liberado.current = true;
                        router.visit(saindoPara);
                    }}
                />
            )}
        </>
    );
}

ModoGerente.layout = {
    breadcrumbs: [
        {
            title: 'Modo Gerente',
            href: index(),
        },
    ],
};

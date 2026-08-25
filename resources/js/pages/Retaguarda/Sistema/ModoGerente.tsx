import { Head } from '@inertiajs/react';
import { KeyRound, Save, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
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
    const { enviando, ocupado, enviar } = useEnvio();

    const aviso = AVISO_ENFORCE[enforce] ?? null;

    function marcar(
        tela: string,
        setor: string,
        acao: string,
        valor: boolean,
    ) {
        setRascunho((atual) => ({
            ...atual,
            [tela]: {
                ...atual[tela],
                [setor]: { ...atual[tela][setor], [acao]: valor },
            },
        }));
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
                    <p className="card-titulo">Nenhuma tela sob controle ainda</p>
                    <p className="card-sub">
                        As telas entram aqui à medida que são construídas. Não há
                        nada a conceder por enquanto.
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
                                                        style={{ marginLeft: 8 }}
                                                    >
                                                        acesso total
                                                    </span>
                                                )}
                                            </td>
                                            {acoes.map((acao) => (
                                                <td key={acao.chave}>
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`${acao.rotulo} — ${setor.nome} em ${tela.rotulo}`}
                                                        checked={Boolean(
                                                            rascunho[tela.slug][
                                                                setor.slug
                                                            ][acao.chave],
                                                        )}
                                                        disabled={setor.travado}
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
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <p className="form-ajuda" style={{ marginTop: 12 }}>
                            &quot;Vê&quot; é pré-requisito das demais: sem ele,
                            nada mais vale. &quot;Só consulta&quot; derruba
                            incluir e excluir.
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                marginTop: 14,
                            }}
                        >
                            <BotaoAcao
                                icone={<Save size={16} />}
                                carregando={enviando === tela.slug}
                                ocupado={ocupado}
                                rotuloCarregando="Salvando…"
                                onClick={() => setConfirmando(tela)}
                            >
                                Salvar permissões
                            </BotaoAcao>
                        </div>
                    </section>
                ))
            )}

            {historico.length > 0 && (
                <section className="card-premium">
                    <p className="card-titulo">Últimas alterações</p>
                    <p className="card-sub" style={{ marginBottom: 14 }}>
                        Para se poder perguntar depois quem abriu qual porta.
                    </p>

                    <div className="table-wrap">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Quando</th>
                                    <th scope="col">Quem</th>
                                    <th scope="col">Tela</th>
                                </tr>
                            </thead>
                            <tbody>
                                {historico.map((registro, i) => (
                                    <tr key={`${registro.quando}-${i}`}>
                                        <td>{registro.quando}</td>
                                        <td>{registro.quem}</td>
                                        <td>{registro.tela}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

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

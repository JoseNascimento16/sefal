import { Head, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    ExternalLink,
    Info,
    RefreshCw,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { BotaoAcao, Spinner } from '@/components/retaguarda/acao';
import { contar } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { index, profundo } from '@/routes/retaguarda/monitoramento';

/**
 * Monitoramento — o painel de "tudo verde, sistema operacional".
 *
 * A promessa da tela é essa frase: com tudo verde aqui, os fluxos do sistema
 * funcionam. Ela existe porque a alteração destrutiva de uma parametrização não
 * avisa ninguém — alguém desliga a última conta de administrador, e o problema
 * só aparece dias depois, na mão de quem precisou conceder um acesso.
 *
 * A tela SAUDÁVEL é uma coluna de cards fechados: card com pendência abre
 * sozinho, e as falhas vêm primeiro. Ninguém precisa procurar o que está errado.
 *
 * Aqui não se corrige nada: cada item vermelho leva PARA ONDE se corrige, ou diz
 * o que pedir a quem administra o ambiente.
 */

type Status = 'ok' | 'aviso' | 'falha';

interface Check {
    id: string;
    titulo: string;
    status: Status;
    detalhe: string;
    acao_url: string | null;
    acao_rotulo: string | null;
    instrucao: string | null;
    /** Tem teste real (disco/rede) que só roda pelo botão. */
    profundo: boolean;
}

/**
 * Um módulo e as suas verificações. A CONTA (quantas falham, quantas avisam) não
 * vem do servidor de propósito: o teste a fundo troca o estado de um check depois
 * que a página já está aberta, e um número vindo pronto envelheceria nesse
 * instante — a faixa-resumo diria "tudo certo" com um item vermelho logo abaixo.
 */
interface Modulo {
    modulo: string;
    checks: Check[];
}

/** Como cada estado se apresenta: selo, ícone e ordem de leitura. */
const ESTADO: Record<
    Status,
    { selo: string; Icone: typeof CircleCheck; peso: number }
> = {
    falha: { selo: 'selo-perigo', Icone: CircleAlert, peso: 0 },
    aviso: { selo: 'selo-aviso', Icone: TriangleAlert, peso: 1 },
    ok: { selo: 'selo-ok', Icone: CircleCheck, peso: 2 },
};

export default function MonitoramentoDeParametrizacoes({
    modulos,
    verificadoEm,
}: {
    modulos: Modulo[];
    verificadoEm: string;
}) {
    // Resultado das verificações profundas: substitui o estado do check de mesmo
    // id (é o que torna o id único uma lei, e não um detalhe).
    const [profundos, setProfundos] = useState<Record<string, Check>>({});
    const [testando, setTestando] = useState(false);
    const [carimbo, setCarimbo] = useState(verificadoEm);
    const [fechados, setFechados] = useState<Record<string, boolean>>({});

    const resolvido = (check: Check): Check => profundos[check.id] ?? check;

    const totais = modulos.reduce(
        (soma, m) => {
            const checks = m.checks.map(resolvido);

            return {
                total: soma.total + checks.length,
                falhas:
                    soma.falhas +
                    checks.filter((c) => c.status === 'falha').length,
                avisos:
                    soma.avisos +
                    checks.filter((c) => c.status === 'aviso').length,
            };
        },
        { total: 0, falhas: 0, avisos: 0 },
    );

    const temProfundo = modulos.some((m) => m.checks.some((c) => c.profundo));

    async function testarAFundo() {
        setTestando(true);

        try {
            const resposta = await fetch(profundo().url, {
                headers: { Accept: 'application/json' },
            });
            const dados = await resposta.json();

            setProfundos(dados?.resultados ?? {});
            setCarimbo(String(dados?.verificadoEm ?? carimbo));
        } catch {
            // Falhar em silêncio aqui seria o pior dos mundos: a pessoa clicaria
            // no botão de diagnóstico e não saberia se passou ou se nem rodou.
            setProfundos({});
            setCarimbo('não foi possível concluir o teste — tente novamente');
        } finally {
            setTestando(false);
        }
    }

    return (
        <>
            <Head title="Monitoramento" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Monitoramento</h1>
                    <p>
                        As condições mínimas para o sistema funcionar. Com tudo
                        verde, os fluxos estão operacionais; o que estiver
                        vermelho diz o que parou e leva para onde se corrige.
                    </p>
                </div>

                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                    <BotaoAcao
                        className="btn btn-secondary btn-sm"
                        icone={<RefreshCw size={15} aria-hidden />}
                        onClick={() => router.reload()}
                    >
                        Verificar de novo
                    </BotaoAcao>

                    {temProfundo && (
                        <BotaoAcao
                            className="btn btn-secondary btn-sm"
                            icone={<CircleCheck size={15} aria-hidden />}
                            carregando={testando}
                            rotuloCarregando="Testando…"
                            title="Faz o teste real (escreve no armazenamento, fala com serviços externos). Pode demorar alguns segundos."
                            onClick={testarAFundo}
                        >
                            Testar a fundo
                        </BotaoAcao>
                    )}
                </div>
            </div>

            {/* A faixa-resumo responde a pergunta em uma linha, antes de qualquer
                card: alguém abre esta tela para saber SE algo está errado. */}
            <div
                className="card-premium"
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    marginBottom: 18,
                    borderLeft: `4px solid var(--sm-${
                        totais.falhas > 0
                            ? 'perigo'
                            : totais.avisos > 0
                              ? 'aviso'
                              : 'ok'
                    })`,
                }}
            >
                {totais.falhas > 0 ? (
                    <CircleAlert
                        size={22}
                        aria-hidden
                        style={{ color: 'var(--sm-perigo)' }}
                    />
                ) : totais.avisos > 0 ? (
                    <TriangleAlert
                        size={22}
                        aria-hidden
                        style={{ color: 'var(--sm-aviso)' }}
                    />
                ) : (
                    <CircleCheck
                        size={22}
                        aria-hidden
                        style={{ color: 'var(--sm-ok)' }}
                    />
                )}

                <div>
                    {/* "Sistema em operação", e não "Sistema operacional": em
                        português essa expressão é primeiro o SO da máquina, e num
                        painel de infraestrutura a leitura errada é provável. */}
                    <p className="card-titulo">
                        {totais.falhas > 0
                            ? `${contar(totais.falhas, 'verificação', 'verificações')} acusando problema`
                            : totais.avisos > 0
                              ? `Sistema em operação, com ${contar(totais.avisos, 'ponto de atenção', 'pontos de atenção')}`
                              : 'Sistema em operação'}
                    </p>
                    <p className="card-sub">
                        {contar(totais.total, 'verificação', 'verificações')} ·
                        conferido em {carimbo}
                    </p>
                </div>
            </div>

            <div style={{ display: 'grid', gap: 16 }}>
                {modulos.map((modulo) => {
                    const checks = [...modulo.checks]
                        .map(resolvido)
                        .sort(
                            (a, b) =>
                                ESTADO[a.status].peso - ESTADO[b.status].peso,
                        );

                    const pendencias = checks.filter(
                        (c) => c.status !== 'ok',
                    ).length;

                    // Card com pendência ABRE sozinho; o saudável fica fechado
                    // numa linha só. Quem quiser conferir o verde clica.
                    const aberto =
                        fechados[modulo.modulo] === undefined
                            ? pendencias > 0
                            : !fechados[modulo.modulo];

                    return (
                        <section className="card-premium" key={modulo.modulo}>
                            <button
                                type="button"
                                onClick={() =>
                                    setFechados((atual) => ({
                                        ...atual,
                                        [modulo.modulo]: aberto,
                                    }))
                                }
                                aria-expanded={aberto}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    width: '100%',
                                    background: 'none',
                                    border: 'none',
                                    padding: 0,
                                    cursor: 'pointer',
                                    textAlign: 'left',
                                    color: 'inherit',
                                }}
                            >
                                {aberto ? (
                                    <ChevronDown size={17} aria-hidden />
                                ) : (
                                    <ChevronRight size={17} aria-hidden />
                                )}

                                <span
                                    className="card-titulo"
                                    style={{ margin: 0 }}
                                >
                                    {modulo.modulo}
                                </span>

                                <span
                                    className={cn(
                                        'selo',
                                        pendencias > 0
                                            ? ESTADO[checks[0].status].selo
                                            : 'selo-ok',
                                    )}
                                    style={{ marginLeft: 'auto' }}
                                >
                                    {pendencias > 0
                                        ? `${pendencias} de ${checks.length} com pendência`
                                        : `${checks.length}/${checks.length} ✓`}
                                </span>
                            </button>

                            {aberto && (
                                <ul
                                    style={{
                                        listStyle: 'none',
                                        margin: '16px 0 0',
                                        padding: 0,
                                        display: 'grid',
                                        gap: 14,
                                    }}
                                >
                                    {checks.map((check) => {
                                        const { selo, Icone } =
                                            ESTADO[check.status];

                                        return (
                                            <li
                                                key={check.id}
                                                style={{
                                                    display: 'flex',
                                                    gap: 12,
                                                    paddingTop: 14,
                                                    borderTop:
                                                        '1px solid var(--sm-borda)',
                                                }}
                                            >
                                                <span
                                                    className={cn('selo', selo)}
                                                    style={{
                                                        alignSelf: 'flex-start',
                                                    }}
                                                >
                                                    <Icone
                                                        size={14}
                                                        aria-hidden
                                                    />
                                                    {check.status === 'ok'
                                                        ? 'OK'
                                                        : check.status ===
                                                            'aviso'
                                                          ? 'Atenção'
                                                          : 'Parado'}
                                                </span>

                                                <div style={{ flex: 1 }}>
                                                    <p
                                                        style={{
                                                            fontWeight: 600,
                                                            fontSize: 14.5,
                                                        }}
                                                    >
                                                        {check.titulo}
                                                        {/* Um selo, não um
                                                            parêntese grudado no
                                                            título: "gravável(tem
                                                            teste real)" lia como
                                                            parte do nome da
                                                            verificação — e "teste
                                                            real" é vocabulário de
                                                            dentro de casa. */}
                                                        {check.profundo && (
                                                            <span
                                                                className="selo selo-neutro"
                                                                title="Além da conferência rápida, esta verificação tem uma prova a fundo — que escreve em disco ou fala com um serviço externo — e roda pelo botão “Testar a fundo”."
                                                                style={{
                                                                    marginLeft: 8,
                                                                    fontWeight: 600,
                                                                }}
                                                            >
                                                                verificação
                                                                profunda
                                                            </span>
                                                        )}
                                                    </p>

                                                    <p
                                                        className="card-sub"
                                                        style={{
                                                            marginTop: 4,
                                                        }}
                                                    >
                                                        {check.detalhe}
                                                    </p>

                                                    {check.status !== 'ok' &&
                                                        (check.acao_url ? (
                                                            <a
                                                                className="btn btn-secondary btn-sm"
                                                                href={
                                                                    check.acao_url
                                                                }
                                                                style={{
                                                                    marginTop: 10,
                                                                }}
                                                            >
                                                                <ExternalLink
                                                                    size={14}
                                                                    aria-hidden
                                                                />{' '}
                                                                {
                                                                    check.acao_rotulo
                                                                }
                                                            </a>
                                                        ) : (
                                                            <p
                                                                className="form-ajuda"
                                                                style={{
                                                                    marginTop: 10,
                                                                }}
                                                            >
                                                                <Info
                                                                    size={14}
                                                                    aria-hidden
                                                                />{' '}
                                                                {
                                                                    check.instrucao
                                                                }
                                                            </p>
                                                        ))}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </section>
                    );
                })}
            </div>

            {testando && (
                <p className="card-sub" style={{ marginTop: 14 }}>
                    <Spinner tamanho={14} /> Os testes reais tocam disco e rede
                    — isso pode levar alguns segundos.
                </p>
            )}
        </>
    );
}

MonitoramentoDeParametrizacoes.layout = {
    breadcrumbs: [
        {
            title: 'Monitoramento',
            href: index(),
        },
    ],
};

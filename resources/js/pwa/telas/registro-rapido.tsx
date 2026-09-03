import { useEffect, useMemo, useRef, useState } from 'react';

import { irPara, useApp } from '../app';
import { Interruptor, Selo, Topo, classes } from '../componentes';
import {
    DESFECHOS_EXPLICADOS,
    DOCUMENTO_DO_DESFECHO,
    LOCAL_APOS_O_DESFECHO,
    ORIGENS,
    SITUACOES_EXPLICADAS,
    TOM_DA_SITUACAO,
    acharDemanda,
    demandasAVistoriar,
    desfechosOferecidos,
    podeRegistrarRetorno,
    prazoDoRetornoEmPalavras,
    prazoEmPalavras,
    tomDoPrazo,
    type Demanda,
    type Desfecho,
} from '../dados-demandas';
import {
    AMBULANTES,
    CENTRO_SALVADOR,
    OCORRENCIAS,
    PRAZOS_RETORNO,
    REGIOES,
    dataBrDaqui,
    nomeRegiao,
} from '../dados-prototipo';
import { Icone } from '../icones';

/* ============================================================================
   O CORAÇÃO do aplicativo — o registro rápido.
   ----------------------------------------------------------------------------
   A fiscalização de ambulante é, antes de tudo, EDUCATIVA: o fiscal chega, pede
   para desarmar a barraca, o ambulante sai. Não há identificação, não há
   documento, muitas vezes não há nem nome. O que existe é: onde foi, o que
   aconteceu, uma foto e se ficou regular ou não.

   Por isso esta tela não pede nada obrigatório além da DECISÃO. Coordenada,
   hora e região já vêm capturadas; foto, relato, ocorrência e vínculo com um
   ambulante conhecido são todos opcionais. Emitir o documento oficial —
   Notificação Preliminar ou Auto de Apreensão — mora noutras telas, e só quem
   precisa chega lá.

   O QUE MUDOU COM O CENÁRIO NOVO: o registro passou a ter ORIGEM. Ele pode
   nascer AVULSO (o fiscal viu andando, como sempre) ou DIRIGIDO — encaminhado à
   equipe pelo administrativo, com número de processo e prazo. No dirigido, o
   passo 1 deixa de ser "onde você está" e passa a ser "o que foi encaminhado":
   o endereço vem do processo, e o vínculo fica à vista da abertura até o
   documento, porque é ele que vai para o campo REFERÊNCIA do papel.
   ============================================================================ */

type Foto = { id: string; url: string | null };

const distancia = (aLat: number, aLng: number, bLat: number, bLng: number): number =>
    Math.hypot(aLat - bLat, aLng - bLng);

/** Sem serviço de endereços no protótipo: a região mais próxima é o palpite. */
const regiaoMaisProxima = (lat: number, lng: number): string =>
    REGIOES.reduce((perto, r) =>
        distancia(lat, lng, r.lat, r.lng) < distancia(lat, lng, perto.lat, perto.lng) ? r : perto,
    ).id;

const ENDERECOS_APROXIMADOS: Record<string, string> = {
    'costa-azul': 'Av. Otávio Mangabeira, orla do Jardim de Alah',
    'jardim-armacao': 'Av. Otávio Mangabeira, altura do Centro de Convenções',
    stiep: 'Rua Ewerton Visco, altura do canteiro central',
    'boca-do-rio': 'Av. Otávio Mangabeira, acesso à Praia da Boca do Rio',
    imbui: 'Rua Ilhéus, entorno da feira',
    pituacu: 'Av. Prof. Pinto de Aguiar, entrada do Parque de Pituaçu',
    patamares: 'Alameda Praia de Patamares, acesso à areia',
    piata: 'Av. Otávio Mangabeira, passarela de Piatã',
    itapua: 'Rua da Música, próximo ao Farol de Itapuã',
    'stella-maris': 'Praia de Stella Maris, frente ao estacionamento',
    mussurunga: 'Av. Luís Viana Filho, ponto de ônibus',
};

export function TelaRegistroRapido({ alvo }: { alvo: string | null }) {
    const { registrar } = useApp();

    /* O mesmo endereço serve os dois caminhos, e o prefixo diz qual é: `amb-07`
       vem de um pino do mapa, `den-0029` vem da fila da equipe (o id da demanda
       é derivado do protocolo `DEN-NNNN`, e é por isso que o prefixo é `den-`). */
    const doMapa = alvo?.startsWith('amb-') ? (AMBULANTES.find((a) => a.id === alvo) ?? null) : null;
    const daFila = alvo?.startsWith('den-') ? acharDemanda(alvo) : null;

    const [demanda, setDemanda] = useState<Demanda | null>(daFila);
    const [escolhendoDemanda, setEscolhendoDemanda] = useState(false);

    const centroDaDemanda = demanda ? (REGIOES.find((r) => r.id === demanda.regiao) ?? null) : null;

    const [posicao, setPosicao] = useState<{ lat: number; lng: number; precisao: number } | null>(
        doMapa ? { lat: doMapa.lat, lng: doMapa.lng, precisao: 12 } : null,
    );
    const [posicaoReal, setPosicaoReal] = useState(false);
    const [fotos, setFotos] = useState<Foto[]>([]);
    const [marcadas, setMarcadas] = useState<string[]>([]);
    const [relato, setRelato] = useState('');
    const [ditando, setDitando] = useState(false);
    const [vinculo, setVinculo] = useState<string | null>(
        doMapa ? doMapa.apelido : (daFila?.sgci?.nome ?? null),
    );
    const [buscando, setBuscando] = useState(false);
    const [busca, setBusca] = useState('');
    const [desfecho, setDesfecho] = useState<Desfecho | null>(null);
    const [retorno, setRetorno] = useState(false);
    const [prazo, setPrazo] = useState(PRAZOS_RETORNO[1]);
    const seletor = useRef<HTMLInputElement>(null);

    const dirigida = demanda !== null;

    /**
     * Duas telas na mesma: vistoria e RETORNO.
     *
     * Quando a denúncia está em `Aguardando regularização`, o que o fiscal vai
     * fazer no ponto não é uma vistoria nova — é conferir se o notificado
     * cumpriu o prazo. Muda o título, muda o que o passo 1 mostra (o documento e
     * o prazo dele) e mudam os desfechos oferecidos. O resto — foto, coordenada,
     * relato — é igual, e é justamente do que o retorno precisa.
     */
    const emRetorno = demanda !== null && podeRegistrarRetorno(demanda);
    const opcoesDeDesfecho = desfechosOferecidos(demanda);
    const documentoAcombinar = desfecho ? DOCUMENTO_DO_DESFECHO[desfecho] : null;

    /**
     * A coordenada é do APARELHO, mesmo na dirigida.
     *
     * O processo diz o endereço; quem diz onde o fiscal estava é o GPS — o
     * próprio manual do cliente pede isso ("seria ideal que pegasse pelo GPS")
     * justamente porque endereço de processo erra e coordenada não. Só quem
     * chegou por um pino do mapa dispensa a captura: ali a coordenada já é a do
     * ponto que o fiscal tocou.
     */
    useEffect(() => {
        if (doMapa) {
            return;
        }

        /* Sem GPS liberado, o palpite é o centro da região da demanda — e não o
           Farol da Barra, que jogaria o registro num bairro alheio. */
        const reserva = { ...(centroDaDemanda ?? CENTRO_SALVADOR), precisao: 45 };

        if (!navigator.geolocation) {
            setPosicao(reserva);

            return;
        }

        navigator.geolocation.getCurrentPosition(
            (p) => {
                setPosicao({
                    lat: p.coords.latitude,
                    lng: p.coords.longitude,
                    precisao: Math.round(p.coords.accuracy),
                });
                setPosicaoReal(true);
            },
            () => setPosicao(reserva),
            { enableHighAccuracy: true, timeout: 6000 },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [doMapa, demanda?.id]);

    /* O ditado é encenação: mostra ao dono ONDE o botão fica e como ele se
       comporta. A transcrição de verdade entra quando o aplicativo sair do
       protótipo. */
    useEffect(() => {
        if (!ditando) {
            return;
        }

        const frase = 'Barraca montada sobre a calçada. Ambulante orientado, desarmou e saiu do ponto.';
        const prefixo = relato.trim() ? `${relato.trimEnd()} ` : '';
        let i = 0;

        const relogio = window.setInterval(() => {
            i += 2;
            setRelato(prefixo + frase.slice(0, i));

            if (i >= frase.length) {
                window.clearInterval(relogio);
                setDitando(false);
            }
        }, 45);

        return () => window.clearInterval(relogio);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ditando]);

    const regiao = useMemo(
        () =>
            demanda?.regiao ??
            doMapa?.regiao ??
            (posicao ? regiaoMaisProxima(posicao.lat, posicao.lng) : 'boca-do-rio'),
        [demanda, doMapa, posicao],
    );

    const endereco =
        demanda?.endereco ?? doMapa?.endereco ?? ENDERECOS_APROXIMADOS[regiao] ?? 'Endereço aproximado';

    const sugestoes = useMemo(() => {
        const termo = busca.trim().toLowerCase();

        if (!termo) {
            return AMBULANTES.slice(0, 6);
        }

        return AMBULANTES.filter((a) =>
            `${a.nome} ${a.apelido} ${a.atividade}`.toLowerCase().includes(termo),
        ).slice(0, 8);
    }, [busca]);

    const alternar = (id: string) =>
        setMarcadas((atuais) =>
            atuais.includes(id) ? atuais.filter((m) => m !== id) : [...atuais, id],
        );

    const anexar = (arquivos: FileList | null) => {
        if (!arquivos) {
            return;
        }

        for (const arquivo of Array.from(arquivos)) {
            const url = URL.createObjectURL(arquivo);
            setFotos((atuais) => [...atuais, { id: `${Date.now()}-${arquivo.name}`, url }]);
        }
    };

    const concluir = () => {
        if (!desfecho) {
            return;
        }

        const id = registrar({
            desfecho,
            ocorrencias: marcadas,
            relato: relato.trim(),
            fotos: fotos.length,
            ambulante: vinculo,
            retornoBr: retorno ? dataBrDaqui(prazo.dias) : null,
            lat: posicao?.lat ?? CENTRO_SALVADOR.lat,
            lng: posicao?.lng ?? CENTRO_SALVADOR.lng,
            endereco,
            regiao,
            origem: dirigida ? 'dirigida' : 'avulsa',
            demandaId: demanda?.id ?? null,
            referencia: demanda?.protocolo ?? null,
        });

        irPara(`recibo/${id}`);
    };

    return (
        <div className="pw-tela">
            <Topo
                titulo={emRetorno ? 'Registrar retorno' : 'Registrar fiscalização'}
                subtitulo={
                    dirigida
                        ? `${emRetorno ? 'Retorno' : 'Dirigida'} · ${demanda.protocolo}`
                        : 'Só o desfecho é obrigatório'
                }
                aoVoltar={() => irPara(dirigida ? 'demandas' : 'mapa')}
            />

            <div className="pw-corpo">
                {/* 1 · Origem ---------------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">1</span>
                        <span className="pw-passo-titulo">Origem da fiscalização</span>
                        {dirigida && <span className="pw-passo-opcional">Da denúncia</span>}
                    </div>

                    {dirigida ? (
                        <VinculoDaDemanda
                            demanda={demanda}
                            aoSoltar={daFila ? null : () => setDemanda(null)}
                        />
                    ) : (
                        <>
                            <div className="pw-duas-colunas">
                                <div className="pw-card pw-card-escolhido">
                                    <p className="pw-forte" style={{ margin: 0, fontSize: 15.5 }}>
                                        <Icone nome="mapa" tamanho={16} /> Avulsa
                                    </p>
                                    <p className="pw-fraco" style={{ margin: '4px 0 0' }}>
                                        O fiscal encontrou andando a rua.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    className="pw-card pw-card-toque"
                                    onClick={() => setEscolhendoDemanda((e) => !e)}
                                >
                                    <p className="pw-forte" style={{ margin: 0, fontSize: 15.5 }}>
                                        <Icone nome="caixa-entrada" tamanho={16} /> Dirigida
                                    </p>
                                    <p className="pw-fraco" style={{ margin: '4px 0 0' }}>
                                        Vem de uma denúncia direcionada à equipe.
                                    </p>
                                </button>
                            </div>

                            {escolhendoDemanda && (
                                <div className="pw-card" style={{ marginTop: 12 }}>
                                    <p className="pw-forte" style={{ margin: '0 0 4px', fontSize: 14.5 }}>
                                        Vincular a uma denúncia da fila
                                    </p>
                                    <p className="pw-fraco" style={{ margin: '0 0 10px', fontSize: 13 }}>
                                        Escolhendo aqui, o endereço e o protocolo da denúncia vêm prontos.
                                        {/* Só o que ainda pede vistoria: oferecer uma denúncia
                                            concluída, ou uma em prazo de regularização, faria o
                                            fiscal registrar uma vistoria onde o ato devido é
                                            outro (ou nenhum). */}
                                    </p>
                                    <ul className="pw-lista-limpa">
                                        {demandasAVistoriar().map((d) => (
                                            <li key={d.id}>
                                                <button
                                                    type="button"
                                                    className="pw-btn pw-btn-fantasma"
                                                    style={{
                                                        justifyContent: 'flex-start',
                                                        marginTop: 6,
                                                        textAlign: 'left',
                                                        minHeight: 58,
                                                    }}
                                                    onClick={() => {
                                                        setDemanda(d);
                                                        setEscolhendoDemanda(false);
                                                        setVinculo(d.sgci?.nome ?? null);
                                                    }}
                                                >
                                                    <span style={{ fontSize: 19 }}>
                                                        {ORIGENS[d.origem].emoji}
                                                    </span>
                                                    <span style={{ minWidth: 0 }}>
                                                        <span
                                                            className="pw-forte"
                                                            style={{ display: 'block', fontSize: 14.5 }}
                                                        >
                                                            {d.assunto}
                                                        </span>
                                                        <span className="pw-fraco" style={{ fontSize: 12.5 }}>
                                                            {d.bairro} · {prazoEmPalavras(d.prazoDias)}
                                                        </span>
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </>
                    )}
                </section>

                {/* 2 · Onde ------------------------------------------------ */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">2</span>
                        <span className="pw-passo-titulo">
                            {dirigida ? 'Endereço da demanda' : 'Onde você está'}
                        </span>
                        <span className="pw-passo-opcional">
                            {dirigida ? 'Do processo' : 'Automático'}
                        </span>
                    </div>

                    <div className="pw-card">
                        <div className="pw-linha-espalha">
                            <div style={{ minWidth: 0 }}>
                                <p className="pw-forte" style={{ margin: 0 }}>
                                    {endereco}
                                </p>
                                <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                                    {demanda?.bairro ?? nomeRegiao(regiao)} ·{' '}
                                    {posicao
                                        ? `${posicao.lat.toFixed(5)}, ${posicao.lng.toFixed(5)}`
                                        : 'capturando coordenada…'}
                                </p>
                            </div>
                            <Selo tom={posicaoReal ? 'ok' : 'neutro'}>
                                <Icone nome="alvo" tamanho={13} />
                                {posicao ? `±${posicao.precisao} m` : '…'}
                            </Selo>
                        </div>
                        {doMapa && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                                Local do ponto de {doMapa.apelido}, aberto pelo mapa.
                            </p>
                        )}
                        {dirigida && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                                Endereço informado no processo — confirme no local antes de lavrar
                                documento.
                            </p>
                        )}
                        {!doMapa && !dirigida && !posicaoReal && posicao && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                                Coordenada simulada — o aparelho não liberou a localização.
                            </p>
                        )}
                    </div>
                </section>

                {/* 3 · Fotos ---------------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">3</span>
                        <span className="pw-passo-titulo">Fotos do local</span>
                        <span className="pw-passo-opcional">Opcional</span>
                    </div>

                    <div className="pw-fotos">
                        {fotos.map((foto) => (
                            <div key={foto.id} className="pw-foto">
                                {foto.url ? <img src={foto.url} alt="Foto do local" /> : '📷'}
                                <button
                                    type="button"
                                    className="pw-foto-remove"
                                    onClick={() => setFotos((a) => a.filter((f) => f.id !== foto.id))}
                                    aria-label="Remover foto"
                                >
                                    <Icone nome="lixeira" tamanho={14} />
                                </button>
                            </div>
                        ))}

                        <button
                            type="button"
                            className="pw-foto pw-foto-add"
                            onClick={() => seletor.current?.click()}
                        >
                            <Icone nome="camera" tamanho={28} />
                        </button>

                        <button
                            type="button"
                            className="pw-foto pw-foto-add"
                            onClick={() =>
                                setFotos((a) => [...a, { id: `simulada-${Date.now()}`, url: null }])
                            }
                            title="Foto simulada, para ver a tela cheia sem câmera"
                        >
                            <span style={{ fontSize: 13, fontWeight: 700 }}>Simular</span>
                        </button>
                    </div>

                    <input
                        ref={seletor}
                        type="file"
                        accept="image/*"
                        capture="environment"
                        multiple
                        hidden
                        onChange={(e) => anexar(e.target.files)}
                    />
                </section>

                {/* 4 · O que aconteceu ------------------------------------ */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">4</span>
                        <span className="pw-passo-titulo">O que aconteceu</span>
                        <span className="pw-passo-opcional">Um toque</span>
                    </div>

                    <div className="pw-chips">
                        {OCORRENCIAS.map((o) => (
                            <button
                                key={o.id}
                                type="button"
                                className={classes('pw-chip', marcadas.includes(o.id) && 'pw-chip-ligado')}
                                onClick={() => alternar(o.id)}
                                aria-pressed={marcadas.includes(o.id)}
                            >
                                <span>{o.emoji}</span>
                                {o.rotulo}
                            </button>
                        ))}
                    </div>

                    <div style={{ position: 'relative', marginTop: 14 }}>
                        <textarea
                            className="pw-entrada"
                            value={relato}
                            onChange={(e) => setRelato(e.target.value)}
                            placeholder="Relato livre (opcional) — escreva ou dite"
                        />
                        <button
                            type="button"
                            className="pw-btn pw-btn-fantasma pw-btn-pequeno"
                            style={{ position: 'absolute', right: 10, bottom: 14 }}
                            onClick={() => setDitando((d) => !d)}
                        >
                            <Icone nome="microfone" tamanho={16} className={ditando ? 'pw-girando' : undefined} />
                            {ditando ? 'Ouvindo…' : 'Ditar'}
                        </button>
                    </div>
                </section>

                {/* 5 · Quem ---------------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">5</span>
                        <span className="pw-passo-titulo">Ambulante</span>
                        <span className="pw-passo-opcional">
                            {demanda?.sgci ? 'Do cadastro SGCI' : 'Opcional'}
                        </span>
                    </div>

                    <div className="pw-card">
                        <div className="pw-linha-espalha">
                            <div>
                                <p className="pw-forte" style={{ margin: 0 }}>
                                    {vinculo ?? 'Não identificado'}
                                </p>
                                <p className="pw-fraco" style={{ margin: 0 }}>
                                    {demanda?.sgci
                                        ? `Ambulante · permissionário SEMOP · ${demanda.sgci.equipamento}`
                                        : vinculo
                                          ? 'Ambulante sem permissão registrada · ponto conhecido'
                                          : 'A maioria das abordagens fica assim mesmo'}
                                </p>
                            </div>
                            {vinculo ? (
                                <button
                                    type="button"
                                    className="pw-btn pw-btn-fantasma pw-btn-pequeno"
                                    onClick={() => setVinculo(null)}
                                >
                                    Desvincular
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    className="pw-btn pw-btn-fantasma pw-btn-pequeno"
                                    onClick={() => setBuscando((b) => !b)}
                                >
                                    <Icone nome="busca" tamanho={16} />
                                    Vincular
                                </button>
                            )}
                        </div>

                        {demanda?.sgci && vinculo === demanda.sgci.nome && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                                <Icone nome="prancheta" tamanho={13} /> Inscrição{' '}
                                {demanda.sgci.inscricao} · {demanda.sgci.atividade} ·{' '}
                                {demanda.sgci.damEmDia ? 'DAM quitado' : 'DAM em aberto'}
                            </p>
                        )}

                        {buscando && !vinculo && (
                            <div style={{ marginTop: 12 }}>
                                <input
                                    className="pw-entrada"
                                    value={busca}
                                    onChange={(e) => setBusca(e.target.value)}
                                    placeholder="Nome ou apelido"
                                    autoFocus
                                />
                                <ul className="pw-lista-limpa" style={{ marginTop: 8 }}>
                                    {sugestoes.map((a) => (
                                        <li key={a.id}>
                                            <button
                                                type="button"
                                                className="pw-btn pw-btn-fantasma"
                                                style={{ justifyContent: 'flex-start', marginTop: 6 }}
                                                onClick={() => {
                                                    setVinculo(a.apelido);
                                                    setBuscando(false);
                                                }}
                                            >
                                                <span style={{ fontSize: 20 }}>{a.emoji}</span>
                                                {a.apelido} · {a.atividade}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                </section>

                {/* 6 · Desfecho ------------------------------------------ */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">6</span>
                        <span className="pw-passo-titulo">
                            {emRetorno ? 'Como o retorno encontrou o ponto' : 'Como a vistoria terminou'}
                        </span>
                    </div>

                    {/* A lista é FECHADA e é a mesma que a Retaguarda lê: é o
                        desfecho que fecha o passo do trâmite da denúncia do outro
                        lado. Texto livre aqui faria o campo dizer uma coisa e a
                        Retaguarda outra — e o relatório não somaria nada. */}
                    <p className="pw-fraco" style={{ margin: '0 0 10px', fontSize: 13.5 }}>
                        {emRetorno
                            ? 'O retorno diz se o notificado cumpriu o prazo. É este desfecho que encerra a denúncia ou a devolve ao gestor da área.'
                            : 'A maioria das abordagens termina no primeiro desfecho — orientou, o ambulante desmontou, acabou.'}
                    </p>

                    <div className="pw-desfechos">
                        {opcoesDeDesfecho.map((opcao) => (
                            <button
                                key={opcao}
                                type="button"
                                className={classes(
                                    'pw-desfecho',
                                    LOCAL_APOS_O_DESFECHO[opcao] === 'regular'
                                        ? 'pw-desfecho-livre'
                                        : 'pw-desfecho-ocorrencia',
                                    desfecho === opcao && 'pw-escolhido',
                                )}
                                onClick={() => setDesfecho(opcao)}
                                aria-pressed={desfecho === opcao}
                            >
                                <span className="pw-desfecho-icone">
                                    <Icone
                                        nome={
                                            DOCUMENTO_DO_DESFECHO[opcao] === 'aa'
                                                ? 'pacote'
                                                : DOCUMENTO_DO_DESFECHO[opcao] === 'np'
                                                  ? 'documento'
                                                  : LOCAL_APOS_O_DESFECHO[opcao] === 'regular'
                                                    ? 'certo'
                                                    : 'alerta'
                                        }
                                        tamanho={22}
                                    />
                                </span>
                                <span style={{ minWidth: 0 }}>
                                    <span className="pw-desfecho-nome">{opcao}</span>
                                    <span className="pw-desfecho-explica">
                                        {DESFECHOS_EXPLICADOS[opcao]}
                                    </span>
                                </span>
                            </button>
                        ))}
                    </div>

                    {documentoAcombinar && (
                        <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                            <Icone nome="documento" tamanho={13} /> Este desfecho lavra{' '}
                            {documentoAcombinar === 'np'
                                ? 'a Notificação Preliminar'
                                : 'o Auto de Apreensão'}
                            . O documento é preenchido na tela seguinte, com número do bloco reservado
                            no aparelho.
                        </p>
                    )}
                </section>

                {/* 7 · Retorno ------------------------------------------- */}
                <section className="pw-passo">
                    <Interruptor
                        ligado={retorno}
                        aoAlternar={() => setRetorno((r) => !r)}
                        titulo="Marcar retorno a este local"
                        descricao={retorno ? `Volta prevista para ${dataBrDaqui(prazo.dias)}` : 'A equipe passa de novo'}
                    />

                    {retorno && (
                        <div className="pw-periodo" style={{ marginTop: 10 }}>
                            {PRAZOS_RETORNO.map((p) => (
                                <button
                                    key={p.id}
                                    type="button"
                                    className={p.id === prazo.id ? 'pw-periodo-ativo' : undefined}
                                    onClick={() => setPrazo(p)}
                                >
                                    {p.rotulo}
                                </button>
                            ))}
                        </div>
                    )}
                </section>

                <button
                    type="button"
                    className="pw-btn pw-btn-acao"
                    style={{ minHeight: 60, fontSize: 18, opacity: desfecho ? 1 : 0.5 }}
                    onClick={concluir}
                    disabled={!desfecho}
                >
                    {desfecho
                        ? emRetorno
                            ? 'Concluir retorno'
                            : 'Concluir registro'
                        : 'Escolha o desfecho'}
                    <Icone nome="seta" tamanho={20} />
                </button>
            </div>
        </div>
    );
}

/** O crachá da denúncia: fica no alto do registro e vai até o documento. */
function VinculoDaDemanda({
    demanda,
    aoSoltar,
}: {
    demanda: Demanda;
    /** `null` quando o fiscal chegou pela própria demanda — aí não há o que soltar. */
    aoSoltar: (() => void) | null;
}) {
    const origem = ORIGENS[demanda.origem];
    const documento = demanda.documento;

    return (
        <div className="pw-card pw-card-vinculo">
            <div className="pw-linha-espalha" style={{ gap: 10, marginBottom: 8 }}>
                <span className="pw-selo pw-selo-origem">
                    <span>{origem.emoji}</span>
                    {origem.curto}
                </span>
                <Selo tom={tomDoPrazo(demanda.prazoDias)}>
                    <Icone nome="relogio" tamanho={13} />
                    {prazoEmPalavras(demanda.prazoDias)}
                </Selo>
            </div>

            <p className="pw-forte" style={{ margin: 0, fontSize: 15.5, lineHeight: 1.3 }}>
                {demanda.assunto}
            </p>
            <p className="pw-fraco" style={{ margin: '4px 0 0', fontSize: 12.5 }}>
                Referência {demanda.protocolo} · prazo {demanda.prazoBr}
            </p>

            {/* A situação diz de quem é a bola. Sem ela o crachá da denúncia
                ficaria igual em duas circunstâncias opostas: a que espera a
                primeira vistoria e a que espera o retorno de um prazo. */}
            <p style={{ margin: '10px 0 0' }}>
                <Selo tom={TOM_DA_SITUACAO[demanda.situacao]}>{demanda.situacao}</Selo>
            </p>
            <p className="pw-fraco" style={{ margin: '6px 0 0', fontSize: 13 }}>
                {SITUACOES_EXPLICADAS[demanda.situacao]}
            </p>

            {documento && (
                <p className="pw-fraco" style={{ margin: '8px 0 0', fontSize: 13 }}>
                    <Icone nome="documento" tamanho={13} /> {documento.rotulo}
                    {documento.prazoRotulo ? ` · prazo de ${documento.prazoRotulo}` : ''}
                    {documento.venceBr && documento.venceEmDias !== null
                        ? ` · ${prazoDoRetornoEmPalavras(documento.venceEmDias)} (${documento.venceBr})`
                        : ''}
                </p>
            )}

            <p style={{ margin: '10px 0 0', fontSize: 14, color: 'var(--pw-texto-corpo)' }}>
                {demanda.detalhe}
            </p>

            <div className="pw-linha" style={{ gap: 8, marginTop: 12, flexWrap: 'wrap' }}>
                <button
                    type="button"
                    className="pw-btn pw-btn-fantasma pw-btn-pequeno"
                    onClick={() => irPara(`demanda/${demanda.id}`)}
                >
                    Abrir a denúncia
                </button>
                {aoSoltar && (
                    <button
                        type="button"
                        className="pw-btn pw-btn-fantasma pw-btn-pequeno"
                        onClick={aoSoltar}
                    >
                        Voltar a avulsa
                    </button>
                )}
            </div>
        </div>
    );
}

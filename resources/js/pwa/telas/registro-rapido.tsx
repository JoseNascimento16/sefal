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
    OCORRENCIAS,
    OPERACOES,
    PRAZOS_RETORNO,
    dataBrDaqui,
    nomeRegiao,
    BAIRROS,
    bairroMaisProximo,
} from '../dados-prototipo';
import { useDitado } from '../ditado';
import { MOTIVOS_SEM_GPS, useGps } from '../gps';
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
   equipe pelo Coordenador, com número de processo e prazo. No dirigido, o
   passo 1 deixa de ser "onde você está" e passa a ser "o que foi encaminhado":
   o endereço vem do processo, e o vínculo fica à vista da abertura até o
   documento, porque é ele que vai para o campo REFERÊNCIA do papel.
   ============================================================================ */

type Foto = { id: string; url: string | null; arquivo: Blob | null };

/**
 * Sem serviço de endereços: o bairro do catálogo mais perto do GPS é o palpite.
 * Sem catálogo carregado não há palpite — o fiscal escreve o bairro. Chutar a
 * partir de uma lista fixa daria um bairro errado longe dela.
 */
const regiaoMaisProxima = (lat: number, lng: number): string => bairroMaisProximo(lat, lng)?.id ?? '';

export function TelaRegistroRapido({ alvo }: { alvo: string | null }) {
    const { registrar } = useApp();

    /* O mesmo endereço serve os dois caminhos, e o prefixo diz qual é: `amb-07`
       vem de um pino do mapa, `den-0029` vem da fila da equipe (o id da demanda
       é derivado do protocolo `DEN-NNNN`, e é por isso que o prefixo é `den-`), e
       `fsc-12` é a ronda que voltou à fila (retorno da notificação, ou a volta
       que o líder determinou). */
    const doMapa = alvo?.startsWith('amb-') ? (AMBULANTES.find((a) => a.id === alvo) ?? null) : null;
    const daFila = alvo?.startsWith('den-') || alvo?.startsWith('fsc-') ? acharDemanda(alvo) : null;
    /* `op-7` vem da tela da operação: a avulsa feita ali fica ligada a ela. */
    const daOperacao = alvo?.startsWith('op-') ? (OPERACOES.find((o) => o.id === alvo) ?? null) : null;

    const [demanda, setDemanda] = useState<Demanda | null>(daFila);
    const [escolhendoDemanda, setEscolhendoDemanda] = useState(false);

    /* A coordenada é SEMPRE a medida pelo aparelho — também na dirigida e no
       registro aberto por um pino do mapa: o processo diz o endereço e o pino
       diz onde o ambulante costuma estar, mas onde o fiscal ESTAVA quem diz é o
       GPS. Sem GPS, o registro vai sem coordenada e com o motivo (`gps.ts`). */
    const gps = useGps();
    const posicao = gps.leitura;
    const [bairroEscolhido, setBairroEscolhido] = useState('');
    const [enderecoDigitado, setEnderecoDigitado] = useState('');
    const [fotos, setFotos] = useState<Foto[]>([]);
    const [marcadas, setMarcadas] = useState<string[]>([]);
    const [relato, setRelato] = useState('');
    const [vinculo, setVinculo] = useState<string | null>(
        doMapa ? doMapa.apelido : (daFila?.sgci?.nome ?? null),
    );
    const [buscando, setBuscando] = useState(false);
    const [busca, setBusca] = useState('');
    const [desfecho, setDesfecho] = useState<Desfecho | null>(null);
    const [retorno, setRetorno] = useState(false);
    const [prazo, setPrazo] = useState(PRAZOS_RETORNO[1]);
    const [despachando, setDespachando] = useState(false);
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

    /* O ditado é de verdade (`ditado.ts`): cada frase reconhecida entra no fim
       do relato, sem apagar o que o fiscal já escreveu. */
    const ditado = useDitado((frase) =>
        setRelato((atual) => (atual.trim() ? `${atual.trimEnd()} ${frase}` : frase)),
    );

    /* O bairro: o da demanda, o do pino, o que o fiscal escolheu ou — no
       registro avulso — o do catálogo mais perto do GPS. Sem nenhum deles, fica
       vazio e o fiscal escolhe: nada de bairro chutado. */
    const regiao = useMemo(
        () =>
            demanda?.regiao ??
            doMapa?.regiao ??
            (bairroEscolhido || (posicao ? regiaoMaisProxima(posicao.lat, posicao.lng) : '')),
        [demanda, doMapa, bairroEscolhido, posicao],
    );

    /* O endereço do avulso é o que o fiscal escreve; sem texto, vai a
       referência ao bairro — nunca uma rua inventada. */
    const endereco =
        demanda?.endereco ??
        doMapa?.endereco ??
        (enderecoDigitado.trim() || (regiao ? `Proximidades de ${nomeRegiao(regiao)}` : ''));

    const aguardandoGps = gps.estado === 'medindo';
    const faltaBairro = !dirigida && !doMapa && regiao === '';

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
            setFotos((atuais) => [...atuais, { id: `${Date.now()}-${arquivo.name}`, url, arquivo }]);
        }
    };

    /**
     * O despacho: grava o registro e leva à tela de conclusão.
     *
     * A guarda de duplo clique não é enfeite: quem toca de pé, com luva ou com a
     * tela sob sol, toca duas vezes achando que não pegou — e o segundo toque
     * gravaria uma SEGUNDA fiscalização do mesmo ponto, que o Chefe de Setor
     * receberia como se fossem duas idas ao local.
     */
    const despacharRegistro = () => {
        if (!desfecho || despachando || aguardandoGps || faltaBairro) {
            return;
        }

        setDespachando(true);

        const id = registrar({
            desfecho,
            ocorrencias: marcadas,
            relato: relato.trim(),
            fotos: fotos.filter((f) => f.arquivo !== null).length,
            arquivos: fotos.flatMap((f) => (f.arquivo ? [f.arquivo] : [])),
            precisaoM: posicao?.precisao ?? null,
            semGps: gps.estado === 'falhou' ? gps.motivo : null,
            ambulante: vinculo,
            /* O id do cadastro só vale enquanto o vínculo é o do pino de onde o
               registro saiu; trocar o nome desfaz a identificação. */
            ambulanteId: doMapa && vinculo === doMapa.apelido ? (doMapa.servidorId ?? null) : null,
            retornoBr: retorno ? dataBrDaqui(prazo.dias) : null,
            lat: posicao?.lat ?? null,
            lng: posicao?.lng ?? null,
            endereco,
            regiao,
            origem: dirigida ? 'dirigida' : 'avulsa',
            demandaId: demanda?.id ?? null,
            referencia: demanda?.protocolo ?? null,
            operacaoId: !dirigida && daOperacao ? Number(daOperacao.id.slice(3)) : null,
            operacaoNome: !dirigida && daOperacao ? daOperacao.nome : null,
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
                                        {daOperacao ? `Na operação ${daOperacao.nome}.` : 'O fiscal encontrou andando a rua.'}
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
                            {dirigida ? 'Do processo' : 'Pelo GPS'}
                        </span>
                    </div>

                    <div className="pw-card">
                        <div className="pw-linha-espalha">
                            <div style={{ minWidth: 0 }}>
                                {(dirigida || doMapa) && (
                                    <p className="pw-forte" style={{ margin: 0 }}>
                                        {endereco}
                                    </p>
                                )}
                                <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                                    {regiao ? `${demanda?.bairro ?? nomeRegiao(regiao)} · ` : ''}
                                    {posicao
                                        ? `${posicao.lat.toFixed(5)}, ${posicao.lng.toFixed(5)}`
                                        : gps.estado === 'falhou'
                                          ? 'sem coordenada'
                                          : 'medindo a posição…'}
                                </p>
                            </div>
                            {/* Tocar no selo mede de novo: a primeira leitura
                                costuma vir com margem grande, e quem acabou de
                                liberar a localização precisa de uma segunda tentativa. */}
                            <button
                                type="button"
                                style={{ background: 'none', border: 0, padding: 0, cursor: 'pointer' }}
                                onClick={gps.medirDeNovo}
                                aria-label="Medir a posição de novo"
                                disabled={aguardandoGps}
                            >
                                <Selo tom={posicao ? (posicao.precisao <= 30 ? 'ok' : 'alerta') : 'neutro'}>
                                    <Icone
                                        nome={aguardandoGps ? 'atualizar' : 'alvo'}
                                        tamanho={13}
                                        className={aguardandoGps ? 'pw-girando' : undefined}
                                    />
                                    {posicao ? `±${posicao.precisao} m` : aguardandoGps ? 'GPS' : 'Sem GPS'}
                                </Selo>
                            </button>
                        </div>

                        {gps.estado === 'falhou' && (
                            <div className="pw-aviso pw-aviso-alerta" role="status" style={{ flexDirection: 'column', marginTop: 12 }}>
                                <p style={{ margin: 0 }}>
                                    <strong>Sem GPS:</strong> {MOTIVOS_SEM_GPS[gps.motivo]}.
                                    {gps.motivo === 'negado'
                                        ? ' Libere a localização para este site nas permissões do Chrome e toque em Medir de novo.'
                                        : ' Vá para céu aberto e toque em Medir de novo.'}
                                </p>
                                <p style={{ margin: '6px 0 0' }}>
                                    Se não der, o registro segue sem coordenada e com essa marca — o líder vê o
                                    motivo. Nenhum ponto é inventado.
                                </p>
                                <button type="button" className="pw-btn pw-btn-contorno pw-btn-pequeno" style={{ marginTop: 8, alignSelf: 'flex-start' }} onClick={gps.medirDeNovo}>
                                    <Icone nome="alvo" tamanho={16} /> Medir de novo
                                </button>
                            </div>
                        )}

                        {posicao && posicao.precisao > 30 && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                                Margem grande para um ponto de rua. Se puder, espere alguns segundos ou toque no selo
                                para medir de novo.
                            </p>
                        )}

                        {/* No avulso não há processo nem pino que diga o lugar: o
                            fiscal escreve o endereço, e o bairro vem do GPS (ou ele
                            escolhe, quando o GPS falhou). */}
                        {!dirigida && !doMapa && (
                            <div style={{ marginTop: 12 }}>
                                <label className="pw-campo">
                                    <span>Endereço ou ponto de referência</span>
                                    <input
                                        className="pw-entrada"
                                        value={enderecoDigitado}
                                        onChange={(e) => setEnderecoDigitado(e.target.value)}
                                        maxLength={200}
                                        placeholder="Ex.: Av. Sete de Setembro, em frente ao nº 120"
                                    />
                                </label>
                                <label className="pw-campo" style={{ marginBottom: 0 }}>
                                    <span>Bairro{faltaBairro ? ' — escolha para despachar' : ''}</span>
                                    <input
                                        className="pw-entrada"
                                        list="pw-bairros-do-catalogo"
                                        value={bairroEscolhido}
                                        onChange={(e) => setBairroEscolhido(e.target.value)}
                                        maxLength={120}
                                        placeholder={
                                            posicao && regiao ? `Pelo GPS: ${nomeRegiao(regiao)}` : 'Digite ou escolha o bairro'
                                        }
                                    />
                                    <datalist id="pw-bairros-do-catalogo">
                                        {BAIRROS.map((b) => (
                                            <option key={b.id} value={b.nome} />
                                        ))}
                                    </datalist>
                                </label>
                            </div>
                        )}

                        {doMapa && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                                Ponto de {doMapa.apelido}, aberto pelo mapa. A coordenada gravada é a do seu GPS.
                            </p>
                        )}
                        {dirigida && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                                Endereço informado no processo — confirme no local antes de lavrar
                                documento.
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
                            onClick={() => (ditado.ouvindo ? ditado.parar() : ditado.iniciar())}
                            aria-pressed={ditado.ouvindo}
                        >
                            <Icone nome="microfone" tamanho={16} className={ditado.ouvindo ? 'pw-girando' : undefined} />
                            {ditado.ouvindo ? 'Parar' : 'Ditar'}
                        </button>
                    </div>
                    {ditado.ouvindo && (
                        <p className="pw-fraco" role="status" style={{ margin: '6px 0 0', fontSize: 13 }}>
                            Ouvindo…{ditado.parcial ? ` “${ditado.parcial}”` : ' fale o relato.'}
                        </p>
                    )}
                    {ditado.erro && (
                        <p className="pw-fraco" role="alert" style={{ margin: '6px 0 0', fontSize: 13, color: 'var(--pw-perigo)' }}>
                            {ditado.erro}
                        </p>
                    )}
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
                            ? 'O retorno diz se o notificado cumpriu o prazo. É este desfecho que encerra a denúncia ou a devolve ao Chefe de Setor.'
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
                            . O documento é preenchido depois do despacho, com número do bloco
                            reservado no aparelho — e o registro só conclui com ele lavrado.
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

                {/* DESPACHAR, e não "concluir": o registro não morre aqui — ele
                    segue para o líder da equipe, que o lê, e a
                    tela seguinte é onde o fiscal escreve o que recomenda. Chamar
                    este botão de "concluir" prometia um fim que não é o dele. */}
                <button
                    type="button"
                    className="pw-btn pw-btn-acao"
                    style={{
                        minHeight: 60,
                        fontSize: 18,
                        opacity: desfecho && !aguardandoGps && !faltaBairro ? 1 : 0.5,
                    }}
                    onClick={despacharRegistro}
                    disabled={!desfecho || despachando || aguardandoGps || faltaBairro}
                >
                    {!desfecho
                        ? 'Escolha o desfecho'
                        : aguardandoGps
                          ? 'Medindo a posição…'
                          : faltaBairro
                            ? 'Informe o bairro'
                            : despachando
                              ? 'Despachando…'
                              : emRetorno
                                ? 'Despachar retorno'
                                : 'Despachar registro'}
                    <Icone
                        nome={despachando ? 'atualizar' : 'seta'}
                        tamanho={20}
                        className={despachando ? 'pw-girando' : undefined}
                    />
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

import { useEffect, useMemo, useRef, useState } from 'react';

import { irPara, useApp } from '../app';
import { Interruptor, Selo, Topo, classes } from '../componentes';
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
   ambulante conhecido são todos opcionais. Identificar a pessoa e emitir
   documento é a ESCALADA — mora noutra tela, e só quem precisa chega lá.
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
    barra: 'Av. Oceânica, altura do Farol da Barra',
    'rio-vermelho': 'Largo da Mariquita, canteiro central',
    pelourinho: 'Largo do Pelourinho, escadaria',
    itapua: 'Rua da Música, próximo ao Farol de Itapuã',
    'campo-grande': 'Praça Dois de Julho, lado do coreto',
    'boca-do-rio': 'Av. Otávio Mangabeira, orla',
    comercio: 'Praça Cairu, frente ao Mercado Modelo',
    ondina: 'Av. Adhemar de Barros, mirante de Ondina',
};

export function TelaRegistroRapido({ ambulanteId }: { ambulanteId: string | null }) {
    const { registrar } = useApp();
    const escolhido = ambulanteId ? (AMBULANTES.find((a) => a.id === ambulanteId) ?? null) : null;

    /* Vindo de um ponto conhecido (o pino do mapa), o registro já nasce COM o
       lugar: coordenada, endereço e ambulante do ponto. É o caso do retorno
       vencido — o fiscal tocou no pino justamente porque vai lá. */
    const [posicao, setPosicao] = useState<{ lat: number; lng: number; precisao: number } | null>(
        escolhido ? { lat: escolhido.lat, lng: escolhido.lng, precisao: 12 } : null,
    );
    const [posicaoReal, setPosicaoReal] = useState(false);
    const [fotos, setFotos] = useState<Foto[]>([]);
    const [marcadas, setMarcadas] = useState<string[]>([]);
    const [relato, setRelato] = useState('');
    const [ditando, setDitando] = useState(false);
    const [vinculo, setVinculo] = useState<string | null>(escolhido ? escolhido.apelido : null);
    const [buscando, setBuscando] = useState(false);
    const [busca, setBusca] = useState('');
    const [decisao, setDecisao] = useState<'regular' | 'irregular' | null>(null);
    const [retorno, setRetorno] = useState(false);
    const [prazo, setPrazo] = useState(PRAZOS_RETORNO[1]);
    const seletor = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (escolhido) {
            return;
        }

        if (!navigator.geolocation) {
            setPosicao({ ...CENTRO_SALVADOR, precisao: 45 });

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
            () => setPosicao({ ...CENTRO_SALVADOR, precisao: 45 }),
            { enableHighAccuracy: true, timeout: 6000 },
        );
    }, [escolhido]);

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
        () => escolhido?.regiao ?? (posicao ? regiaoMaisProxima(posicao.lat, posicao.lng) : 'barra'),
        [escolhido, posicao],
    );

    const endereco = escolhido?.endereco ?? ENDERECOS_APROXIMADOS[regiao] ?? 'Endereço aproximado';

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
        if (!decisao) {
            return;
        }

        const id = registrar({
            status: decisao,
            ocorrencias: marcadas,
            relato: relato.trim(),
            fotos: fotos.length,
            ambulante: vinculo,
            retornoBr: retorno ? dataBrDaqui(prazo.dias) : null,
            lat: posicao?.lat ?? CENTRO_SALVADOR.lat,
            lng: posicao?.lng ?? CENTRO_SALVADOR.lng,
            endereco,
            regiao,
        });

        irPara(`recibo/${id}`);
    };

    return (
        <div className="pw-tela">
            <Topo
                titulo="Registrar fiscalização"
                subtitulo="Só a decisão é obrigatória"
                aoVoltar={() => irPara('mapa')}
            />

            <div className="pw-corpo">
                {/* 1 · Onde ------------------------------------------------ */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">1</span>
                        <span className="pw-passo-titulo">Onde você está</span>
                        <span className="pw-passo-opcional">Automático</span>
                    </div>

                    <div className="pw-card">
                        <div className="pw-linha-espalha">
                            <div style={{ minWidth: 0 }}>
                                <p className="pw-forte" style={{ margin: 0 }}>
                                    {endereco}
                                </p>
                                <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                                    {nomeRegiao(regiao)} ·{' '}
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
                        {escolhido && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                                Local do ponto de {escolhido.apelido}, aberto pelo mapa.
                            </p>
                        )}
                        {!escolhido && !posicaoReal && posicao && (
                            <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                                Coordenada simulada — o aparelho não liberou a localização.
                            </p>
                        )}
                    </div>
                </section>

                {/* 2 · Fotos ---------------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">2</span>
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

                {/* 3 · O que aconteceu ------------------------------------ */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">3</span>
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

                {/* 4 · Quem ----------------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">4</span>
                        <span className="pw-passo-titulo">Ambulante</span>
                        <span className="pw-passo-opcional">Opcional</span>
                    </div>

                    <div className="pw-card">
                        <div className="pw-linha-espalha">
                            <div>
                                <p className="pw-forte" style={{ margin: 0 }}>
                                    {vinculo ?? 'Não identificado'}
                                </p>
                                <p className="pw-fraco" style={{ margin: 0 }}>
                                    {vinculo
                                        ? 'Vinculado a um ponto conhecido'
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

                {/* 5 · Decisão -------------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">5</span>
                        <span className="pw-passo-titulo">Como ficou o local</span>
                    </div>

                    <div className="pw-decisao">
                        <button
                            type="button"
                            className={classes('pw-regular', decisao === 'regular' && 'pw-escolhido')}
                            onClick={() => setDecisao('regular')}
                        >
                            <Icone nome="certo" tamanho={34} />
                            TUDO REGULAR
                            <span>Ponto liberado</span>
                        </button>
                        <button
                            type="button"
                            className={classes('pw-irregular', decisao === 'irregular' && 'pw-escolhido')}
                            onClick={() => setDecisao('irregular')}
                        >
                            <Icone nome="alerta" tamanho={34} />
                            IRREGULAR
                            <span>Ocorrência registrada</span>
                        </button>
                    </div>
                </section>

                {/* 6 · Retorno -------------------------------------------- */}
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
                    style={{ minHeight: 60, fontSize: 18, opacity: decisao ? 1 : 0.5 }}
                    onClick={concluir}
                    disabled={!decisao}
                >
                    {decisao ? 'Concluir registro' : 'Escolha regular ou irregular'}
                    <Icone nome="seta" tamanho={20} />
                </button>
            </div>
        </div>
    );
}

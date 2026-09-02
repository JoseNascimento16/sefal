import { useRef, useState } from 'react';

import { irPara } from '../app';
import { Selo, Topo, Vazio, classes } from '../componentes';
import { EQUIPE, EQUIPES_SEFAL } from '../dados-demandas';
import {
    ATIVIDADES_LEVANTAMENTO,
    EXEMPLOS_LEVANTAMENTO,
    LEVANTAMENTOS_RECENTES,
    RUAS_SUGERIDAS,
    frasePluralizada,
    listaEmPortugues,
    numeroDoItem,
    type LinhaLevantamento,
} from '../dados-levantamento';
import { Icone } from '../icones';

/* ============================================================================
   LEVANTAMENTO DE AMBULANTES — o censo da rua, que hoje é feito em planilha.
   ----------------------------------------------------------------------------
   Capacidade NOVA no aplicativo, antiga no setor: o cliente entregou um
   levantamento pronto da Rua Barão de Mauá, no Arenoso, com onze ambulantes
   numerados, CPF, telefone, referência do ponto, atividade e uma FOTO do
   equipamento de cada um — assinado por duas equipes e dois encarregados.

   Duas coisas o papel ensina e a tela obedece:

   • É trabalho em CADEIA, não em formulário. O fiscal caminha e vai somando:
     001, 002, 003. Então o botão que fecha uma linha volta o foco para o campo
     do nome, e a linha nova já nasce numerada — quem digita de pé não pode
     precisar navegar para continuar.
   • O CABEÇALHO não é do fiscal, é da força-tarefa: rua, bairro, equipes
     participantes e encarregados. Duas equipes levantam a mesma rua, e o
     documento sai no nome das duas.

   E o levantamento é o que finalmente ALIMENTA o mapa de calor por antecipação:
   hoje o calor só sabe onde houve ocorrência; com o censo, ele passa a saber
   onde os ambulantes estão antes de qualquer ocorrência acontecer.
   ============================================================================ */

type Etapa = 'cabecalho' | 'campo';

export function TelaLevantamento() {
    const [etapa, setEtapa] = useState<Etapa>('cabecalho');
    const [rua, setRua] = useState('');
    const [bairro, setBairro] = useState('');
    const [equipes, setEquipes] = useState<string[]>([EQUIPE.codigo]);
    const [linhas, setLinhas] = useState<LinhaLevantamento[]>([]);

    const encarregados = EQUIPES_SEFAL.filter((e) => equipes.includes(e.codigo)).map(
        (e) => e.encarregado,
    );

    const podeIrACampo = rua.trim().length > 2 && bairro.trim().length > 1 && equipes.length > 0;

    if (etapa === 'cabecalho') {
        return (
            <Cabecalho
                rua={rua}
                bairro={bairro}
                equipes={equipes}
                encarregados={encarregados}
                aoRua={setRua}
                aoBairro={setBairro}
                aoEquipes={setEquipes}
                podeSeguir={podeIrACampo}
                aoSeguir={() => setEtapa('campo')}
            />
        );
    }

    return (
        <EmCampo
            rua={rua}
            bairro={bairro}
            equipes={equipes}
            encarregados={encarregados}
            linhas={linhas}
            aoLinhas={setLinhas}
            aoVoltar={() => setEtapa('cabecalho')}
        />
    );
}

/* ------------------------------- Cabeçalho ------------------------------- */

function Cabecalho({
    rua,
    bairro,
    equipes,
    encarregados,
    aoRua,
    aoBairro,
    aoEquipes,
    podeSeguir,
    aoSeguir,
}: {
    rua: string;
    bairro: string;
    equipes: string[];
    encarregados: string[];
    aoRua: (v: string) => void;
    aoBairro: (v: string) => void;
    aoEquipes: (v: string[]) => void;
    podeSeguir: boolean;
    aoSeguir: () => void;
}) {
    const alternarEquipe = (codigo: string) =>
        aoEquipes(
            equipes.includes(codigo) ? equipes.filter((c) => c !== codigo) : [...equipes, codigo],
        );

    return (
        <div className="pw-tela">
            <Topo
                titulo="Levantamento de ambulantes"
                subtitulo="Censo por rua"
                aoVoltar={() => irPara('inicio')}
            />

            <div className="pw-corpo">
                <p className="pw-leitura">
                    Cada linha levantada aqui vira um ponto que o <strong>mapa de calor</strong> passa a
                    conhecer — é assim que o mapa deixa de mostrar só onde houve ocorrência e passa a
                    mostrar onde os ambulantes estão.
                </p>

                <p className="pw-titulo-secao">A rua a levantar</p>

                <div className="pw-card">
                    <label className="pw-campo">
                        <span>Rua</span>
                        <input
                            className="pw-entrada"
                            value={rua}
                            onChange={(e) => aoRua(e.target.value)}
                            placeholder="Ex.: Rua Barão de Mauá"
                        />
                    </label>

                    <label className="pw-campo" style={{ marginBottom: 0 }}>
                        <span>Bairro</span>
                        <input
                            className="pw-entrada"
                            value={bairro}
                            onChange={(e) => aoBairro(e.target.value)}
                            placeholder="Ex.: Arenoso"
                        />
                    </label>
                </div>

                <p className="pw-fraco" style={{ margin: '12px 0 8px', fontSize: 13 }}>
                    Indicadas pela chefia:
                </p>
                <div className="pw-chips" style={{ gap: 8 }}>
                    {RUAS_SUGERIDAS.map((s) => (
                        <button
                            key={s.rua}
                            type="button"
                            className={classes('pw-chip', 'pw-chip-mini', rua === s.rua && 'pw-chip-ligado')}
                            onClick={() => {
                                aoRua(s.rua);
                                aoBairro(s.bairro);
                            }}
                        >
                            {s.rua} · {s.bairro}
                        </button>
                    ))}
                </div>

                <p className="pw-titulo-secao">Equipes participantes</p>

                <div className="pw-chips" style={{ gap: 8 }}>
                    {EQUIPES_SEFAL.map((e) => (
                        <button
                            key={e.codigo}
                            type="button"
                            className={classes('pw-chip', equipes.includes(e.codigo) && 'pw-chip-ligado')}
                            onClick={() => alternarEquipe(e.codigo)}
                            aria-pressed={equipes.includes(e.codigo)}
                        >
                            <Icone nome="equipe" tamanho={15} />
                            {e.codigo} · {e.areaNome}
                        </button>
                    ))}
                </div>

                <div className="pw-card" style={{ marginTop: 14 }}>
                    <p className="pw-forte" style={{ margin: 0, fontSize: 14.5 }}>
                        Encarregados
                    </p>
                    <p className="pw-fraco" style={{ margin: '4px 0 0' }}>
                        {encarregados.length > 0
                            ? listaEmPortugues(encarregados)
                            : 'Escolha ao menos uma equipe para o levantamento sair assinado.'}
                    </p>
                    <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 12.5 }}>
                        Os encarregados vêm das equipes escolhidas — como no rodapé do levantamento em
                        papel, que sai no nome de todos.
                    </p>
                </div>

                <button
                    type="button"
                    className="pw-btn pw-btn-acao"
                    style={{ marginTop: 20, minHeight: 60, opacity: podeSeguir ? 1 : 0.5 }}
                    onClick={aoSeguir}
                    disabled={!podeSeguir}
                >
                    <Icone nome="prancheta" tamanho={20} />
                    Começar o levantamento
                </button>

                <p className="pw-titulo-secao">Levantamentos recentes</p>

                {LEVANTAMENTOS_RECENTES.map((l) => (
                    <div key={l.id} className="pw-card">
                        <div className="pw-linha-espalha" style={{ marginBottom: 4 }}>
                            <span className="pw-forte" style={{ fontSize: 15.5 }}>
                                {l.rua}
                            </span>
                            <Selo tom="ok">
                                {l.linhas === 1 ? '1 ambulante' : `${l.linhas} ambulantes`}
                            </Selo>
                        </div>
                        <p className="pw-fraco" style={{ margin: 0 }}>
                            {l.bairro} · {l.dataBr}
                        </p>
                        <p className="pw-fraco" style={{ margin: '6px 0 0', fontSize: 13 }}>
                            <Icone nome="equipe" tamanho={13} /> Equipes {l.equipes.join(' / ')} ·{' '}
                            {l.encarregados.join(' / ')}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* -------------------------------- Em campo -------------------------------- */

function EmCampo({
    rua,
    bairro,
    equipes,
    encarregados,
    linhas,
    aoLinhas,
    aoVoltar,
}: {
    rua: string;
    bairro: string;
    equipes: string[];
    encarregados: string[];
    linhas: LinhaLevantamento[];
    aoLinhas: (v: LinhaLevantamento[]) => void;
    aoVoltar: () => void;
}) {
    const [nome, setNome] = useState('');
    const [documento, setDocumento] = useState('');
    const [contato, setContato] = useState('');
    const [referencia, setReferencia] = useState('');
    const [atividade, setAtividade] = useState(ATIVIDADES_LEVANTAMENTO[0]);
    const [foto, setFoto] = useState<string | null>(null);
    const [fotoSimulada, setFotoSimulada] = useState(false);
    const [ultimo, setUltimo] = useState<string | null>(null);
    const campoNome = useRef<HTMLInputElement>(null);
    const seletor = useRef<HTMLInputElement>(null);

    const podeSomar = nome.trim().length > 2;

    /* O ritmo é o do papel: fecha a linha, limpa os campos, volta o foco para o
       nome. Quem anda a rua não deve precisar tocar em nada para continuar. */
    const somar = () => {
        if (!podeSomar) {
            return;
        }

        const item = numeroDoItem(linhas.length);

        aoLinhas([
            ...linhas,
            {
                id: `lin-${Date.now()}`,
                item,
                nome: nome.trim(),
                documento: documento.trim(),
                contato: contato.trim(),
                referencia: referencia.trim(),
                atividade,
                foto,
                temFoto: Boolean(foto) || fotoSimulada,
            },
        ]);

        setUltimo(item);
        setNome('');
        setDocumento('');
        setContato('');
        setReferencia('');
        setFoto(null);
        setFotoSimulada(false);
        campoNome.current?.focus();
    };

    const preencherExemplo = () => {
        aoLinhas(
            EXEMPLOS_LEVANTAMENTO.map((e, i) => ({
                ...e,
                id: `exemplo-${i}`,
                item: numeroDoItem(i),
            })),
        );
        setUltimo(numeroDoItem(EXEMPLOS_LEVANTAMENTO.length - 1));
    };

    const comFoto = linhas.filter((l) => l.temFoto).length;

    return (
        <div className="pw-tela">
            <Topo
                titulo={rua || 'Levantamento'}
                subtitulo={`${bairro} · ${frasePluralizada('equipe', 'equipes', equipes)}`}
                aoVoltar={aoVoltar}
            />

            <div className="pw-corpo">
                <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap' }}>
                    <Selo tom="info">
                        <Icone nome="prancheta" tamanho={13} />
                        {linhas.length === 1 ? '1 levantado' : `${linhas.length} levantados`}
                    </Selo>
                    <Selo tom={comFoto === linhas.length && linhas.length > 0 ? 'ok' : 'alerta'}>
                        <Icone nome="camera" tamanho={13} />
                        {comFoto === 1 ? '1 com foto' : `${comFoto} com foto`}
                    </Selo>
                    <Selo tom="neutro">
                        <Icone nome="equipe" tamanho={13} />
                        {frasePluralizada('Encarregado', 'Encarregados', encarregados)}
                    </Selo>
                </div>

                {/* Adicionar em sequência ---------------------------------- */}
                <p className="pw-titulo-secao">
                    Item {numeroDoItem(linhas.length)}
                    {ultimo && (
                        <span className="pw-fraco" style={{ marginLeft: 8, fontWeight: 600, letterSpacing: 0 }}>
                            — {ultimo} somado
                        </span>
                    )}
                </p>

                <div className="pw-card">
                    <label className="pw-campo">
                        <span>Nome do ambulante</span>
                        <input
                            ref={campoNome}
                            className="pw-entrada"
                            value={nome}
                            onChange={(e) => setNome(e.target.value)}
                            placeholder="Nome completo"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    somar();
                                }
                            }}
                        />
                    </label>

                    <div className="pw-duas-colunas" style={{ gap: 10 }}>
                        <label className="pw-campo">
                            <span>CPF / RG</span>
                            <input
                                className="pw-entrada"
                                value={documento}
                                onChange={(e) => setDocumento(e.target.value)}
                                placeholder="000.000.000-00"
                            />
                        </label>
                        <label className="pw-campo">
                            <span>Contato</span>
                            <input
                                className="pw-entrada"
                                value={contato}
                                onChange={(e) => setContato(e.target.value)}
                                placeholder="(71) 90000-0000"
                                inputMode="tel"
                            />
                        </label>
                    </div>

                    <label className="pw-campo">
                        <span>Referência do ponto</span>
                        <input
                            className="pw-entrada"
                            value={referencia}
                            onChange={(e) => setReferencia(e.target.value)}
                            placeholder="Ex.: Nº 02"
                        />
                    </label>

                    <label className="pw-campo">
                        <span>Atividade</span>
                        <div className="pw-chips" style={{ gap: 8 }}>
                            {ATIVIDADES_LEVANTAMENTO.map((a) => (
                                <button
                                    key={a}
                                    type="button"
                                    className={classes('pw-chip', 'pw-chip-mini', atividade === a && 'pw-chip-ligado')}
                                    onClick={() => setAtividade(a)}
                                >
                                    {a}
                                </button>
                            ))}
                        </div>
                    </label>

                    <div className="pw-campo" style={{ marginBottom: 0 }}>
                        <span>Foto do equipamento</span>
                        <div className="pw-fotos">
                            {(foto || fotoSimulada) && (
                                <div className="pw-foto">
                                    {foto ? <img src={foto} alt="Equipamento levantado" /> : '📷'}
                                    <button
                                        type="button"
                                        className="pw-foto-remove"
                                        onClick={() => {
                                            setFoto(null);
                                            setFotoSimulada(false);
                                        }}
                                        aria-label="Remover foto"
                                    >
                                        <Icone nome="lixeira" tamanho={14} />
                                    </button>
                                </div>
                            )}
                            {!foto && !fotoSimulada && (
                                <>
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
                                        onClick={() => setFotoSimulada(true)}
                                        title="Foto simulada, para ver a tela cheia sem câmera"
                                    >
                                        <span style={{ fontSize: 13, fontWeight: 700 }}>Simular</span>
                                    </button>
                                </>
                            )}
                        </div>
                        <input
                            ref={seletor}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            hidden
                            onChange={(e) => {
                                const arquivo = e.target.files?.[0];

                                if (arquivo) {
                                    setFoto(URL.createObjectURL(arquivo));
                                }
                            }}
                        />
                    </div>
                </div>

                <button
                    type="button"
                    className="pw-btn pw-btn-acao"
                    style={{ marginTop: 14, minHeight: 58, opacity: podeSomar ? 1 : 0.5 }}
                    onClick={somar}
                    disabled={!podeSomar}
                >
                    <Icone nome="mais" tamanho={20} />
                    Somar e ir ao próximo
                </button>

                {linhas.length === 0 && (
                    <button
                        type="button"
                        className="pw-btn pw-btn-fantasma"
                        style={{ marginTop: 10 }}
                        onClick={preencherExemplo}
                    >
                        Preencher exemplo com 6 linhas
                    </button>
                )}

                {/* A lista da rua ---------------------------------------- */}
                <p className="pw-titulo-secao">Levantados nesta rua</p>

                {linhas.length === 0 ? (
                    <Vazio
                        icone="🧾"
                        titulo="A rua ainda está em branco"
                        texto="Some o primeiro ambulante acima — a numeração começa em 001."
                    />
                ) : (
                    <>
                        {[...linhas].reverse().map((linha) => (
                            <div key={linha.id} className="pw-card pw-linha-levantada">
                                <span className="pw-item-numero">{linha.item}</span>
                                <div style={{ minWidth: 0, flex: 1 }}>
                                    <p className="pw-forte" style={{ margin: 0, fontSize: 15.5 }}>
                                        {linha.nome}
                                    </p>
                                    <p className="pw-fraco" style={{ margin: '2px 0 0', fontSize: 13.5 }}>
                                        {linha.atividade}
                                        {linha.referencia ? ` · ${linha.referencia}` : ''}
                                    </p>
                                    <p className="pw-fraco" style={{ margin: '2px 0 0', fontSize: 13 }}>
                                        {linha.documento || 'documento não informado'}
                                        {linha.contato ? ` · ${linha.contato}` : ''}
                                    </p>
                                </div>
                                <span className={classes('pw-miniatura', !linha.temFoto && 'pw-miniatura-vazia')}>
                                    {linha.foto ? (
                                        <img
                                            src={linha.foto}
                                            alt={`Equipamento de ${linha.nome}`}
                                            style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: 10 }}
                                        />
                                    ) : linha.temFoto ? (
                                        '📷'
                                    ) : (
                                        '—'
                                    )}
                                </span>
                                <button
                                    type="button"
                                    className="pw-foto-remove pw-remove-linha"
                                    onClick={() => aoLinhas(linhas.filter((l) => l.id !== linha.id))}
                                    aria-label={`Remover ${linha.nome}`}
                                >
                                    <Icone nome="lixeira" tamanho={14} />
                                </button>
                            </div>
                        ))}

                        <div className="pw-card pw-total-levantamento">
                            <div className="pw-linha-espalha">
                                <span className="pw-forte" style={{ fontSize: 16 }}>
                                    Total da rua
                                </span>
                                <span className="pw-forte" style={{ fontSize: 26, color: 'var(--pw-primaria)' }}>
                                    {linhas.length}
                                </span>
                            </div>
                            <p className="pw-fraco" style={{ margin: '4px 0 0' }}>
                                {rua} · {bairro} — levantamento realizado por{' '}
                                {frasePluralizada('equipe', 'equipes', equipes)},{' '}
                                {frasePluralizada('encarregado', 'encarregados', encarregados)}.
                            </p>
                            <button
                                type="button"
                                className="pw-btn pw-btn-contorno"
                                style={{ marginTop: 14 }}
                                onClick={() => irPara('calor')}
                            >
                                <Icone nome="calor" tamanho={18} />
                                Ver no mapa de calor
                            </button>
                            <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 12.5 }}>
                                No protótipo o levantamento não altera o mapa de calor — o botão mostra
                                para onde esse dado vai.
                            </p>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

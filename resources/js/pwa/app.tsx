import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

import { api, ErroDoServidor } from './api';
import { BarraInferior, type Aba } from './componentes';
import { LOCAL_APOS_O_DESFECHO, carregarDemandas, demandasVencidas, type Demanda, type Desfecho } from './dados-demandas';
import { carregarNumeros } from './dados-documentos';
import { carregarLevantamentos, type Levantamento, type LinhaLevantamento } from './dados-levantamento';
import {
    HOJE_BR,
    carregarBairros,
    carregarCampo,
    horaAgora,
    type Ambulante,
    type DocumentoParaEnvio,
    type IncidenciaCalor,
    type Operacao,
    type OrigemRegistro,
    type Regiao,
    type Registro,
    type ViaImpressa,
} from './dados-prototipo';
import {
    filaDe,
    guardarLevantamento,
    guardarNaFila,
    levantamentosDe,
    tirarDaFila,
    tirarLevantamento,
    type LevantamentoNaFila,
} from './fila-local';
import { FISCAL, aplicarSessao, type SessaoDoServidor } from './sessao';
import { TelaApreensao } from './telas/apreensao';
import { TelaCalor } from './telas/calor';
import { TelaDemanda } from './telas/demanda';
import { TelaDemandas } from './telas/demandas';
import { TelaDocumentos } from './telas/documentos';
import { TelaEntrada } from './telas/entrada';
import { TelaInicio } from './telas/inicio';
import { TelaLevantamento } from './telas/levantamento';
import { TelaMapa } from './telas/mapa';
import { TelaNotificacao } from './telas/notificacao';
import { TelaPerfil } from './telas/perfil';
import { TelaRecibo } from './telas/recibo';
import { TelaRegistroRapido } from './telas/registro-rapido';
import { TelaRegistros } from './telas/registros';
import { TelaSincronizacao } from './telas/sincronizacao';
import { TelaVia } from './telas/via';

/* ============================================================================
   Aplicativo do fiscal (SEFAL)
   ----------------------------------------------------------------------------
   Uma página só, sem Inertia e sem biblioteca de rotas: o endereço vive no
   fragmento (`#/mapa`, `#/recibo/reg-013`) e um `switch` decide a tela. São
   quinze telas; uma biblioteca de roteamento aqui pesaria mais que o
   aplicativo.

   A barra de baixo tem SEIS destinos porque o trabalho do fiscal tem duas
   entradas, não uma: além do que ele acha andando a rua (o mapa), há a fila do
   que o líder enviou à equipe (as demandas). Esconder a fila atrás de outra
   tela seria esconder metade do serviço.

   O SERVIDOR é a Retaguarda, na mesma origem e na mesma sessão (`api.ts`). Ao
   abrir, o aplicativo pergunta quem está logado e carrega o "kit" do turno: a
   fila da equipe, os registros do fiscal, o catálogo de bairros e os números
   oficiais reservados para ele. O registro nasce no aparelho — e fica guardado
   na fila local (`fila-local.ts`), com as fotos — e sobe ao servidor quando o
   fiscal o despacha, ou quando o sinal voltar.
   ============================================================================ */

export type NovoRegistro = {
    /* O que o fiscal escolheu no fim da vistoria (ou do retorno). A leitura
       "regular / irregular" NÃO vem da tela: é derivada aqui, para a mesma
       decisão não ter dois donos. */
    desfecho: Desfecho;
    ocorrencias: string[];
    relato: string;
    fotos: number;
    /** Os arquivos das fotos — sobem junto com o registro. */
    arquivos: Blob[];
    precisaoM: number | null;
    ambulante: string | null;
    /** O ambulante do cadastro, quando o registro saiu de um pino do mapa. */
    ambulanteId: number | null;
    retornoBr: string | null;
    lat: number;
    lng: number;
    endereco: string;
    regiao: string;
    origem: OrigemRegistro;
    demandaId: string | null;
    referencia: string | null;
};

type Contexto = {
    registros: Registro[];
    pendentes: number;
    tema: 'claro' | 'escuro';
    alternarTema: () => void;
    registrar: (novo: NovoRegistro) => string;
    anexarDocumento: (id: string, via: ViaImpressa, nome: string | null, documento: DocumentoParaEnvio) => void;
    /** Fecha o registro e o envia ao servidor — ele vai ao líder da equipe. */
    despachar: (id: string, consideracoes: string, recomendacoes: string[]) => void;
    /** Envia o que está na fila e recarrega a fila da equipe e os números. */
    sincronizar: () => Promise<void>;
    sincronizando: boolean;
    /** A frase do último problema de carga (sem sinal, sessão expirada…). */
    aviso: string | null;
    /** Muda a cada kit novo — é a dependência das telas que desenham o que veio dele. */
    versao: number;
    /** Conclui o levantamento da rua: envia, ou guarda no aparelho sem sinal. */
    concluirLevantamento: (dados: NovoLevantamento) => Promise<{ concluido: boolean; frase: string }>;
    sair: () => void;
};

export type NovoLevantamento = {
    rua: string;
    bairro: string;
    equipes: string[];
    iniciadoEm: string | null;
    linhas: LinhaLevantamento[];
};

const ContextoApp = createContext<Contexto | null>(null);

export const useApp = (): Contexto => {
    const contexto = useContext(ContextoApp);

    if (!contexto) {
        throw new Error('As telas do aplicativo precisam estar dentro do App.');
    }

    return contexto;
};

/* -------------------------------- Navegação -------------------------------- */

export const irPara = (destino: string): void => {
    window.location.hash = destino;
};

/**
 * Volta para a tela ANTERIOR — de verdade, não para um destino fixo.
 *
 * A conclusão do registro é alcançada por caminhos diferentes (o despacho que
 * acabou de acontecer, a lista de registros, a ficha de um ponto no mapa), e um
 * botão que sempre levava ao mapa mandava o fiscal para longe de onde ele
 * estava. O mapa fica como rede de segurança de quem abriu o aplicativo direto
 * num endereço colado, sem histórico para onde voltar.
 */
export const voltar = (): void => {
    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    irPara('mapa');
};

const lerHash = (): string => window.location.hash.replace(/^#\/?/, '') || 'entrada';

function useRota(): { tela: string; parametro: string | null } {
    const [caminho, setCaminho] = useState(lerHash);

    useEffect(() => {
        const ouvir = () => setCaminho(lerHash());

        window.addEventListener('hashchange', ouvir);

        return () => window.removeEventListener('hashchange', ouvir);
    }, []);

    /* Toda troca de tela começa do alto. Sem isto, quem conclui um registro no
       fim de uma tela longa cai no meio do recibo e acha que nada aconteceu. */
    useEffect(() => {
        window.scrollTo({ top: 0 });
    }, [caminho]);

    const partes = caminho.split('/');

    return { tela: partes[0] || 'entrada', parametro: partes[1] ?? null };
}

/**
 * O padrão do aplicativo é o tema CLARO — quem trabalha na rua está sob o sol, e o claro
 * é o que se lê no reflexo. Antes seguíamos o tema do aparelho, e num celular configurado
 * no escuro o aplicativo abria escuro sem ninguém ter pedido. Escolha explícita do fiscal
 * (o botão no perfil) é lembrada; a ausência de escolha continua sendo o claro.
 */
const temaInicial = (): 'claro' | 'escuro' => {
    try {
        const salvo = window.localStorage.getItem('sefal-tema');
        if (salvo === 'escuro' || salvo === 'claro') return salvo;
    } catch {
        // Armazenamento bloqueado (janela privada): segue o padrão.
    }

    return 'claro';
};

/* ------------------------------ Servidor ------------------------------ */

type Kit = {
    demandas: Demanda[];
    registros: Registro[];
    bairros: Regiao[];
    numeros: { np: string[]; aa: string[] };
    ambulantes: (Omit<Ambulante, 'emoji'> & { emoji: string | null })[];
    incidencias: IncidenciaCalor[];
    operacoes: Operacao[];
    levantamentos: Levantamento[];
    equipesDaCidade: { equipe: string; regiao: string; encarregado: string }[];
};

const novoId = (): string =>
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;

const paraFila = (registro: Registro, fotos: Blob[]) => ({ matricula: FISCAL.matricula, registro, fotos });

/** Monta o envio: o registro inteiro, as fotos e o documento lavrado. */
function corpoDoEnvio(r: Registro, fotos: Blob[]): FormData {
    const corpo = new FormData();
    const por = (chave: string, valor: string | number | null | undefined) => {
        if (valor !== null && valor !== undefined && valor !== '') {
            corpo.append(chave, String(valor));
        }
    };

    por('client_id', r.clientId);
    por('demanda_id', r.demandaId?.startsWith('den-') ? r.demandaId.slice(4) : null);
    por('desfecho', r.desfecho);
    por('relato', r.relato);
    por('ambulante', r.ambulante);
    por('ambulante_id', r.ambulanteId);
    por('latitude', r.lat);
    por('longitude', r.lng);
    por('precisao_m', r.precisaoM);
    por('endereco', r.endereco);
    por('bairro', r.regiao);
    por('aberta_em', r.abertaEm);
    por('consideracoes', r.consideracoes);

    if (r.retornoBr) {
        const [dia, mes, ano] = r.retornoBr.split('/');
        por('retorno_em', `${ano}-${mes}-${dia}`);
    }

    r.ocorrencias.forEach((o) => corpo.append('ocorrencias[]', o));
    r.recomendacoes.forEach((c) => corpo.append('recomendacoes[]', c));
    fotos.forEach((f, i) => corpo.append('fotos[]', f, `foto-${i + 1}.${f.type === 'image/png' ? 'png' : 'jpg'}`));

    if (r.documentoDados) {
        por('documento', JSON.stringify(r.documentoDados));
    }

    return corpo;
}

/** O levantamento da fila como ele sobe: as linhas em JSON e a foto de cada uma. */
function corpoDoLevantamento(l: LevantamentoNaFila): FormData {
    const corpo = new FormData();

    corpo.append('client_id', l.clientId);
    corpo.append('rua', l.rua);
    corpo.append('bairro', l.bairro);
    l.equipes.forEach((e) => corpo.append('equipes[]', e));

    if (l.iniciadoEm) {
        corpo.append('iniciado_em', l.iniciadoEm);
    }

    corpo.append('linhas', JSON.stringify(l.linhas));
    Object.entries(l.fotos).forEach(([linha, foto]) => corpo.append(`fotos[${linha}]`, foto, `${linha}.jpg`));

    return corpo;
}

/** Como o levantamento que ainda está no aparelho aparece entre os recentes. */
const comoRecente = (l: LevantamentoNaFila): Levantamento => ({
    id: l.clientId,
    rua: l.rua,
    bairro: l.bairro,
    equipes: l.equipes,
    encarregados: [],
    dataBr: new Date(l.concluidoEm).toLocaleDateString('pt-BR'),
    linhas: l.linhas.length,
    situacao: 'em-campo',
});

/* ---------------------------------- Tela ---------------------------------- */

export function App() {
    const { tela, parametro } = useRota();
    const [verificando, setVerificando] = useState(true);
    const [entrou, setEntrou] = useState(false);
    const [registros, setRegistros] = useState<Registro[]>([]);
    const [aviso, setAviso] = useState<string | null>(null);
    const [sincronizando, setSincronizando] = useState(false);
    /* A fila, os bairros e os números moram em módulos (ligações vivas); trocar
       esta versão é o que faz as telas lerem de novo depois de um kit novo. */
    const [versao, setVersao] = useState(0);
    const [tema, setTema] = useState<'claro' | 'escuro'>(temaInicial);
    const fotos = useRef(new Map<string, Blob[]>());
    const atuais = useRef<Registro[]>([]);
    /* Levantamentos concluídos que o servidor ainda não confirmou. */
    const [levantamentosNaFila, setLevantamentosNaFila] = useState<LevantamentoNaFila[]>([]);
    const kitDeLevantamentos = useRef<{ recentes: Levantamento[]; equipes: Kit['equipesDaCidade'] }>({
        recentes: [],
        equipes: [],
    });

    useEffect(() => {
        atuais.current = registros;
    }, [registros]);

    useEffect(() => {
        document.documentElement.dataset.tema = tema;
    }, [tema]);

    /** Busca o kit e junta os registros do servidor com os que só existem no aparelho. */
    const carregarKit = useCallback(async () => {
        try {
            const kit = await api.get<Kit>('kit');

            carregarDemandas(kit.demandas);
            carregarBairros(kit.bairros);
            carregarNumeros(kit.numeros);
            carregarCampo(kit);

            const levantamentosPendentes = (await levantamentosDe(FISCAL.matricula)).filter(
                (l) => !kit.levantamentos.some((k) => k.clientId === l.clientId),
            );

            kitDeLevantamentos.current = { recentes: kit.levantamentos, equipes: kit.equipesDaCidade };
            carregarLevantamentos([...levantamentosPendentes.map(comoRecente), ...kit.levantamentos], kit.equipesDaCidade);
            setLevantamentosNaFila(levantamentosPendentes);

            const naFila = await filaDe(FISCAL.matricula);
            const doServidor = new Set(kit.registros.map((r) => r.clientId).filter(Boolean));

            for (const entrada of naFila) {
                const id = entrada.registro.clientId as string;

                if (doServidor.has(id)) {
                    /* Chegou por um envio cuja resposta se perdeu: sai da fila. */
                    await tirarDaFila(id);
                } else {
                    fotos.current.set(id, entrada.fotos);
                }
            }

            const soNoAparelho = naFila.map((e) => e.registro).filter((r) => !doServidor.has(r.clientId));

            atuais.current = [...soNoAparelho, ...kit.registros];
            setRegistros(atuais.current);
            setAviso(null);
            setVersao((v) => v + 1);
        } catch (erro) {
            if (erro instanceof ErroDoServidor && erro.status === 401) {
                setEntrou(false);
                irPara('entrada');
            }

            setAviso(erro instanceof Error ? erro.message : 'Não foi possível carregar a fila.');
        }
    }, []);

    /** Envia um registro despachado. Devolve se chegou. */
    const enviar = useCallback(async (registro: Registro): Promise<boolean> => {
        const id = registro.clientId as string;

        try {
            const resposta = await api.post<{ registro: Registro; numeros: Kit['numeros'] }>(
                'fiscalizacoes',
                corpoDoEnvio(registro, fotos.current.get(id) ?? []),
            );

            carregarNumeros(resposta.numeros);
            await tirarDaFila(id);
            fotos.current.delete(id);
            /* Fica o id local: a tela que estiver aberta continua achando o registro. */
            atuais.current = atuais.current.map((r) =>
                r.clientId === id ? { ...resposta.registro, id: r.id, via: r.via ?? resposta.registro.via } : r,
            );
            setRegistros(atuais.current);

            return true;
        } catch (erro) {
            const passageiro = erro instanceof ErroDoServidor ? erro.passageiro : true;
            const frase = erro instanceof Error ? erro.message : 'Falha no envio.';

            atuais.current = atuais.current.map((r) =>
                r.clientId === id ? { ...r, envio: passageiro ? 'pendente' : 'erro', erroEnvio: frase } : r,
            );
            setRegistros(atuais.current);

            return false;
        }
    }, []);

    /** Envia um levantamento da fila. Devolve a frase do resultado, ou `null` se chegou. */
    const enviarLevantamento = useCallback(async (l: LevantamentoNaFila): Promise<{ chegou: boolean; frase: string }> => {
        try {
            const resposta = await api.post<{ levantamento: Levantamento }>(
                'levantamentos',
                corpoDoLevantamento(l),
            );

            await tirarLevantamento(l.clientId);
            setLevantamentosNaFila((lista) => lista.filter((x) => x.clientId !== l.clientId));

            return {
                chegou: true,
                frase: `Levantamento ${resposta.levantamento.protocolo} gravado: ${resposta.levantamento.linhas === 1 ? '1 ambulante' : `${resposta.levantamento.linhas} ambulantes`} em ${l.rua}.`,
            };
        } catch (erro) {
            const frase = erro instanceof Error ? erro.message : 'Falha no envio.';

            if (erro instanceof ErroDoServidor && !erro.passageiro) {
                /* Recusa de conteúdo: na fila ele voltaria a ser recusado para
                   sempre. Sai dela, e a tela — que ainda tem as linhas — mostra
                   o motivo para o fiscal corrigir. */
                await tirarLevantamento(l.clientId);
                setLevantamentosNaFila((lista) => lista.filter((x) => x.clientId !== l.clientId));

                return { chegou: false, frase };
            }

            return { chegou: false, frase: `${frase} O levantamento ficou guardado no aparelho e sobe quando o sinal voltar.` };
        }
    }, []);

    const concluirLevantamento = useCallback(
        async (dados: NovoLevantamento): Promise<{ concluido: boolean; frase: string }> => {
            const entrada: LevantamentoNaFila = {
                matricula: FISCAL.matricula,
                clientId: novoId(),
                rua: dados.rua.trim(),
                bairro: dados.bairro.trim(),
                equipes: dados.equipes,
                iniciadoEm: dados.iniciadoEm,
                concluidoEm: new Date().toISOString(),
                linhas: dados.linhas.map((l) => ({
                    id: l.id,
                    nome: l.nome,
                    documento: l.documento,
                    contato: l.contato,
                    referencia: l.referencia,
                    atividade: l.atividade,
                    latitude: l.latitude ?? null,
                    longitude: l.longitude ?? null,
                })),
                fotos: Object.fromEntries(dados.linhas.flatMap((l) => (l.arquivo ? [[l.id, l.arquivo]] : []))),
            };

            await guardarLevantamento(entrada);
            setLevantamentosNaFila((lista) => [...lista, entrada]);

            const resultado = await enviarLevantamento(entrada);

            if (resultado.chegou) {
                await carregarKit();

                return { concluido: true, frase: resultado.frase };
            }

            /* Sem sinal: concluído no aparelho. Recusado: volta para o fiscal corrigir. */
            const naFila = await levantamentosDe(FISCAL.matricula);
            const ficou = naFila.some((l) => l.clientId === entrada.clientId);

            if (ficou) {
                carregarLevantamentos(
                    [...naFila.map(comoRecente), ...kitDeLevantamentos.current.recentes],
                    kitDeLevantamentos.current.equipes,
                );
                setVersao((v) => v + 1);
            }

            return { concluido: ficou, frase: resultado.frase };
        },
        [enviarLevantamento, carregarKit],
    );

    const sincronizar = useCallback(async () => {
        setSincronizando(true);

        try {
            const fila = atuais.current.filter((r) => r.envio !== 'enviado' && r.despachadoBr !== null);

            for (const registro of fila) {
                await enviar(registro);
            }

            for (const levantamento of await levantamentosDe(FISCAL.matricula)) {
                await enviarLevantamento(levantamento);
            }

            await carregarKit();
        } finally {
            setSincronizando(false);
        }
    }, [enviar, enviarLevantamento, carregarKit]);

    /* Ao abrir: quem está logado? Com sessão, carrega o kit; sem, mostra a porta. */
    useEffect(() => {
        api.get<SessaoDoServidor>('sessao')
            .then(async (sessao) => {
                aplicarSessao(sessao);
                setEntrou(true);
                await carregarKit();

                if (lerHash() === 'entrada') {
                    irPara('inicio');
                }
            })
            .catch((erro) => {
                if (erro instanceof ErroDoServidor && erro.status === 0) {
                    setAviso(erro.message);
                }
            })
            .finally(() => setVerificando(false));
    }, [carregarKit]);

    /* Voltou o sinal: o que esperava na fila sobe sozinho. */
    useEffect(() => {
        const aoVoltar = () => {
            if (entrou) {
                void sincronizar();
            }
        };

        window.addEventListener('online', aoVoltar);

        return () => window.removeEventListener('online', aoVoltar);
    }, [entrou, sincronizar]);

    const registrar = useCallback((novo: NovoRegistro): string => {
        const clientId = novoId();
        const id = `reg-${clientId}`;

        const registro: Registro = {
            id,
            /* O protocolo oficial (FIS…) é o servidor que dá; até lá, o registro
               diz que ainda está no aparelho. */
            protocolo: 'No aparelho',
            dataBr: HOJE_BR,
            hora: horaAgora(),
            regiao: novo.regiao,
            endereco: novo.endereco,
            lat: novo.lat,
            lng: novo.lng,
            desfecho: novo.desfecho,
            status: LOCAL_APOS_O_DESFECHO[novo.desfecho],
            ocorrencias: novo.ocorrencias,
            relato: novo.relato,
            fotos: novo.fotos,
            ambulante: novo.ambulante,
            ambulanteId: novo.ambulanteId,
            retornoBr: novo.retornoBr,
            envio: 'pendente',
            documento: null,
            documentoTipo: null,
            via: null,
            origem: novo.origem,
            demandaId: novo.demandaId,
            referencia: novo.referencia,
            /* O registro nasce ABERTO: quem escreve as considerações finais é
               a tela de conclusão, no passo seguinte. */
            consideracoes: '',
            recomendacoes: [],
            despachadoBr: null,
            clientId,
            abertaEm: new Date().toISOString(),
            precisaoM: novo.precisaoM,
            documentoDados: null,
            erroEnvio: null,
        };

        fotos.current.set(clientId, novo.arquivos);
        void guardarNaFila(paraFila(registro, novo.arquivos));
        atuais.current = [registro, ...atuais.current];
        setRegistros(atuais.current);

        return id;
    }, []);

    /** Aplica a mudança, guarda na fila local e devolve o registro novo. */
    const alterar = useCallback((id: string, mudar: (r: Registro) => Registro): Registro | null => {
        const antes = atuais.current.find((r) => r.id === id);

        if (!antes) {
            return null;
        }

        const depois = mudar(antes);

        atuais.current = atuais.current.map((r) => (r.id === id ? depois : r));
        setRegistros(atuais.current);

        if (depois.clientId && depois.envio !== 'enviado') {
            void guardarNaFila(paraFila(depois, fotos.current.get(depois.clientId) ?? []));
        }

        return depois;
    }, []);

    /**
     * O despacho: guarda as considerações finais, fecha o registro e o envia.
     * Sem sinal, ele fica na fila e sobe quando o sinal voltar.
     */
    const despachar = useCallback(
        (id: string, consideracoes: string, recomendacoes: string[]) => {
            const fechado = alterar(id, (r) => ({
                ...r,
                consideracoes,
                recomendacoes,
                despachadoBr: HOJE_BR,
                erroEnvio: null,
            }));

            if (fechado) {
                void enviar(fechado).then((chegou) => (chegou ? carregarKit() : undefined));
            }
        },
        [alterar, enviar, carregarKit],
    );

    const anexarDocumento = useCallback(
        (id: string, via: ViaImpressa, nome: string | null, documento: DocumentoParaEnvio) => {
            alterar(id, (r) => ({
                ...r,
                documento: `${via.tipo === 'np' ? 'NP' : 'AA'} ${via.numero}`,
                documentoTipo: via.tipo,
                via,
                documentoDados: documento,
                ambulante: nome ?? r.ambulante,
            }));
        },
        [alterar],
    );

    /* O que ficou na fila local NÃO é apagado: é do fiscal que saiu, e sobe na
       próxima vez que ele entrar neste aparelho. */
    const sair = useCallback(() => {
        void api.post('sair').catch(() => undefined);
        setEntrou(false);
        atuais.current = [];
        setRegistros([]);
        carregarDemandas([]);
        irPara('entrada');
    }, []);

    /* "Pendente" é o que espera SINAL, não o que espera o fiscal: registro ainda
       aberto contado aqui faria o selo do rodapé prometer um envio que nem foi
       pedido. O que falta concluir aparece na própria tela de envio, em bloco
       separado. */
    const pendentes = useMemo(
        () =>
            registros.filter((r) => r.envio !== 'enviado' && r.despachadoBr !== null).length +
            levantamentosNaFila.length,
        [registros, levantamentosNaFila],
    );

    const contexto = useMemo<Contexto>(
        () => ({
            registros,
            pendentes,
            tema,
            alternarTema: () =>
                setTema((t) => {
                    const proximo = t === 'claro' ? 'escuro' : 'claro';
                    // A escolha do fiscal é lembrada no aparelho — sem isto, o aplicativo
                    // voltaria ao claro a cada abertura e quem trabalha à noite (a equipe
                    // Noturna existe) teria de alternar todos os dias.
                    try {
                        window.localStorage.setItem('sefal-tema', proximo);
                    } catch {
                        // Armazenamento bloqueado: vale só nesta sessão.
                    }

                    return proximo;
                }),
            registrar,
            anexarDocumento,
            despachar,
            sincronizar,
            sincronizando,
            aviso,
            versao,
            concluirLevantamento,
            sair,
        }),
        [
            registros,
            pendentes,
            tema,
            registrar,
            anexarDocumento,
            despachar,
            sincronizar,
            sincronizando,
            aviso,
            versao,
            concluirLevantamento,
            sair,
        ],
    );

    if (verificando) {
        return <div className="pw-entrada-tela" aria-busy="true" />;
    }

    /* Quem ainda não entrou só vê a porta — inclusive quem chegar por um endereço
       interno colado no navegador. */
    if (!entrou || tela === 'entrada') {
        return (
            <ContextoApp.Provider value={contexto}>
                <FaixaPrototipo />
                <TelaEntrada
                    aviso={aviso}
                    aoEntrar={async (login, senha) => {
                        try {
                            const sessao = await api.post<SessaoDoServidor>('entrar', { login, password: senha });

                            /* A identidade é trocada ANTES de a árvore autenticada
                               montar: é ela que decide a equipe e, com a equipe, a
                               fila e o contorno da área no mapa. */
                            aplicarSessao(sessao);
                            setEntrou(true);
                            await carregarKit();
                            irPara('inicio');

                            return null;
                        } catch (erro) {
                            return erro instanceof Error ? erro.message : 'Não foi possível entrar.';
                        }
                    }}
                />
            </ContextoApp.Provider>
        );
    }

    const abaAtiva: Aba = (
        ['inicio', 'demandas', 'mapa', 'registros', 'calor', 'sincronizacao'].includes(tela)
            ? tela
            : 'inicio'
    ) as Aba;

    const comBarra = abaAtiva === tela;

    return (
        <ContextoApp.Provider value={contexto}>
            <FaixaPrototipo />
            <div className="pw-com-faixa">
                {tela === 'inicio' && <TelaInicio />}
                {tela === 'demandas' && <TelaDemandas />}
                {tela === 'demanda' && <TelaDemanda id={parametro} />}
                {tela === 'mapa' && <TelaMapa regiaoFoco={parametro} />}
                {tela === 'registrar' && <TelaRegistroRapido alvo={parametro} />}
                {tela === 'recibo' && <TelaRecibo id={parametro} />}
                {tela === 'documentos' && <TelaDocumentos id={parametro} />}
                {tela === 'notificacao' && <TelaNotificacao id={parametro} />}
                {tela === 'apreensao' && <TelaApreensao id={parametro} />}
                {tela === 'via' && <TelaVia id={parametro} />}
                {tela === 'levantamento' && <TelaLevantamento />}
                {tela === 'registros' && <TelaRegistros />}
                {tela === 'calor' && <TelaCalor />}
                {tela === 'sincronizacao' && <TelaSincronizacao />}
                {tela === 'perfil' && <TelaPerfil />}
            </div>
            {comBarra && (
                <BarraInferior
                    ativa={abaAtiva}
                    aoTrocar={(aba) => irPara(aba)}
                    pendentes={pendentes}
                    demandas={demandasVencidas().length}
                />
            )}
        </ContextoApp.Provider>
    );
}

/* Todos os dados já vêm do servidor; a faixa diz só que o aplicativo está em
   implantação (homologação), para ninguém tomá-lo pela versão final. */
function FaixaPrototipo() {
    return <div className="pw-faixa-prototipo">Em implantação · homologação</div>;
}

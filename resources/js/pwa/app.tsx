import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import { BarraInferior, type Aba } from './componentes';
import { LOCAL_APOS_O_DESFECHO, demandasVencidas, type Desfecho } from './dados-demandas';
import { entrarComMatricula } from './sessao';
import {
    HOJE_BR,
    REGISTROS,
    horaAgora,
    type OrigemRegistro,
    type Registro,
    type ViaImpressa,
} from './dados-prototipo';
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
   PROTÓTIPO — aplicativo do fiscal (SEFAL)
   ----------------------------------------------------------------------------
   Uma página só, sem Inertia e sem biblioteca de rotas: o endereço vive no
   fragmento (`#/mapa`, `#/recibo/reg-013`) e um `switch` decide a tela. São
   quinze telas; uma biblioteca de roteamento aqui pesaria mais que o
   aplicativo.

   A barra de baixo tem SEIS destinos porque o trabalho do fiscal passou a ter
   duas entradas, não uma: além do que ele acha andando a rua (o mapa), há a
   fila do que o administrativo encaminhou à equipe (as demandas). Esconder a
   fila atrás de outra tela seria esconder metade do serviço.

   O estado também é deliberadamente simples — o que o fiscal registra fica na
   memória da aba. É protótipo: não há servidor do outro lado, e fingir que há
   seria esconder do dono exatamente o que ainda falta construir.
   ============================================================================ */

export type NovoRegistro = {
    /* O que o fiscal escolheu no fim da vistoria (ou do retorno). A leitura
       "regular / irregular" NÃO vem da tela: é derivada aqui, para a mesma
       decisão não ter dois donos. */
    desfecho: Desfecho;
    ocorrencias: string[];
    relato: string;
    fotos: number;
    ambulante: string | null;
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
    anexarDocumento: (id: string, via: ViaImpressa, nome: string | null) => void;
    esvaziarFila: () => void;
    sair: () => void;
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

/* ---------------------------------- Tela ---------------------------------- */

export function App() {
    const { tela, parametro } = useRota();
    const [entrou, setEntrou] = useState(false);
    const [registros, setRegistros] = useState<Registro[]>(REGISTROS);
    const [tema, setTema] = useState<'claro' | 'escuro'>(temaInicial);

    useEffect(() => {
        document.documentElement.dataset.tema = tema;
    }, [tema]);

    const registrar = useCallback((novo: NovoRegistro): string => {
        const id = `reg-novo-${Date.now()}`;
        /* O protocolo carrega a data de HOJE, calculada — escrita à mão, ela
           envelheceria e o registro de hoje sairia com a data da semana passada. */
        const [dia, mes, ano] = HOJE_BR.split('/');

        setRegistros((atuais) => [
            {
                id,
                protocolo: `FA${ano}${mes}${dia}${String(atuais.length + 1).padStart(3, '0')}`,
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
                retornoBr: novo.retornoBr,
                envio: 'pendente',
                documento: null,
                documentoTipo: null,
                via: null,
                origem: novo.origem,
                demandaId: novo.demandaId,
                referencia: novo.referencia,
            },
            ...atuais,
        ]);

        return id;
    }, []);

    const anexarDocumento = useCallback((id: string, via: ViaImpressa, nome: string | null) => {
        setRegistros((atuais) =>
            atuais.map((r) =>
                r.id === id
                    ? {
                          ...r,
                          documento: `${via.tipo === 'np' ? 'NP' : 'AA'} ${via.numero}`,
                          documentoTipo: via.tipo,
                          via,
                          ambulante: nome ?? r.ambulante,
                      }
                    : r,
            ),
        );
    }, []);

    const esvaziarFila = useCallback(() => {
        setRegistros((atuais) => atuais.map((r) => ({ ...r, envio: 'enviado' as const })));
    }, []);

    const sair = useCallback(() => {
        setEntrou(false);
        irPara('entrada');
    }, []);

    const pendentes = useMemo(
        () => registros.filter((r) => r.envio !== 'enviado').length,
        [registros],
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
            esvaziarFila,
            sair,
        }),
        [registros, pendentes, tema, registrar, anexarDocumento, esvaziarFila, sair],
    );

    /* Quem ainda não entrou só vê a porta — inclusive quem chegar por um endereço
       interno colado no navegador. */
    if (!entrou || tela === 'entrada') {
        return (
            <ContextoApp.Provider value={contexto}>
                <FaixaPrototipo />
                <TelaEntrada
                    aoEntrar={(matricula) => {
                        /* A identidade é trocada ANTES de a árvore autenticada
                           montar: é ela que decide a equipe e, com a equipe, a
                           fila e o contorno da área no mapa. */
                        entrarComMatricula(matricula);
                        setEntrou(true);
                        irPara('inicio');
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

function FaixaPrototipo() {
    return <div className="pw-faixa-prototipo">Protótipo · dados fictícios</div>;
}

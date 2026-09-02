import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import { BarraInferior, type Aba } from './componentes';
import { HOJE_BR, REGISTROS, horaAgora, type Registro, type Situacao } from './dados-prototipo';
import { TelaCalor } from './telas/calor';
import { TelaEntrada } from './telas/entrada';
import { TelaEscalada } from './telas/escalada';
import { TelaInicio } from './telas/inicio';
import { TelaMapa } from './telas/mapa';
import { TelaPerfil } from './telas/perfil';
import { TelaRecibo } from './telas/recibo';
import { TelaRegistroRapido } from './telas/registro-rapido';
import { TelaRegistros } from './telas/registros';
import { TelaSincronizacao } from './telas/sincronizacao';

/* ============================================================================
   PROTÓTIPO — aplicativo do fiscal (SEFAL)
   ----------------------------------------------------------------------------
   Uma página só, sem Inertia e sem biblioteca de rotas: o endereço vive no
   fragmento (`#/mapa`, `#/recibo/reg-013`) e um `switch` decide a tela. São nove
   telas; uma biblioteca de roteamento aqui pesaria mais que o aplicativo.

   O estado também é deliberadamente simples — o que o fiscal registra fica na
   memória da aba. É protótipo: não há servidor do outro lado, e fingir que há
   seria esconder do dono exatamente o que ainda falta construir.
   ============================================================================ */

export type NovoRegistro = {
    status: Situacao;
    ocorrencias: string[];
    relato: string;
    fotos: number;
    ambulante: string | null;
    retornoBr: string | null;
    lat: number;
    lng: number;
    endereco: string;
    regiao: string;
};

type Contexto = {
    registros: Registro[];
    pendentes: number;
    tema: 'claro' | 'escuro';
    alternarTema: () => void;
    registrar: (novo: NovoRegistro) => string;
    anexarDocumento: (id: string, documento: string, nome: string | null) => void;
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

const temaInicial = (): 'claro' | 'escuro' =>
    window.matchMedia('(prefers-color-scheme: dark)').matches ? 'escuro' : 'claro';

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

        setRegistros((atuais) => [
            {
                id,
                protocolo: `FA20260826${String(atuais.length + 1).padStart(3, '0')}`,
                dataBr: HOJE_BR,
                hora: horaAgora(),
                regiao: novo.regiao,
                endereco: novo.endereco,
                lat: novo.lat,
                lng: novo.lng,
                status: novo.status,
                ocorrencias: novo.ocorrencias,
                relato: novo.relato,
                fotos: novo.fotos,
                ambulante: novo.ambulante,
                retornoBr: novo.retornoBr,
                envio: 'pendente',
                documento: null,
            },
            ...atuais,
        ]);

        return id;
    }, []);

    const anexarDocumento = useCallback((id: string, documento: string, nome: string | null) => {
        setRegistros((atuais) =>
            atuais.map((r) => (r.id === id ? { ...r, documento, ambulante: nome ?? r.ambulante } : r)),
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
            alternarTema: () => setTema((t) => (t === 'claro' ? 'escuro' : 'claro')),
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
                    aoEntrar={() => {
                        setEntrou(true);
                        irPara('inicio');
                    }}
                />
            </ContextoApp.Provider>
        );
    }

    const abaAtiva: Aba = (
        ['inicio', 'mapa', 'registros', 'calor', 'sincronizacao'].includes(tela) ? tela : 'inicio'
    ) as Aba;

    const comBarra = abaAtiva === tela;

    return (
        <ContextoApp.Provider value={contexto}>
            <FaixaPrototipo />
            <div className="pw-com-faixa">
                {tela === 'inicio' && <TelaInicio />}
                {tela === 'mapa' && <TelaMapa regiaoFoco={parametro} />}
                {tela === 'registrar' && <TelaRegistroRapido ambulanteId={parametro} />}
                {tela === 'recibo' && <TelaRecibo id={parametro} />}
                {tela === 'escalada' && <TelaEscalada id={parametro} />}
                {tela === 'registros' && <TelaRegistros />}
                {tela === 'calor' && <TelaCalor />}
                {tela === 'sincronizacao' && <TelaSincronizacao />}
                {tela === 'perfil' && <TelaPerfil />}
            </div>
            {comBarra && (
                <BarraInferior ativa={abaAtiva} aoTrocar={(aba) => irPara(aba)} pendentes={pendentes} />
            )}
        </ContextoApp.Provider>
    );
}

function FaixaPrototipo() {
    return <div className="pw-faixa-prototipo">Protótipo · dados fictícios</div>;
}

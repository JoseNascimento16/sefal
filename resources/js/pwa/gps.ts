import { useCallback, useEffect, useRef, useState } from 'react';

/* ============================================================================
   O GPS do registro — a coordenada MEDIDA, ou o motivo de não haver uma
   ----------------------------------------------------------------------------
   O lugar é o dado central da fiscalização: o alvo não tem endereço fixo. Até
   14/10/2026, quando o aparelho não liberava a localização, o registro saía
   com o centro do bairro no lugar dela — um ponto inventado que entrava no
   mapa, no calor e no pino do ambulante como se fosse medido. Agora:

   • o aparelho vigia a posição enquanto a tela está aberta e guarda a MELHOR
     leitura (a de menor margem), porque a primeira costuma vir com centenas de
     metros de erro e melhora em segundos;
   • o fiscal pode tocar no selo da precisão e medir de novo;
   • sem GPS, o registro vai SEM coordenada e com o MOTIVO, e a Retaguarda
     mostra a marca. Nenhum ponto é inventado.
   ============================================================================ */

/**
 * Por que o registro não tem coordenada. As chaves e as frases são as do
 * servidor (`config/prototipo_denuncias.php` → `motivos_sem_gps`); um teste
 * falha se as duas listas divergirem.
 */
export const MOTIVOS_SEM_GPS = {
    negado: 'a localização do aparelho não foi liberada',
    indisponivel: 'o aparelho não encontrou sinal de GPS',
    tempo: 'o GPS não respondeu a tempo',
    'sem-suporte': 'o navegador do aparelho não tem GPS',
} as const;

export type MotivoSemGps = keyof typeof MOTIVOS_SEM_GPS;

/** A coordenada de um registro, em texto — ou a marca de que não houve GPS. */
export const coordenadaEmTexto = (r: { lat: number | null; lng: number | null }): string =>
    r.lat !== null && r.lng !== null ? `${r.lat.toFixed(5)}, ${r.lng.toFixed(5)}` : 'sem GPS';

export type Leitura ={ lat: number; lng: number; precisao: number };

export type Gps =
    | { estado: 'medindo'; leitura: Leitura | null; motivo: null }
    | { estado: 'pronto'; leitura: Leitura; motivo: null }
    | { estado: 'falhou'; leitura: null; motivo: MotivoSemGps };

/** Margem que já basta: abaixo dela o aparelho para de vigiar e poupa bateria. */
const PRECISAO_BOA_M = 20;

/** Quanto tempo esperar a primeira leitura antes de declarar "não respondeu". */
const ESPERA_MAXIMA_MS = 25_000;

/** Quanto tempo seguir refinando depois da primeira leitura. */
const REFINO_MS = 15_000;

const motivoDoErro = (codigo: number): MotivoSemGps =>
    codigo === 1 ? 'negado' : codigo === 3 ? 'tempo' : 'indisponivel';

/**
 * Mede a posição do aparelho enquanto `ativo`. Devolve o estado e
 * `medirDeNovo`, para o fiscal pedir outra medição (depois de liberar a
 * permissão, ou se a margem estiver ruim).
 */
export function useGps(ativo = true): Gps & { medirDeNovo: () => void } {
    const [gps, setGps] = useState<Gps>({ estado: 'medindo', leitura: null, motivo: null });
    const [rodada, setRodada] = useState(0);
    const melhor = useRef<Leitura | null>(null);

    useEffect(() => {
        if (!ativo) {
            return;
        }

        melhor.current = null;
        setGps({ estado: 'medindo', leitura: null, motivo: null });

        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            setGps({ estado: 'falhou', leitura: null, motivo: 'sem-suporte' });

            return;
        }

        let vigia: number | null = null;
        let prazoDoRefino: number | null = null;

        const parar = () => {
            if (vigia !== null) {
                navigator.geolocation.clearWatch(vigia);
                vigia = null;
            }
        };

        const fechar = () => {
            parar();

            if (melhor.current) {
                setGps({ estado: 'pronto', leitura: melhor.current, motivo: null });
            }
        };

        vigia = navigator.geolocation.watchPosition(
            (p) => {
                const leitura = {
                    lat: p.coords.latitude,
                    lng: p.coords.longitude,
                    precisao: Math.max(1, Math.round(p.coords.accuracy)),
                };

                if (melhor.current === null || leitura.precisao <= melhor.current.precisao) {
                    melhor.current = leitura;
                }

                /* Já serve para gravar; segue refinando por mais um tempo. */
                setGps({ estado: 'pronto', leitura: melhor.current, motivo: null });

                if (melhor.current.precisao <= PRECISAO_BOA_M) {
                    parar();
                } else if (prazoDoRefino === null) {
                    prazoDoRefino = window.setTimeout(fechar, REFINO_MS);
                }
            },
            (erro) => {
                /* Um erro depois de já haver leitura não apaga a leitura. */
                if (melhor.current) {
                    fechar();

                    return;
                }

                parar();
                setGps({ estado: 'falhou', leitura: null, motivo: motivoDoErro(erro.code) });
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: ESPERA_MAXIMA_MS },
        );

        const prazo = window.setTimeout(() => {
            if (melhor.current === null) {
                parar();
                setGps({ estado: 'falhou', leitura: null, motivo: 'tempo' });
            }
        }, ESPERA_MAXIMA_MS + 1_000);

        return () => {
            parar();
            window.clearTimeout(prazo);

            if (prazoDoRefino !== null) {
                window.clearTimeout(prazoDoRefino);
            }
        };
    }, [ativo, rodada]);

    const medirDeNovo = useCallback(() => setRodada((r) => r + 1), []);

    return { ...gps, medirDeNovo };
}

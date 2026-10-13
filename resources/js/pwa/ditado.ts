import { useCallback, useEffect, useRef, useState } from 'react';

/* ============================================================================
   O DITADO do relato — reconhecimento de voz do próprio navegador
   ----------------------------------------------------------------------------
   De pé na calçada, ditar é mais rápido que digitar. O Chrome do Android traz o
   reconhecimento de voz (Web Speech API) em português; ele precisa de SINAL (a
   transcrição é feita no serviço do Google) e da permissão do microfone. Sem
   um ou outro, o botão diz o motivo e o fiscal escreve — o relato continua
   opcional.
   ============================================================================ */

type Resultado = { isFinal: boolean; 0: { transcript: string } };

type Reconhecimento = {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    onresult: ((e: { resultIndex: number; results: ArrayLike<Resultado> }) => void) | null;
    onerror: ((e: { error: string }) => void) | null;
    onend: (() => void) | null;
    start: () => void;
    stop: () => void;
};

type ConstrutorDeReconhecimento = new () => Reconhecimento;

const construtor = (): ConstrutorDeReconhecimento | null => {
    const w = window as unknown as {
        SpeechRecognition?: ConstrutorDeReconhecimento;
        webkitSpeechRecognition?: ConstrutorDeReconhecimento;
    };

    return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
};

const FRASE_DO_ERRO: Record<string, string> = {
    'not-allowed': 'O microfone está bloqueado. Libere o microfone para o aplicativo nas permissões do Chrome.',
    'service-not-allowed': 'O microfone está bloqueado. Libere o microfone para o aplicativo nas permissões do Chrome.',
    network: 'O ditado precisa de sinal. Sem internet, escreva o relato.',
    'no-speech': 'Não ouvi nada. Toque em Ditar e fale perto do aparelho.',
    'audio-capture': 'O aparelho não encontrou o microfone.',
};

/**
 * Liga o ditado: cada frase reconhecida vai para `aoReconhecer`; o que ainda
 * está sendo ouvido sai em `parcial`, para o fiscal ver que o aparelho escuta.
 */
export function useDitado(aoReconhecer: (frase: string) => void) {
    const [ouvindo, setOuvindo] = useState(false);
    const [parcial, setParcial] = useState('');
    const [erro, setErro] = useState<string | null>(null);
    const atual = useRef<Reconhecimento | null>(null);
    const callback = useRef(aoReconhecer);

    useEffect(() => {
        callback.current = aoReconhecer;
    }, [aoReconhecer]);

    const suportado = construtor() !== null;

    const parar = useCallback(() => {
        atual.current?.stop();
    }, []);

    const iniciar = useCallback(() => {
        const Construtor = construtor();

        if (!Construtor) {
            setErro('Este navegador não faz ditado. Use o Chrome do aparelho, ou escreva o relato.');

            return;
        }

        const r = new Construtor();
        r.lang = 'pt-BR';
        r.continuous = true;
        r.interimResults = true;

        r.onresult = (e) => {
            let ouvindoAgora = '';

            for (let i = e.resultIndex; i < e.results.length; i++) {
                const resultado = e.results[i];
                const texto = resultado[0].transcript.trim();

                if (resultado.isFinal) {
                    if (texto) {
                        callback.current(texto);
                    }
                } else {
                    ouvindoAgora += `${texto} `;
                }
            }

            setParcial(ouvindoAgora.trim());
        };
        r.onerror = (e) => setErro(FRASE_DO_ERRO[e.error] ?? 'O ditado parou. Toque em Ditar de novo, ou escreva o relato.');
        r.onend = () => {
            setOuvindo(false);
            setParcial('');
            atual.current = null;
        };

        setErro(null);
        atual.current = r;

        try {
            r.start();
            setOuvindo(true);
        } catch {
            setErro('O ditado não começou. Toque em Ditar de novo.');
        }
    }, []);

    /* Sair da tela desliga o microfone. */
    useEffect(() => () => atual.current?.stop(), []);

    return { suportado, ouvindo, parcial, erro, iniciar, parar };
}

import { useEffect, useState } from 'react';
import { primeiroNome, saudacaoAgora } from '@/lib/saudacao';
import { cn } from '@/lib/utils';

/**
 * Splash de boas-vindas da Retaguarda — aparece UMA vez, logo depois do login, e
 * some sozinho com fade suave.
 *
 * O desenho é a variante aprovada pelo dono: "malha de ruas e pins". O fundo é a
 * cidade vista de cima — ruas curvas em azul sobre navy, manchas de calor onde a
 * fiscalização se concentra e pins pulsando, um deles laranja (a cor de
 * incidência: o ponto irregular). À esquerda, o bloco editorial com a saudação
 * pelo horário e o nome de quem entrou; no canto superior direito, a marca.
 *
 * Não bloqueia ninguém: o overlay é `pointer-events: none` e se desmonta ao fim
 * da transição — quem já quer trabalhar clica através dele. O estilo vive em
 * `resources/css/retaguarda.css` (`.bv-*`), com os @keyframes no NÍVEL RAIZ do
 * arquivo: aninhado em @layer/@media, o minificador da build de produção
 * (lightningcss) os descarta em silêncio.
 */

/** Tempo com o splash em tela antes de começar a sumir, e a duração do fade (casa com o CSS). */
const TEMPO_EM_TELA_MS = 3200;
const TEMPO_DE_FADE_MS = 900;

/**
 * A malha de ruas — decoração, não é mapa de nada. São curvas de Bézier soltas
 * numa tela de 1600×900; as verticais e as horizontais se cruzam para dar a
 * leitura de quadra, e a espessura varia para umas parecerem avenida e outras
 * travessa.
 */
const RUAS: { d: string; largura: number; forte?: boolean }[] = [
    { d: 'M -50 210 C 260 150 520 250 820 190 S 1300 120 1660 200', largura: 2.5, forte: true },
    { d: 'M -50 430 C 300 380 560 470 900 420 S 1340 350 1660 430', largura: 3, forte: true },
    { d: 'M -50 640 C 240 600 540 690 860 630 S 1320 570 1660 650', largura: 2.5 },
    { d: 'M -50 800 C 320 770 620 840 960 790 S 1380 740 1660 810', largura: 2 },
    { d: 'M 180 -50 C 240 220 150 460 250 700 S 300 880 260 950', largura: 2 },
    { d: 'M 520 -50 C 600 240 480 480 580 720 S 640 880 600 950', largura: 2.5, forte: true },
    { d: 'M 900 -50 C 960 200 860 460 980 700 S 1020 880 990 950', largura: 2 },
    { d: 'M 1280 -50 C 1340 230 1240 470 1360 710 S 1400 880 1370 950', largura: 2.5 },
];

/**
 * Os pins. `atraso` escalona o pulso para eles não baterem juntos (o conjunto
 * respirando parece vivo; em uníssono parece defeito). O laranja é UM só, e de
 * propósito: laranja é a cor de INCIDÊNCIA no sistema — o ponto irregular. Dois
 * ou três laranjas fariam o splash dizer que a cidade está toda irregular.
 *
 * `fora` sai de cena nas telas estreitas, para os pins não empilharem sobre o
 * bloco editorial.
 */
const PINS: {
    x: number;
    y: number;
    atraso: string;
    incidencia?: boolean;
    fora?: boolean;
}[] = [
    { x: 1010, y: 300, atraso: '0s' },
    { x: 1330, y: 520, atraso: '0.9s', incidencia: true },
    { x: 1150, y: 690, atraso: '1.7s' },
    { x: 720, y: 420, atraso: '2.4s', fora: true },
];

export default function OverlayBoasVindas({ nome }: { nome: string }) {
    // 'entrando' → 'saindo' (dispara o fade do CSS) → desmontado.
    const [fase, setFase] = useState<'entrando' | 'saindo'>('entrando');
    const [montado, setMontado] = useState(true);

    useEffect(() => {
        const fade = setTimeout(() => setFase('saindo'), TEMPO_EM_TELA_MS);
        const fim = setTimeout(
            () => setMontado(false),
            TEMPO_EM_TELA_MS + TEMPO_DE_FADE_MS,
        );

        return () => {
            clearTimeout(fade);
            clearTimeout(fim);
        };
    }, []);

    /*
     * A saudação e o primeiro nome vêm da FONTE ÚNICA (`@/lib/saudacao`), a mesma
     * que a tela Início usa. As duas cumprimentam quem entra ao mesmo tempo, na
     * mesma entrada: com uma cópia da regra em cada uma, elas discordariam — era o
     * caso às 3h da manhã, quando o Início dizia "Bom dia".
     */
    const saudacao = saudacaoAgora();
    const tratamento = primeiroNome(nome);

    if (!montado) return null;

    return (
        // As classes condicionais vão por `cn`, e não por texto de gabarito: o
        // formatador do projeto normaliza o conteúdo de `className` e come o espaço
        // de início, então `${… ? ' saindo' : ''}` sairia grudado
        // ("bv-overlaysaindo") — o splash nunca sumiria, e nada acusaria.
        <div
            className={cn('bv-overlay', fase === 'saindo' && 'saindo')}
            aria-hidden="true"
        >
            <svg
                className="bv-mapa"
                viewBox="0 0 1600 900"
                preserveAspectRatio="xMidYMid slice"
            >
                {/* Manchas de calor: onde a fiscalização se concentra. Sutis de
                    propósito — quem tem de ser lido é o texto, não o fundo. */}
                <defs>
                    <radialGradient id="bv-calor">
                        <stop offset="0%" stopColor="#0066b2" stopOpacity=".38" />
                        <stop offset="100%" stopColor="#0066b2" stopOpacity="0" />
                    </radialGradient>
                    <radialGradient id="bv-calor-quente">
                        <stop offset="0%" stopColor="#ff9a4d" stopOpacity=".18" />
                        <stop offset="100%" stopColor="#ff9a4d" stopOpacity="0" />
                    </radialGradient>
                </defs>

                <circle cx="1180" cy="380" r="300" fill="url(#bv-calor)" />
                <circle cx="820" cy="720" r="260" fill="url(#bv-calor)" />
                <circle cx="1360" cy="520" r="190" fill="url(#bv-calor-quente)" />

                {RUAS.map((rua) => (
                    <path
                        key={rua.d}
                        className={cn('bv-rua', rua.forte && 'forte')}
                        d={rua.d}
                        strokeWidth={rua.largura}
                    />
                ))}

                {PINS.map((pin) => (
                    <g
                        key={`${pin.x}-${pin.y}`}
                        className={cn(
                            'bv-pin',
                            pin.incidencia && 'incidencia',
                            pin.fora && 'recolhivel',
                        )}
                        style={{ animationDelay: pin.atraso }}
                    >
                        {/* O anel que expande: é ele que dá a leitura de "sinal
                            saindo do ponto". */}
                        <circle
                            className="bv-pin-anel"
                            cx={pin.x}
                            cy={pin.y}
                            r="14"
                            style={{ animationDelay: pin.atraso }}
                        />
                        <circle
                            className="bv-pin-nucleo"
                            cx={pin.x}
                            cy={pin.y}
                            r="7"
                        />
                    </g>
                ))}
            </svg>

            {/* Véu: escurece a esquerda, que é onde mora o texto, e deixa a
                malha respirar à direita, onde ficam os pins. */}
            <div className="bv-veu" />

            <div className="bv-marca">
                <img src="/images/marca/brasao-salvador-branco.svg" alt="" />
                <div>
                    <div className="bv-marca-nome">SEFAL</div>
                    <div className="bv-marca-orgao">SEMOP · SALVADOR</div>
                </div>
            </div>

            <div className="bv-bloco">
                <div className="bv-kicker">FISCALIZAÇÃO DE AMBULANTES</div>
                <div className="bv-saudacao">
                    {saudacao}
                    {tratamento !== '' ? ',' : '!'}
                    {tratamento !== '' && (
                        <>
                            <br />
                            {tratamento}
                        </>
                    )}
                </div>
                <div className="bv-regua" />
                <div className="bv-frase">
                    A rua já está no mapa. Bom plantão.
                </div>
            </div>
        </div>
    );
}

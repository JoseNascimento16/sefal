import { Head } from '@inertiajs/react';
import { Compass, Hourglass } from 'lucide-react';

/**
 * A tela de uma funcionalidade que AINDA NÃO EXISTE — e que, mesmo assim, abre.
 *
 * Uma tela só: o que muda entre Cadastro de Operação, Fiscalizações, Mapa ao Vivo e
 * Mapa de Calor é o TEXTO, e ele vem do servidor
 * ({@see TelasEmPreparacaoController}). Quatro páginas iguais com frases diferentes
 * seriam quatro lugares para consertar o dia em que o desenho da espera mudasse.
 *
 * Ela usa a casca normal e o cabeçalho editorial de sempre — a mesma sobrancelha,
 * o mesmo título grande. Quem chega aqui não caiu num lugar estranho: caiu na tela
 * certa, que ainda não tem conteúdo. A espera é dita DENTRO da tela, com o que ela
 * vai fazer e em que fase chega, em vez de virar um item morto no menu.
 */

/** As duas variantes de corpo — ver o `variante` no catálogo do controller. */
type Variante = 'mapa' | 'cartao';

export default function EmPreparacao({
    secao,
    titulo,
    subtitulo,
    frase,
    variante,
    fase,
    itens,
}: {
    secao: string;
    titulo: string;
    subtitulo: string;
    /** A frase de uma linha: o que esta tela vai ser. */
    frase: string;
    variante: Variante;
    /** Quando chega, no plano ("Fase 2"). */
    fase: string;
    /** O que ela vai permitir fazer — curto, três itens. */
    itens: string[];
}) {
    return (
        <>
            <Head title={titulo} />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">{secao}</p>
                    <h1>{titulo}</h1>
                    <p>{subtitulo}</p>
                </div>
            </div>

            {/* As telas de MAPA se explicam mostrando o que vão mostrar: a cidade,
                no mesmo navy com malha de ruas do resto do sistema. As de lista
                recebem o aviso sóbrio — desenhar um mapa decorativo numa tela de
                lista prometeria a coisa errada. */}
            {variante === 'mapa' ? (
                <div className="prep-cidade">
                    <svg
                        className="prep-malha"
                        viewBox="0 0 1200 420"
                        preserveAspectRatio="xMidYMid slice"
                        aria-hidden="true"
                    >
                        <defs>
                            <radialGradient id="prep-calor">
                                <stop
                                    offset="0%"
                                    stopColor="#0066b2"
                                    stopOpacity=".42"
                                />
                                <stop
                                    offset="100%"
                                    stopColor="#0066b2"
                                    stopOpacity="0"
                                />
                            </radialGradient>
                        </defs>

                        <circle cx="880" cy="150" r="220" fill="url(#prep-calor)" />
                        <circle cx="330" cy="330" r="180" fill="url(#prep-calor)" />

                        <g fill="none" strokeWidth="2">
                            <path
                                stroke="#26537e"
                                d="M-40 110 C 260 80, 540 150, 820 105 S 1180 60, 1240 100"
                            />
                            <path
                                stroke="#26537e"
                                d="M-40 250 C 300 220, 600 290, 900 240 S 1200 200, 1240 245"
                            />
                            <path
                                stroke="#1b3a5e"
                                d="M-40 360 C 280 335, 620 400, 940 350 L 1240 320"
                            />
                            <path
                                stroke="#1b3a5e"
                                d="M240 -20 C 265 130, 215 280, 260 440"
                            />
                            <path
                                stroke="#26537e"
                                d="M620 -20 C 590 120, 660 270, 615 440"
                            />
                            <path
                                stroke="#1b3a5e"
                                d="M980 -20 C 1010 140, 950 300, 1000 440"
                            />
                        </g>

                        <circle cx="880" cy="150" r="7" fill="#ff9a4d" />
                        <circle cx="330" cy="330" r="6" fill="#4d9fdc" />
                        <circle cx="615" cy="240" r="6" fill="#4d9fdc" />
                    </svg>

                    <div className="prep-cidade-texto">
                        <p className="prep-fase">
                            <Compass size={14} aria-hidden />
                            {fase}
                        </p>
                        <p className="prep-frase">{frase}</p>
                        <ul className="prep-lista">
                            {itens.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    </div>
                </div>
            ) : (
                <div className="card-premium prep-cartao">
                    <p className="prep-fase clara">
                        <Hourglass size={14} aria-hidden />
                        {fase}
                    </p>
                    <p className="prep-frase">{frase}</p>
                    <ul className="prep-lista">
                        {itens.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                    <p className="form-ajuda prep-nota">
                        Enquanto isso, o que os fiscais registram em rua fica guardado
                        pelo aplicativo — nada se perde à espera desta tela.
                    </p>
                </div>
            )}
        </>
    );
}

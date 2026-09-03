import { Eye, EyeOff } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * A camada de leitura das telas de mapa — e o botão que a recolhe.
 *
 * ── Por que este componente existe ──────────────────────────────────────────
 * Nas telas de mapa a informação flutua SOBRE a cidade (é o partido "imersão
 * no mapa"). Em tela larga isso funciona: sobra cidade entre os painéis. Em
 * tela estreita não sobra — abaixo de 1100px os painéis viram UMA coluna que
 * rola, e essa coluna precisa receber a roda do mouse para rolar; ou seja, ela
 * é uma superfície opaca ao clique justamente onde o mapa deveria responder.
 * O resultado, relatado pelo dono: em tela pequena não se consegue interagir
 * com o mapa.
 *
 * A saída não é encolher os painéis (informação some) nem devolvê-los para uma
 * coluna lateral (aí não é mais mapa imersivo): é dar ao usuário o comando de
 * RECOLHER a interface e ficar só com a cidade — e trazê-la de volta quando
 * quiser ler. Mapa em tela pequena tem dois usos que competem pelo mesmo
 * espaço, e quem escolhe qual vale agora é quem está olhando.
 *
 * ── Por que compartilhado, e não copiado nas duas telas ─────────────────────
 * Mapa ao Vivo e Mapa de Calor têm a MESMA camada e o mesmo problema. Regra
 * duplicada em dois arquivos diverge (é lei do projeto): um lado ganharia o
 * ajuste e o outro ficaria para trás, sem nada acusando. Aqui a decisão mora
 * num lugar só; cada tela diz apenas o que são os painéis e o que é a legenda.
 */
export function CamadaDoMapa({
    children,
    legenda,
    rotuloDoConteudo = 'os painéis',
}: {
    /** Os painéis que flutuam sobre a cidade (cabeçalho, filtros, colunas). */
    children: ReactNode;
    /** A legenda do pé — fica ao lado do botão, e some com o resto. */
    legenda?: ReactNode;
    /** Como o botão chama o que ele recolhe, para o rótulo fazer sentido. */
    rotuloDoConteudo?: string;
}) {
    /**
     * Nasce VISÍVEL de propósito: a tela tem de abrir dizendo o que sabe. Quem
     * precisa do mapa limpo pede — o contrário (abrir vazio e exigir um clique
     * para ver os números) esconderia a informação de quem nem sabe que ela
     * existe.
     */
    const [visivel, setVisivel] = useState(true);

    return (
        <div className={cn('rt-mapa-camada', !visivel && 'oculta')}>
            {visivel && children}

            {/* O pé fica SEMPRE montado, e o botão sempre no mesmo canto: em
                tela pequena, controle que muda de lugar conforme o estado faz o
                usuário procurar. `margin-top: auto` (no CSS) prende a faixa no
                rodapé mesmo quando os painéis saem e o miolo desaparece. */}
            <div className="rt-mapa-pe">
                <button
                    type="button"
                    className="rt-mapa-alternar"
                    onClick={() => setVisivel((v) => !v)}
                    aria-expanded={visivel}
                    title={
                        visivel
                            ? `Recolher ${rotuloDoConteudo} e liberar o mapa`
                            : `Mostrar ${rotuloDoConteudo} de novo`
                    }
                >
                    {visivel ? (
                        <EyeOff size={15} aria-hidden />
                    ) : (
                        <Eye size={15} aria-hidden />
                    )}
                    <span>{visivel ? 'Só o mapa' : 'Mostrar painéis'}</span>
                </button>

                {visivel && legenda}
            </div>
        </div>
    );
}

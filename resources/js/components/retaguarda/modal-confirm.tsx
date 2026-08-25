import { AlertTriangle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef } from 'react';
import { Spinner } from '@/components/retaguarda/acao';
import { useScrollLock } from '@/hooks/use-scroll-lock';
import { cn } from '@/lib/utils';

/**
 * Confirmação de ação — o ÚNICO jeito de perguntar "tem certeza?" na Retaguarda.
 *
 * `window.confirm`/`alert`/`prompt` são proibidos: têm a cara do navegador (não a
 * do sistema), travam a página inteira e não dizem o que vai acontecer.
 *
 * O pai monta e desmonta: renderize só quando for perguntar. Enquanto está no ar,
 * a rolagem da página de trás fica travada — a decisão é o único assunto.
 *
 * @example
 *   {confirmando && (
 *       <ModalConfirm
 *           titulo="Cancelar a fiscalização?"
 *           mensagem="O registro fica no histórico como cancelado."
 *           rotuloConfirmar="Cancelar fiscalização"
 *           destrutiva
 *           processando={enviando === 'cancelar'}
 *           onCancelar={() => setConfirmando(false)}
 *           onConfirmar={() => enviar('cancelar', url)}
 *       />
 *   )}
 */
export function ModalConfirm({
    titulo,
    mensagem,
    rotuloConfirmar,
    rotuloCancelar = 'Voltar',
    iconeConfirmar,
    destrutiva = false,
    processando = false,
    onCancelar,
    onConfirmar,
}: {
    titulo: string;
    mensagem: ReactNode;
    rotuloConfirmar: string;
    rotuloCancelar?: string;
    iconeConfirmar?: ReactNode;
    /** Ação que apaga/encerra algo: o botão de confirmar fica vermelho. */
    destrutiva?: boolean;
    /** Ação em voo: desabilita os dois botões e mostra o spinner. */
    processando?: boolean;
    onCancelar: () => void;
    onConfirmar: () => void;
}) {
    const voltar = useRef<HTMLButtonElement>(null);

    useScrollLock(true);

    // O foco nasce no "Voltar", e não no botão que confirma: quem aperta Enter
    // sem ler acaba de sair, não de apagar algo.
    useEffect(() => {
        voltar.current?.focus();
    }, []);

    // Esc cancela — menos no meio de uma ação em voo, para a pessoa não fechar a
    // camada sem saber se aquilo foi ou não gravado.
    useEffect(() => {
        const tecla = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && !processando) {
                onCancelar();
            }
        };

        document.addEventListener('keydown', tecla);

        return () => document.removeEventListener('keydown', tecla);
    }, [processando, onCancelar]);

    return (
        <div
            className="sobreposicao"
            role="presentation"
            // Clicar fora cancela — mas não no meio de uma ação em voo, senão a
            // pessoa fecha a camada sem saber se aquilo foi ou não gravado.
            onClick={processando ? undefined : onCancelar}
        >
            <div
                className="card-premium"
                style={{ width: '100%', maxWidth: 460 }}
                role="dialog"
                aria-modal="true"
                aria-label={titulo}
                onClick={(e) => e.stopPropagation()}
            >
                <h2
                    className="sobreposicao-titulo"
                    style={{
                        color: destrutiva
                            ? 'var(--sm-perigo)'
                            : 'var(--sm-texto)',
                    }}
                >
                    <AlertTriangle size={20} aria-hidden /> {titulo}
                </h2>

                <p className="sobreposicao-texto">{mensagem}</p>

                <div className="sobreposicao-acoes">
                    <button
                        type="button"
                        ref={voltar}
                        className="btn btn-secondary btn-sm"
                        onClick={onCancelar}
                        disabled={processando}
                    >
                        {rotuloCancelar}
                    </button>

                    <button
                        type="button"
                        className={cn(
                            'btn btn-sm',
                            destrutiva ? 'btn-perigo' : 'btn-primary',
                        )}
                        onClick={onConfirmar}
                        disabled={processando}
                        aria-busy={processando || undefined}
                    >
                        {processando ? (
                            <>
                                <Spinner /> Enviando…
                            </>
                        ) : (
                            <>
                                {iconeConfirmar} {rotuloConfirmar}
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}

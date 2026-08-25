import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Superfície onde os avisos aparecem. Quem DECIDE mostrar um aviso é o
 * `useFlashToast`, chamado uma vez por layout — este componente só desenha.
 *
 * O `Toaster` fica fora da árvore da página (montado no `app.tsx`), onde não há
 * contexto de página para ler: por isso a leitura do recado não pode morar aqui.
 */
function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };

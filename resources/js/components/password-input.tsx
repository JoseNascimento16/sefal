import { Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Campo de senha do Design System, com o olho que revela o que foi digitado.
 *
 * O olho existe porque senha digitada às cegas é a causa nº 1 de "minha senha não
 * funciona" — e, num aparelho em rua, com teclado pequeno, mais ainda. O botão
 * fica fora da ordem de tabulação: quem navega pelo teclado passa direto para o
 * próximo campo.
 */
export default function PasswordInput({
    className = '',
    ref,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & { ref?: Ref<HTMLInputElement> }) {
    const [revelada, setRevelada] = useState(false);

    return (
        <div style={{ position: 'relative' }}>
            <input
                type={revelada ? 'text' : 'password'}
                className={cn('form-control', className)}
                style={{ paddingRight: 44 }}
                ref={ref}
                {...props}
            />
            <button
                type="button"
                onClick={() => setRevelada((v) => !v)}
                aria-label={revelada ? 'Esconder a senha' : 'Mostrar a senha'}
                title={revelada ? 'Esconder a senha' : 'Mostrar a senha'}
                tabIndex={-1}
                style={{
                    position: 'absolute',
                    right: 4,
                    top: '50%',
                    transform: 'translateY(-50%)',
                    display: 'grid',
                    placeItems: 'center',
                    width: 34,
                    height: 34,
                    border: 'none',
                    background: 'transparent',
                    color: 'var(--sm-texto-fraco)',
                    cursor: 'pointer',
                }}
            >
                {revelada ? (
                    <EyeOff size={16} aria-hidden />
                ) : (
                    <Eye size={16} aria-hidden />
                )}
            </button>
        </div>
    );
}

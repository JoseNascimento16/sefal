import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Escolha do tema: claro, escuro ou o que o aparelho estiver usando.
 *
 * "Sistema" é a opção padrão e existe por um motivo prático: fiscal em rua com o
 * aparelho no modo escuro à noite não deveria ter de trocar nada aqui.
 */
const OPCOES: { valor: Appearance; icone: LucideIcon; rotulo: string }[] = [
    { valor: 'light', icone: Sun, rotulo: 'Claro' },
    { valor: 'dark', icone: Moon, rotulo: 'Escuro' },
    { valor: 'system', icone: Monitor, rotulo: 'Do aparelho' },
];

export default function AppearanceToggleTab() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="group"
            aria-label="Tema do sistema"
            style={{
                display: 'inline-flex',
                gap: 6,
                padding: 5,
                borderRadius: 'var(--sm-raio-md)',
                background: 'var(--sm-muted)',
            }}
        >
            {OPCOES.map(({ valor, icone: Icone, rotulo }) => {
                const ativo = appearance === valor;

                return (
                    <button
                        key={valor}
                        type="button"
                        onClick={() => updateAppearance(valor)}
                        aria-pressed={ativo}
                        className={`btn btn-sm ${ativo ? 'btn-primary' : 'btn-secondary'}`}
                        style={
                            ativo ? undefined : { borderColor: 'transparent' }
                        }
                    >
                        <Icone size={15} aria-hidden />
                        {rotulo}
                    </button>
                );
            })}
        </div>
    );
}

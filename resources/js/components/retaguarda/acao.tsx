import { Loader2 } from 'lucide-react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';

/**
 * Spinner do Design System (ícone girando). A animação vem da classe global
 * `.spinner-acao` (keyframes em `resources/css/retaguarda.css`), então nenhuma
 * tela precisa declarar `<style>`.
 */
export function Spinner({ tamanho = 16 }: { tamanho?: number }) {
    return <Loader2 className="spinner-acao" size={tamanho} aria-hidden />;
}

type BotaoAcaoProps = Omit<
    ButtonHTMLAttributes<HTMLButtonElement>,
    'children'
> & {
    /** true enquanto ESTA ação está em voo → troca o ícone por spinner e desabilita. */
    carregando?: boolean;
    /** true se QUALQUER ação está em voo → desabilita (não dispara outra no meio). */
    ocupado?: boolean;
    /** Ícone do estado normal — um componente de ícone já montado. */
    icone?: ReactNode;
    /** Rótulo durante o carregamento ("Salvando…"). Sem ele, repete o children. */
    rotuloCarregando?: ReactNode;
    children: ReactNode;
};

/**
 * Botão de AÇÃO DE ESCRITA — já vem com a guarda de duplo clique: quando
 * `carregando`, mostra o spinner e se desabilita; quando `ocupado` (outra ação em
 * voo), também. É o padrão para QUALQUER botão que dispare algo que possa
 * demorar; nunca chame `router.post` num `<button>` cru.
 *
 * Não existe aviso em tela dizendo "não clique de novo": o sinal é o progresso.
 *
 * @example
 *   const { enviando, ocupado, enviar } = useEnvio();
 *   <BotaoAcao
 *       icone={<Check size={16} />}
 *       carregando={enviando === 'validar'}
 *       ocupado={ocupado}
 *       rotuloCarregando="Validando…"
 *       onClick={() => enviar('validar', url, dados)}
 *   >
 *       Validar cadastro
 *   </BotaoAcao>
 */
export function BotaoAcao({
    carregando = false,
    ocupado = false,
    icone,
    rotuloCarregando,
    disabled,
    children,
    className = 'btn btn-primary btn-sm',
    type = 'button',
    ...resto
}: BotaoAcaoProps) {
    return (
        <button
            type={type}
            className={className}
            disabled={disabled || ocupado || carregando}
            aria-busy={carregando || undefined}
            {...resto}
        >
            {carregando ? (
                <>
                    <Spinner /> {rotuloCarregando ?? children}
                </>
            ) : (
                <>
                    {icone} {children}
                </>
            )}
        </button>
    );
}

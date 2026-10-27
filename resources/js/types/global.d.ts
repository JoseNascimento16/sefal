import type { AcoesDaTela, Auth } from '@/types/auth';
import type { MenuSecao } from '@/types/navigation';
import type { Recado } from '@/types/ui';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            menu: MenuSecao[];
            acoes: AcoesDaTela | null;
            flash: Recado;
            /**
             * Painel a abrir ao chegar nesta tela (ou `null`). Serve a quem
             * digita o endereço de algo que hoje é painel sobreposto — ver
             * `HandleInertiaRequests::painel`.
             */
            painel: string | null;
            /** O Modo Gerente: pode ligar? está ligado (chaves no menu)? */
            modoGerente: { pode: boolean; ativo: boolean };
            /** A demanda que acabou de ser registrada (vale uma vez). */
            demandaRegistrada: { protocolo: string; canal: string; proximo: string } | null;
            [key: string]: unknown;
        };
    }
}

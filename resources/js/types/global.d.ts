import type { Auth } from '@/types/auth';
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
            flash: Recado;
            [key: string]: unknown;
        };
    }
}

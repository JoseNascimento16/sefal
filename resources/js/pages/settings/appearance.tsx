import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { edit as editarPerfil } from '@/routes/profile';

export default function Appearance() {
    return (
        <>
            <Head title="Aparência" />

            <div style={{ marginBottom: 20 }}>
                <h2 className="card-titulo">Aparência</h2>
                <p className="card-sub">
                    Como o sistema se apresenta neste navegador. A escolha vale
                    só para você.
                </p>
            </div>

            <AppearanceTabs />
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Meu Perfil',
            href: editarPerfil(),
        },
    ],
};

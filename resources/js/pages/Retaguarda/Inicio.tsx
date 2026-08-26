import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardCheck, LayoutGrid, Map, Store, UserRound } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { inicio } from '@/routes/retaguarda';

/**
 * Um atalho, como o servidor o entrega. `href` nulo = tela das próximas
 * entregas; atalho que esta pessoa não pode abrir não chega aqui.
 */
interface Atalho {
    chave: string;
    titulo: string;
    descricao: string;
    href: string | null;
}

/**
 * O ícone de cada atalho. É só isto que a tela decide sobre eles — quais existem,
 * o que dizem e para onde levam vem do servidor, que é quem sabe se a rota
 * existe e se a pessoa entra.
 */
const ICONES: Record<string, LucideIcon> = {
    perfil: UserRound,
    permissionarios: Store,
    fiscalizacoes: ClipboardCheck,
    areas: Map,
};

/** Bom dia / boa tarde / boa noite — pelo relógio de quem está olhando. */
function saudacao(): string {
    const hora = new Date().getHours();

    if (hora < 12) {
        return 'Bom dia';
    }

    return hora < 18 ? 'Boa tarde' : 'Boa noite';
}

/** Primeiro nome: "Bom dia, Maria" soa com gente; o nome completo, com cadastro. */
function primeiroNome(nome: string): string {
    return nome.trim().split(/\s+/)[0] ?? nome;
}

function Atalho({
    icone: Icone,
    titulo,
    descricao,
    href,
}: {
    icone: LucideIcon;
    titulo: string;
    descricao: string;
    href?: string;
}) {
    const conteudo = (
        <>
            <span className="rt-atalho-ico">
                <Icone size={22} aria-hidden />
            </span>
            <span>
                <span className="card-titulo">{titulo}</span>
                <span className="card-sub" style={{ display: 'block' }}>
                    {descricao}
                </span>
            </span>
        </>
    );

    // Atalho sem tela ainda não finge ser clicável: fica visível e esmaecido,
    // dizendo o que vem por aí. Botão que não leva a lugar nenhum é pior que
    // atalho ausente.
    if (!href) {
        return (
            <div
                className="card-premium rt-atalho em-breve"
                aria-disabled="true"
            >
                {conteudo}
                <span className="selo selo-neutro">Em construção</span>
            </div>
        );
    }

    return (
        <Link href={href} className="card-premium card-interativo rt-atalho">
            {conteudo}
        </Link>
    );
}

export default function Inicio({ atalhos }: { atalhos: Atalho[] }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Início" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Retaguarda · SEMOP</p>
                    <h1>
                        {saudacao()}
                        {auth.user ? `, ${primeiroNome(auth.user.name)}` : ''}.
                    </h1>
                    <p>
                        Fiscalização de permissionários — comerciantes
                        ambulantes de rua de Salvador.
                    </p>
                </div>

                {auth.user && (
                    <div style={{ textAlign: 'right' }}>
                        <p className="rt-usuario-matricula">
                            Matrícula {auth.user.login.toUpperCase()}
                        </p>
                        <p style={{ marginTop: 6 }}>
                            {auth.user.admin ? (
                                <span className="selo selo-ok">
                                    Administrador
                                </span>
                            ) : auth.user.setores.length > 0 ? (
                                auth.user.setores.map((setor) => (
                                    <span
                                        key={setor}
                                        className="selo selo-info"
                                        style={{ marginLeft: 6 }}
                                    >
                                        {setor}
                                    </span>
                                ))
                            ) : (
                                <span className="selo selo-aviso">
                                    Sem setor definido
                                </span>
                            )}
                        </p>
                    </div>
                )}
            </div>

            <div className="rt-grid-atalhos">
                {atalhos.map((atalho) => (
                    <Atalho
                        key={atalho.chave}
                        icone={ICONES[atalho.chave] ?? LayoutGrid}
                        titulo={atalho.titulo}
                        descricao={atalho.descricao}
                        href={atalho.href ?? undefined}
                    />
                ))}
            </div>
        </>
    );
}

Inicio.layout = {
    breadcrumbs: [
        {
            title: 'Início',
            href: inicio(),
        },
    ],
};

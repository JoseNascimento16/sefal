/**
 * O usuário como a tela o conhece — o recorte que o `HandleInertiaRequests`
 * compartilha. Não é o registro inteiro do banco: só o que a tela precisa.
 */
export type User = {
    id: number;
    name: string;
    email: string;
    /** Matrícula, na forma canônica (minúscula). Mostre em MAIÚSCULA. */
    login: string;
    /** Enxerga tudo — pela marca na conta ou pelo setor `administrador`. */
    admin: boolean;
    /** Setores (perfis de acesso) a que a pessoa pertence, por apelido. */
    setores: string[];
};

export type Auth = {
    /** `null` nas telas públicas e de acesso. */
    user: User | null;
};

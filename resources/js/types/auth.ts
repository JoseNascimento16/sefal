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

/**
 * O que a pessoa pode FAZER na tela que está aberta — ver
 * `HandleInertiaRequests::acoes()`.
 *
 * Serve para a tela não oferecer o que o servidor recusa. **Não é autorização:**
 * quem barra são as guardas, e esconder botão é só o conforto de descobrir a
 * recusa antes de preencher o formulário inteiro. Vem da mesma resposta que
 * barra, para os dois nunca discordarem.
 *
 * `null` quando não há o que responder: visitante, ou tela fora do Modo Gerente
 * (a inicial, a área da própria conta). A tela trata isso como "sem restrição
 * declarada" — ver o hook `useAcoes`.
 */
export type AcoesDaTela = {
    /** Abre a tela. */
    visivel: boolean;
    /** Grava alteração no que já existe. */
    habilitado: boolean;
    /** Abre só para olhar — derruba operar, incluir e excluir. */
    apenas_leitura: boolean;
    incluir: boolean;
    excluir: boolean;
};

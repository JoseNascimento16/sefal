import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { ModoGerentePermissoes } from '@/components/retaguarda/modo-gerente-permissoes';
import OverlayBoasVindas from '@/components/retaguarda/overlay-boas-vindas';
import { Sidebar } from '@/components/retaguarda/sidebar';
import { Topbar } from '@/components/retaguarda/topbar';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

/**
 * Casca de TODA tela autenticada da Retaguarda — o desenho "editorial curvo"
 * aprovado pelo dono: painel navy de canto curvado à esquerda, miolo claro à
 * direita, e o topo da tela pertencendo ao CABEÇALHO DA PÁGINA em vez de a uma
 * barra de sistema (ver `docs/regras-de-negocio/design-retaguarda.md`).
 *
 * A tela recebe a trilha de navegação por propriedade de layout — desenhada só
 * quando tem mais de um nível, porque com um nível ela repetiria o título:
 *
 *   MinhaTela.layout = { breadcrumbs: [{ title: 'Permissionários', href: url }] };
 */

/** Onde a preferência de menu retraído fica guardada, por navegador. */
const CHAVE_RETRACAO = 'sefal.menu.retraido';

/**
 * A largura abaixo da qual o painel estendido (292px) não cabe sem roubar o
 * trabalho: a doca passa a valer sozinha. O MESMO valor está no `retaguarda.css`
 * (o degrau de 1100px que deita a doca e aperta o miolo) — mudou aqui, mude lá.
 */
const LARGURA_DA_DOCA = '(max-width: 1100px)';

export default function RetaguardaLayout({
    breadcrumbs = [],
    imersivo = false,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    /**
     * A tela usa o corpo INTEIRO, sem o respiro do miolo — é o padrão imersivo
     * das telas de mapa (RN-07): o mapa é o fundo, sangrando de borda a borda, e
     * a leitura flutua sobre ele em painéis de vidro.
     *
     * O menu PERMANECE: o imersivo é sobre o conteúdo, não sobre a casca. O que
     * a marca faz é tirar o preenchimento do miolo, travar a rolagem da página
     * (quem rola é o mapa) e clarear o cluster de tema/avisos, que ficaria
     * invisível em texto escuro sobre a cidade à noite.
     */
    imersivo?: boolean;
    children: ReactNode;
}) {
    /*
     * Menu retraído (a doca). A preferência é da PESSOA e do navegador dela, então
     * mora no `localStorage` — não no servidor: é conforto de quem opera, não dado
     * do sistema, e uma coluna para isso faria a preferência viajar em toda
     * requisição sem ninguém precisar dela.
     *
     * Começa estendida e é corrigida no primeiro efeito, e não lida direto no
     * estado inicial, porque a renderização do servidor (SSR) não tem
     * `localStorage`: ler ali faria a marcação do servidor divergir da do
     * navegador e o React reclamaria da diferença.
     */
    const [retraida, setRetraida] = useState(false);

    useEffect(() => {
        try {
            setRetraida(localStorage.getItem(CHAVE_RETRACAO) === '1');
        } catch {
            // Navegador com armazenamento bloqueado: fica estendida, que é o
            // padrão. Preferência é conveniência — nunca motivo de tela quebrada.
        }
    }, []);

    function alternarRetracao() {
        setRetraida((atual) => {
            const novo = !atual;

            try {
                localStorage.setItem(CHAVE_RETRACAO, novo ? '1' : '0');
            } catch {
                // Idem: a escolha vale para esta sessão mesmo sem poder guardá-la.
            }

            return novo;
        });
    }

    /*
     * Abaixo de 1100px o painel estendido não cabe: são 292px de uma tela de 900,
     * e o que sobra para o trabalho fica estreito demais. A doca então vale
     * SOZINHA, independente da preferência — que não é apagada e volta a valer
     * quando a janela crescer.
     *
     * Uma decisão de desenho está embutida aqui: nesta faixa não existe painel
     * escondido atrás de um botão de menu. Antes existia (a barra deslizava por
     * cima com um véu), e isso deixava o menu a DOIS toques de distância e
     * inalcançável para quem não notasse o hambúrguer. A doca fica sempre à vista,
     * com ícone e rótulo — um menu, duas formas, nenhuma escondida.
     */
    const [estreito, setEstreito] = useState(false);

    useEffect(() => {
        const consulta = window.matchMedia(LARGURA_DA_DOCA);
        const aplicar = () => setEstreito(consulta.matches);

        aplicar();
        consulta.addEventListener('change', aplicar);

        return () => consulta.removeEventListener('change', aplicar);
    }, []);

    const emDoca = retraida || estreito;

    /*
     * O painel sobreposto aberto agora (hoje só o Modo Gerente), ou `null`.
     *
     * Ele mora AQUI, e não na barra lateral, porque a barra fecha sozinha no
     * celular ao clicar num item: com o estado lá dentro, o painel morreria junto
     * com a barra. E mora aqui, e não em cada tela, porque abre sobre qualquer
     * uma delas.
     */
    const [painelAberto, setPainelAberto] = useState<string | null>(null);

    // Os recados do servidor (`flash.sucesso` / `flash.erro`) aparecem aqui, uma
    // vez só, para toda a Retaguarda — nenhuma tela precisa se lembrar disso.
    useFlashToast();

    /*
     * O servidor também pode pedir que um painel abra: é o que acontece com quem
     * digita (ou tem nos favoritos) o endereço de algo que hoje é painel, e não
     * página — ver `HandleInertiaRequests::painel`.
     */
    const { painel, auth } = usePage().props;

    useEffect(() => {
        if (painel !== null) {
            setPainelAberto(painel);
        }
    }, [painel]);

    /*
     * Splash de boas-vindas: só na PRIMEIRA tela depois do login (o servidor manda
     * a marca e a consome na entrega — ver `HandleInertiaRequests::share`).
     *
     * A decisão é guardada no estado INICIAL, e não lida da prop a cada render: a
     * prop vira `false` na navegação seguinte, e ler direto dela faria o splash
     * desmontar no meio do fade se alguma requisição chegasse enquanto ele está no
     * ar. Quem o retira do ar é ele mesmo, no fim do próprio tempo.
     */
    const [boasVindas] = useState(() => auth.boas_vindas === true);

    /*
     * O tema NÃO é escrito aqui. Quem marca o <html> (classe `.dark` e atributo
     * `data-theme`, juntos) é o `applyTheme` do `use-appearance` — um só dono,
     * para os dois marcadores nunca discordarem e para valerem também nas telas
     * que ficam fora desta casca, como a de entrar.
     */

    return (
        <div
            className={cn(
                'rt-shell',
                emDoca && 'rt-shell-doca',
                imersivo && 'rt-shell-imersivo',
            )}
        >
            <Sidebar
                emDoca={emDoca}
                /* Quando a largura força a doca, alternar não tem para onde ir: o
                   botão de expandir sai de cena (é o CSS que o esconde, no mesmo
                   degrau de 1100px). */
                onAlternarRetracao={alternarRetracao}
                onAbrirPainel={setPainelAberto}
            />

            <div className="rt-principal">
                {/* Tema e avisos num cluster discreto no canto — o topo da tela
                    pertence ao cabeçalho da própria página. Ver o `Topbar`. */}
                <Topbar breadcrumbs={breadcrumbs} />

                <main className="rt-conteudo">{children}</main>
            </div>

            {painelAberto === 'modo-gerente' && (
                <ModoGerentePermissoes
                    onFechar={() => setPainelAberto(null)}
                />
            )}

            {/* Boas-vindas logo depois do login — some sozinho, com fade. */}
            {boasVindas && (
                <OverlayBoasVindas nome={auth.user?.name ?? ''} />
            )}
        </div>
    );
}

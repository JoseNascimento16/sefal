import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();

/**
 * O tema de quem NUNCA escolheu: CLARO.
 *
 * Já foi `system`, e o efeito era este: quem tem o sistema operacional no escuro
 * — o padrão de fábrica em boa parte dos aparelhos — abria a Retaguarda em navy
 * sem nunca ter pedido isso. Aqui o dia de trabalho é claro; o escuro é escolha,
 * e quem a fizer (ou pedir `sistema`) tem a escolha respeitada.
 *
 * ⚠️ Este valor tem três irmãos, e os quatro precisam concordar: o `HandleAppearance`
 * (cookie), o `$appearance ?? …` do `app.blade.php` (duas vezes: a classe do <html>
 * e o script de pré-pintura). Se um discordar, a primeira pintura é de um tema e a
 * segunda é de outro — o lampejo que a pré-pintura existe para evitar.
 */
const PADRAO: Appearance = 'light';

let currentAppearance: Appearance = PADRAO;

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return PADRAO;
    }

    return (localStorage.getItem('appearance') as Appearance) || PADRAO;
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

/**
 * Escreve o tema no <html>. É o ÚNICO lugar do cliente que faz isso.
 *
 * São dois marcadores, porque as duas linguagens de estilo do sistema leem
 * coisas diferentes: a classe `.dark` é o que as utilidades do Tailwind
 * enxergam, e `data-theme` é o que os tokens do Design System também aceitam
 * (e o que permite inverter o tema de um trecho isolado). Os dois são escritos
 * aqui, na mesma linha de raciocínio e a partir do mesmo booleano — se cada um
 * tivesse o seu escritor, um dia a tela apareceria com token escuro e utilidade
 * clara ao mesmo tempo (e nas telas fora da Retaguarda um deles nunca chegaria).
 */
const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);
    const html = document.documentElement;

    html.classList.toggle('dark', isDark);
    html.dataset.theme = isDark ? 'dark' : 'light';
    html.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

/**
 * O aparelho trocou de tema (anoiteceu no celular do fiscal, por exemplo).
 *
 * Repinta o <html> e AVISA quem está ouvindo: sem o aviso, o React continuaria
 * com o tema anterior na mão e a tela ficaria dizendo o contrário do que mostra
 * — o botão da barra superior, por exemplo, ainda oferecendo "usar o tema
 * escuro" com a tela já escura.
 */
const handleSystemThemeChange = (): void => {
    applyTheme(currentAppearance);
    notify();
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    /*
     * Ausência de escolha NÃO é gravada como escolha. Antes se escrevia `system` no
     * armazenamento na primeira visita, e aquilo congelava o padrão: mudá-lo depois
     * não alcançaria mais ninguém que já tivesse aberto o sistema uma vez. Sem
     * escrita, "não escolhi" continua sendo não escolhido, e o padrão é do código —
     * o mesmo que o servidor usa quando não há cookie.
     */
    currentAppearance = getStoredAppearance();
    applyTheme(currentAppearance);

    // Set up system theme change listener
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => PADRAO,
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', mode);

        // Store in cookie for SSR...
        setCookie('appearance', mode);

        applyTheme(mode);
        notify();
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}

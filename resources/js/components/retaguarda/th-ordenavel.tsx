import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ChevronUp,
} from 'lucide-react';
import type { ReactNode, ThHTMLAttributes } from 'react';
import { useCallback, useId, useMemo, useState } from 'react';
import { cn } from '@/lib/utils';

/*
|------------------------------------------------------------------------------
| Grade da Retaguarda — ordenação e paginação no CLIENTE
|------------------------------------------------------------------------------
|
| A ordem de montagem é sempre a mesma, e ela importa: filtra → ORDENA → pagina.
|
|   const filtradas = itens.filter(...);                  // busca da tela
|   const ord = useOrdenacao(filtradas, { campo: 'nome', acessor: 'nome' });
|   const pag = usePaginacao(ord.itens);
|
|   <ThOrdenavel campo="nome" acessor="nome" ord={ord}>Nome</ThOrdenavel>
|   {pag.visiveis.map(...)}
|   <Paginacao {...pag.props} />
|
| Paginar antes de ordenar ordenaria só a página à vista — o registro certo
| ficaria escondido na página 3.
|
| ⚠️ Ao exportar a listagem, o que vai para o arquivo é `ord.itens` (o recorte
| visível inteiro), NUNCA `pag.visiveis` (só a página atual).
|
*/

export const OPCOES_POR_PAGINA = [10, 25, 50, 100] as const;

export function usePaginacao<T>(
    itens: T[],
    porPaginaInicial: number = OPCOES_POR_PAGINA[0],
) {
    const [porPagina, setPorPagina] = useState<number>(porPaginaInicial);
    const [pagina, setPagina] = useState(1);

    const total = itens.length;
    const totalPaginas = Math.max(1, Math.ceil(total / porPagina));
    // Prende no limite em vez de guardar página inválida: se o filtro encolher a
    // lista enquanto a pessoa está na página 5, ela cai na última válida — nunca
    // numa página em branco sem explicação.
    const paginaAtual = Math.min(pagina, totalPaginas);
    const inicio = (paginaAtual - 1) * porPagina;

    const visiveis = useMemo(
        () => itens.slice(inicio, inicio + porPagina),
        [itens, inicio, porPagina],
    );

    return {
        visiveis,
        resetar: () => setPagina(1),
        props: {
            paginaAtual,
            totalPaginas,
            total,
            inicio,
            exibidos: visiveis.length,
            porPagina,
            onIr: (p: number) => setPagina(p),
            onPorPagina: (n: number) => {
                setPorPagina(n);
                setPagina(1);
            },
        },
    };
}

/** Janela de páginas com elipses (1 … 4 5 6 … 20) — evita fileira gigante. */
function janelaDePaginas(atual: number, total: number): (number | '…')[] {
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const paginas: (number | '…')[] = [1];
    const inicio = Math.max(2, atual - 1);
    const fim = Math.min(total - 1, atual + 1);

    if (inicio > 2) {
        paginas.push('…');
    }

    for (let p = inicio; p <= fim; p++) {
        paginas.push(p);
    }

    if (fim < total - 1) {
        paginas.push('…');
    }

    paginas.push(total);

    return paginas;
}

export function Paginacao({
    paginaAtual,
    totalPaginas,
    total,
    inicio,
    exibidos,
    porPagina,
    onIr,
    onPorPagina,
}: {
    paginaAtual: number;
    totalPaginas: number;
    total: number;
    inicio: number;
    exibidos: number;
    porPagina: number;
    onIr: (p: number) => void;
    onPorPagina: (n: number) => void;
}) {
    const idSelect = useId();

    // A tela pode pedir um tamanho fora da lista padrão; sem isto o <select>
    // ficaria em branco (valor sem opção correspondente).
    const opcoes = [...new Set<number>([porPagina, ...OPCOES_POR_PAGINA])].sort(
        (a, b) => a - b,
    );

    // Lista curta demais para paginar: não polui a tela com controle inútil.
    if (total <= opcoes[0]) {
        return null;
    }

    return (
        <div className="paginacao">
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 14,
                    flexWrap: 'wrap',
                }}
            >
                <div>
                    Exibindo <strong>{exibidos === 0 ? 0 : inicio + 1}</strong>–
                    <strong>{inicio + exibidos}</strong> de{' '}
                    <strong>{total}</strong>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <label htmlFor={idSelect}>Por página</label>
                    <select
                        id={idSelect}
                        className="form-control"
                        value={porPagina}
                        onChange={(e) => onPorPagina(Number(e.target.value))}
                        style={{
                            width: 'auto',
                            padding: '6px 10px',
                            fontSize: 13,
                        }}
                    >
                        {opcoes.map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <nav className="paginacao-nav" aria-label="Paginação">
                <button
                    type="button"
                    className="icon-btn"
                    style={{ width: 34, height: 34 }}
                    disabled={paginaAtual === 1}
                    onClick={() => onIr(paginaAtual - 1)}
                    title="Anterior"
                    aria-label="Página anterior"
                >
                    <ChevronLeft size={16} aria-hidden />
                </button>

                {janelaDePaginas(paginaAtual, totalPaginas).map((p, i) =>
                    p === '…' ? (
                        <span
                            key={`vao-${i}`}
                            style={{ padding: '0 4px' }}
                            aria-hidden
                        >
                            …
                        </span>
                    ) : (
                        <button
                            key={p}
                            type="button"
                            className="paginacao-pagina"
                            onClick={() => onIr(p)}
                            aria-current={
                                p === paginaAtual ? 'page' : undefined
                            }
                            aria-label={`Página ${p}`}
                        >
                            {p}
                        </button>
                    ),
                )}

                <button
                    type="button"
                    className="icon-btn"
                    style={{ width: 34, height: 34 }}
                    disabled={paginaAtual === totalPaginas}
                    onClick={() => onIr(paginaAtual + 1)}
                    title="Próxima"
                    aria-label="Próxima página"
                >
                    <ChevronRight size={16} aria-hidden />
                </button>
            </nav>
        </div>
    );
}

export type DirOrd = 'asc' | 'desc';
type ValorOrd = string | number | boolean | null | undefined;
export type AcessorOrd<T> = keyof T | ((item: T) => ValorOrd);

export interface Ordenacao<T> {
    /** Lista ordenada — é ela que vai para `usePaginacao` e para a exportação. */
    itens: T[];
    /** Coluna ativa (null = ordem original). */
    campo: string | null;
    dir: DirOrd;
    /** Alterna a ordenação por uma coluna (a mesma coluna inverte a direção). */
    ordenarPor: (campo: string, acessor: AcessorOrd<T>) => void;
}

function valorOrd<T>(item: T, acessor: AcessorOrd<T>): ValorOrd {
    return typeof acessor === 'function'
        ? acessor(item)
        : (item[acessor] as ValorOrd);
}

function compararOrd(a: ValorOrd, b: ValorOrd): number {
    const aVazio = a === null || a === undefined || a === '';
    const bVazio = b === null || b === undefined || b === '';

    if (aVazio && bVazio) {
        return 0;
    }

    // Vazio sempre por último em ordem crescente: a coluna preenchida é a que
    // interessa a quem ordenou.
    if (aVazio) {
        return 1;
    }

    if (bVazio) {
        return -1;
    }

    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    if (typeof a === 'boolean' && typeof b === 'boolean') {
        return a === b ? 0 : a ? 1 : -1;
    }

    // Texto: alfabético insensível a acento e caixa, com números lidos como
    // números ("Rua 2" antes de "Rua 10").
    return String(a).localeCompare(String(b), 'pt-BR', {
        sensitivity: 'base',
        numeric: true,
    });
}

export function useOrdenacao<T>(
    itens: T[],
    inicial?: { campo: string; dir?: DirOrd; acessor: AcessorOrd<T> },
): Ordenacao<T> {
    const [estado, setEstado] = useState<{
        campo: string;
        dir: DirOrd;
        acessor: AcessorOrd<T>;
    } | null>(
        inicial
            ? {
                  campo: inicial.campo,
                  dir: inicial.dir ?? 'asc',
                  acessor: inicial.acessor,
              }
            : null,
    );

    const ordenarPor = useCallback((campo: string, acessor: AcessorOrd<T>) => {
        setEstado((anterior) =>
            anterior && anterior.campo === campo
                ? {
                      campo,
                      acessor,
                      dir: anterior.dir === 'asc' ? 'desc' : 'asc',
                  }
                : { campo, acessor, dir: 'asc' },
        );
    }, []);

    const ordenados = useMemo(() => {
        if (!estado) {
            return itens;
        }

        const sinal = estado.dir === 'asc' ? 1 : -1;

        // Estável: preserva a ordem original entre iguais.
        return [...itens].sort(
            (a, b) =>
                compararOrd(
                    valorOrd(a, estado.acessor),
                    valorOrd(b, estado.acessor),
                ) * sinal,
        );
    }, [itens, estado]);

    return {
        itens: ordenados,
        campo: estado?.campo ?? null,
        dir: estado?.dir ?? 'asc',
        ordenarPor,
    };
}

/**
 * Cabeçalho de coluna ordenável. O clique alterna a ordem e a seta diz qual é:
 * ▲ crescente / ▼ decrescente na coluna ativa, ↕ esmaecido nas ordenáveis
 * inativas. Colunas que não ordenam (a de ações) continuam `<th>` comum.
 *
 * `acessor` é a chave do objeto ('nome') ou uma função `(item) => valor` — use a
 * função quando a coluna mostra algo derivado (selo, data formatada, 1º item de
 * uma grade). Ordenar pelo TEXTO exibido e não pelo dado é o erro clássico: a
 * data em dd/mm/aaaa ordenaria por dia.
 */
export function ThOrdenavel<T>({
    campo,
    acessor,
    ord,
    children,
    className,
    ...resto
}: {
    campo: string;
    acessor: AcessorOrd<T>;
    ord: Ordenacao<T>;
    children: ReactNode;
} & ThHTMLAttributes<HTMLTableCellElement>) {
    const ativo = ord.campo === campo;
    const Seta = ativo
        ? ord.dir === 'asc'
            ? ChevronUp
            : ChevronDown
        : ChevronsUpDown;

    return (
        <th
            {...resto}
            className={cn('ord', ativo && 'ord-ativa', className)}
            aria-sort={
                ativo
                    ? ord.dir === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
        >
            {/*
                O acionador é um <button>, e não o <th> com `onClick`. Com o
                clique no cabeçalho, ordenar existia só para quem usa mouse: o
                `<th>` não recebe foco, não responde a Enter e a árvore de
                acessibilidade não via ali nada acionável.
                O `<th>` continua sendo `<th>` (o `aria-sort` é dele, e é assim
                que o leitor de tela anuncia a ordem atual); o que virou botão é
                o conteúdo. O estilo mora em `.ord-wrap`, que já era o invólucro.
            */}
            <button
                type="button"
                className="ord-wrap"
                onClick={() => ord.ordenarPor(campo, acessor)}
                title="Ordenar por esta coluna"
            >
                {children}
                <Seta className="ord-ico" size={14} aria-hidden />
            </button>
        </th>
    );
}

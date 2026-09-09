import type { CSSProperties, ReactNode, TdHTMLAttributes } from 'react';
import type { ColunaExportacao } from '@/components/retaguarda/exportar';
import type { AcessorOrd, Ordenacao } from '@/components/retaguarda/th-ordenavel';
import { ThOrdenavel } from '@/components/retaguarda/th-ordenavel';

/**
 * A GRADE ENXUTA — o padrão de listagem do sistema.
 *
 * A régua, com o porquê de cada item, está em `docs/padroes/listagem-clean.md`.
 * O que este arquivo entrega é a mecânica dela: uma linha por registro, altura
 * fixa, o que não couber cortado com reticências.
 *
 * ── Por que as colunas vêm do SERVIDOR ──────────────────────────────────────
 *
 * Porque as mesmas colunas precisam ser conhecidas em dois lugares — a grade e
 * o arquivo exportado —, e a ordem do dono foi enxugar SÓ a grade. Com as duas
 * listas escritas na tela, a primeira "limpeza" seguinte apaga a coluna nos
 * dois e o arquivo perde o dado em silêncio. Declaradas em
 * `config/listagens_da_retaguarda.php` e cruzadas por teste, isso não passa.
 *
 * A tela continua dona do que cada célula DESENHA (o selo, a cor, o retrato):
 * o catálogo diz quais colunas existem e em que ordem, não como pintá-las.
 */

/** Uma coluna visível, como o servidor a entrega. */
export interface ColunaDaGrade {
    chave: string;
    titulo: string;
    alinhar?: 'left' | 'center' | 'right';
    /** Teto de largura da célula — é ele que faz as reticências aparecerem. */
    largura?: number;
}

/** Uma listagem inteira: o que a tela mostra e o que o arquivo leva. */
export interface Listagem {
    grade: ColunaDaGrade[];
    exportacao: ColunaExportacao[];
}

/**
 * As listagens de uma tela, por identificador — uma por aba, porque a aba troca
 * a fonte dos dados e com ela o recorte de colunas.
 */
export type Listagens = Record<string, Listagem>;

/**
 * O cabeçalho montado a partir do catálogo.
 *
 * `acessores` diz como ORDENAR por cada coluna; coluna sem acessor vira `<th>`
 * comum (é o caso da célula que é um seletor, e da que mostra um resumo
 * derivado de vários campos). Montar o cabeçalho aqui e as células no `<tbody>`
 * a partir da MESMA lista é o que impede o defeito clássico da grade: título e
 * valor desalinhados depois de alguém acrescentar uma coluna só num dos dois.
 */
export function CabecaDaGrade<T>({
    grade,
    ord,
    acessores,
}: {
    grade: ColunaDaGrade[];
    /** Ausente na listagem que não ordena — aí todo cabeçalho é `<th>` comum. */
    ord?: Ordenacao<T>;
    acessores?: Record<string, AcessorOrd<T> | undefined>;
}) {
    return (
        <>
            {grade.map((coluna) => {
                const acessor = acessores?.[coluna.chave];
                const estilo: CSSProperties = {
                    width: coluna.largura,
                    textAlign: coluna.alinhar,
                };

                if (acessor === undefined || ord === undefined) {
                    return (
                        <th key={coluna.chave} style={estilo}>
                            {coluna.titulo}
                        </th>
                    );
                }

                return (
                    <ThOrdenavel
                        key={coluna.chave}
                        campo={coluna.chave}
                        acessor={acessor}
                        ord={ord}
                        style={estilo}
                    >
                        {coluna.titulo}
                    </ThOrdenavel>
                );
            })}
        </>
    );
}

/**
 * Uma célula de UMA LINHA.
 *
 * O corte é do CSS (`text-overflow`), e não de um `substring` no dado: o texto
 * continua inteiro no DOM, então leitor de tela e busca do navegador continuam
 * lendo o valor completo — cortar no JavaScript apagaria a informação para
 * quem não vê a tela. `dica` é para o olho: passa o mouse e lê o resto.
 *
 * Quando a tela resume de verdade (o primeiro selo de uma lista com "+2"), a
 * `dica` deixa de ser conforto e passa a ser obrigatória — é o único lugar onde
 * o que foi omitido volta a existir. Aí ela também vira `aria-label`.
 */
export function Celula({
    coluna,
    dica,
    resumida,
    className,
    children,
    style,
    ...resto
}: {
    coluna: ColunaDaGrade;
    /** O texto inteiro, para o `title`. */
    dica?: string | null;
    /** A tela omitiu conteúdo (não é só corte visual)? Então a dica é anunciada. */
    resumida?: boolean;
    children: ReactNode;
} & TdHTMLAttributes<HTMLTableCellElement>) {
    return (
        <td
            {...resto}
            style={{
                maxWidth: coluna.largura,
                textAlign: coluna.alinhar,
                ...style,
            }}
            className={className}
        >
            <span
                className="celula-1l"
                title={dica ?? undefined}
                aria-label={resumida && dica ? dica : undefined}
            >
                {children}
            </span>
        </td>
    );
}

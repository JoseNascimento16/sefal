import {
    Download,
    FileSpreadsheet,
    FileText,
    FileType2,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Spinner } from '@/components/retaguarda/acao';
import { baixarDocumento } from '@/lib/baixar-documento';

/**
 * Exportação de LISTAGEM — botão discreto + a escolha do formato.
 *
 * Vale para os dois tipos de listagem da Retaguarda: a aba "Localizar" das telas
 * de cadastro e a grade operacional (caixas de entrada, consultas, logs). Toda
 * listagem nasce com ele — é lei do projeto.
 *
 * O que sai é o RECORTE VISÍVEL: quem passa as linhas é a tela, já filtradas.
 * Exportar não é uma segunda consulta ao banco — se fosse, o arquivo divergiria
 * do que a pessoa está vendo.
 *
 * @example
 *   <BotaoExportar
 *       titulo="Permissionários"
 *       subtitulo="Fiscalização › Permissionários"
 *       contexto={`Aba: ${aba} · busca: "${busca}"`}
 *       colunas={[{ chave: 'nome', titulo: 'Nome' }]}
 *       linhas={ord.itens}
 *   />
 */

/** Uma coluna do arquivo. `chave` casa com o campo de cada linha; `titulo` é o cabeçalho. */
export interface ColunaExportacao {
    chave: string;
    titulo: string;
    alinhar?: 'left' | 'center' | 'right';
}

interface Props {
    /** Nome do documento — vira o título impresso e o nome do arquivo. */
    titulo: string;
    /** Normalmente o caminho no menu. Ex.: "Sistema › Relatórios". */
    subtitulo?: string;
    /**
     * O recorte EM PALAVRAS: aba ativa, busca digitada, filtros. Vai impresso no
     * documento — sem ele, semanas depois ninguém sabe o que aquelas linhas eram.
     */
    contexto?: string;
    colunas: ColunaExportacao[];
    /**
     * As linhas a exportar — passe o resultado JÁ FILTRADO (ex.: `ord.itens`), e
     * não o da página visível: paginação é artifício de visualização, o filtro é
     * a intenção de quem pediu. Quem filtrou espera o resultado inteiro, não os
     * dez primeiros.
     */
    linhas: Record<string, unknown>[];
    /** Força a orientação do PDF; por padrão o servidor deita a folha acima de 6 colunas. */
    orientacao?: 'portrait' | 'landscape';
}

type Formato = 'pdf' | 'xlsx' | 'docx';

const FORMATOS: {
    chave: Formato;
    rotulo: string;
    Icone: typeof FileText;
    cor: string;
}[] = [
    { chave: 'pdf', rotulo: 'PDF', Icone: FileText, cor: '#b3261e' },
    { chave: 'xlsx', rotulo: 'EXCEL', Icone: FileSpreadsheet, cor: '#0f7a52' },
    { chave: 'docx', rotulo: 'WORD', Icone: FileType2, cor: '#0b6f8c' },
];

export default function BotaoExportar({
    titulo,
    subtitulo,
    contexto,
    colunas,
    linhas,
    orientacao,
}: Props) {
    const [aberto, setAberto] = useState(false);
    const [gerando, setGerando] = useState<Formato | null>(null);
    const [erro, setErro] = useState<string | null>(null);

    async function exportar(formato: Formato) {
        setGerando(formato);
        setErro(null);

        const resultado = await baixarDocumento(
            '/retaguarda/exportar-listagem',
            {
                formato,
                titulo,
                subtitulo,
                contexto,
                colunas,
                linhas,
                orientacao,
            },
            `${titulo}.${formato}`,
        );

        setGerando(null);

        if (resultado.ok) {
            setAberto(false);

            return;
        }

        // A recusa tem de DIZER o motivo (o teto de linhas, por exemplo):
        // download que simplesmente não acontece parece o sistema travado.
        setErro(resultado.mensagem);
    }

    const vazio = linhas.length === 0;

    return (
        <>
            {/* Discreto e à direita, logo acima da tabela: exportar é conveniência,
                não a ação central da tela — daí `btn-secondary`, nunca primário. */}
            <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => setAberto(true)}
                disabled={vazio}
                title={
                    vazio
                        ? 'Nada a exportar com o filtro atual'
                        : `Exportar ${linhas.length} registro(s) — o que está filtrado na tela`
                }
                style={{ marginLeft: 'auto' }}
            >
                <Download size={15} aria-hidden /> Exportar
            </button>

            {aberto && (
                <div
                    className="sobreposicao"
                    role="presentation"
                    onClick={gerando ? undefined : () => setAberto(false)}
                >
                    <div
                        className="card-premium"
                        style={{ width: '100%', maxWidth: 460 }}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Exportar a listagem"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 10,
                            }}
                        >
                            <h2 className="sobreposicao-titulo">
                                <Download size={18} aria-hidden /> Exportar
                            </h2>
                            <button
                                type="button"
                                className="icon-btn"
                                onClick={() => setAberto(false)}
                                disabled={gerando !== null}
                                aria-label="Fechar"
                            >
                                <X size={16} aria-hidden />
                            </button>
                        </div>

                        {/* Quem exporta precisa saber O QUE vai sair antes de escolher o formato. */}
                        <p className="sobreposicao-texto">
                            <strong>{titulo}</strong>
                            {contexto ? ` · ${contexto}` : ''}
                            <br />
                            {linhas.length} registro(s) · {colunas.length}{' '}
                            coluna(s) — exatamente o que está filtrado na tela.
                        </p>

                        {erro && (
                            <p
                                className="form-erro"
                                style={{ marginBottom: 14 }}
                            >
                                <TriangleAlert size={15} aria-hidden /> {erro}
                            </p>
                        )}

                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                flexWrap: 'wrap',
                            }}
                        >
                            {FORMATOS.map(({ chave, rotulo, Icone, cor }) => (
                                <button
                                    key={chave}
                                    type="button"
                                    className="btn btn-sm"
                                    onClick={() => exportar(chave)}
                                    disabled={gerando !== null}
                                    aria-busy={gerando === chave || undefined}
                                    style={{
                                        flex: 1,
                                        minWidth: 120,
                                        background: cor,
                                        color: '#fff',
                                    }}
                                >
                                    {gerando === chave ? (
                                        <>
                                            <Spinner tamanho={15} /> Gerando…
                                        </>
                                    ) : (
                                        <>
                                            <Icone size={15} aria-hidden />{' '}
                                            {rotulo}
                                        </>
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

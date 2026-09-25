import { Download, ExternalLink, FileText, ImageOff } from 'lucide-react';

/**
 * Um arquivo do processo — a foto tirada em campo ou o anexo que veio com a
 * demanda —, como o servidor o entrega (`ArquivoParaTela`).
 */
export interface Arquivo {
    id: number;
    nome: string;
    /** Imagem ganha miniatura; o resto, o ícone de documento. */
    imagem: boolean;
    /** O arquivo existe no disco? Se não, a tela DIZ — não oferece link que dá erro. */
    disponivel: boolean;
    /** Abre no navegador. */
    url: string;
    /** Baixa o arquivo. */
    baixar: string;
}

/**
 * Os arquivos do processo, para VER e BAIXAR (pedido do dono, 25/09/2026).
 *
 * Imagem mostra a miniatura, que abre a foto inteira numa aba nova; documento
 * mostra o ícone. Cada um tem "Ver" e "Baixar". O endereço é a rota do servidor,
 * que confere a permissão e o recorte do líder — a miniatura não fura isso.
 */
export function ListaDeArquivos({ arquivos, vazio }: { arquivos: Arquivo[]; vazio?: string }) {
    if (arquivos.length === 0) {
        return vazio ? <p className="form-ajuda">{vazio}</p> : null;
    }

    return (
        <ul className="rt-arquivos">
            {arquivos.map((a) => (
                <li key={`${a.id}-${a.url}`} className="rt-arquivo">
                    {a.disponivel ? (
                        <a
                            className="rt-arquivo-miniatura"
                            href={a.url}
                            target="_blank"
                            rel="noopener"
                            title={`Abrir ${a.nome}`}
                        >
                            {a.imagem ? (
                                <img src={a.url} alt={a.nome} loading="lazy" />
                            ) : (
                                <FileText size={28} aria-hidden />
                            )}
                        </a>
                    ) : (
                        <span className="rt-arquivo-miniatura rt-arquivo-ausente" title="O arquivo não foi encontrado no armazenamento">
                            <ImageOff size={24} aria-hidden />
                        </span>
                    )}

                    <span className="rt-arquivo-nome" title={a.nome}>
                        {a.nome}
                    </span>

                    {a.disponivel ? (
                        <span className="rt-arquivo-acoes">
                            <a href={a.url} target="_blank" rel="noopener">
                                <ExternalLink size={13} aria-hidden /> Ver
                            </a>
                            <a href={a.baixar} download>
                                <Download size={13} aria-hidden /> Baixar
                            </a>
                        </span>
                    ) : (
                        <span className="rt-arquivo-acoes rt-arquivo-aviso">Arquivo não encontrado</span>
                    )}
                </li>
            ))}
        </ul>
    );
}

import { Lightbulb, Search, Sparkles, X } from 'lucide-react';

/**
 * Barra da BUSCA INTELIGENTE — campo único e amplo, com exemplos clicáveis.
 *
 * É o filtro da tela: não crie chips nem botões de filtro paralelos ("por
 * situação", "por origem"). Quem interpreta a frase é a tela, com
 * `parseConsulta` (`@/lib/busca`), que separa as facetas do domínio dos termos
 * livres e compara sem acento.
 *
 * @example
 *   <BuscaInteligente
 *       busca={busca}
 *       setBusca={setBusca}
 *       placeholder="Procure por nome, apelido, documento ou nº de permissão"
 *       exemplos={['cadastrado em campo', 'sem documento', 'barra']}
 *   />
 */
export function BuscaInteligente({
    busca,
    setBusca,
    placeholder,
    exemplos = [],
    onSubmit,
}: {
    busca: string;
    setBusca: (v: string) => void;
    placeholder: string;
    exemplos?: string[];
    /**
     * Opcional, para telas que buscam no servidor. Recebe o termo quando o
     * disparo vem de um exemplo clicado.
     *
     * ⚠️ O parâmetro não é decorativo: `setBusca` é assíncrono, então um
     * `onSubmit()` que leia o estado do closure consultaria o termo ANTERIOR — a
     * pessoa clica num exemplo e recebe o resultado do que estava no campo antes.
     * Use o valor recebido e caia no estado só quando ele vier `undefined` (o
     * caso do Enter).
     */
    onSubmit?: (termo?: string) => void;
}) {
    return (
        <div style={{ margin: '4px 0 18px' }}>
            <div className="busca-campo">
                <Sparkles className="busca-marca" size={20} aria-hidden />

                <input
                    type="text"
                    value={busca}
                    onChange={(e) => setBusca(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && onSubmit) {
                            onSubmit();
                        }
                    }}
                    placeholder={placeholder}
                    aria-label={placeholder}
                />

                <div className="busca-campo-acoes">
                    {busca && (
                        <button
                            type="button"
                            className="icon-btn"
                            title="Limpar"
                            aria-label="Limpar a busca"
                            style={{ width: 32, height: 32 }}
                            onClick={() => setBusca('')}
                        >
                            <X size={16} aria-hidden />
                        </button>
                    )}

                    {onSubmit ? (
                        <button
                            type="button"
                            className="icon-btn"
                            title="Pesquisar"
                            aria-label="Pesquisar"
                            style={{ color: 'var(--sm-primaria)' }}
                            // `() => onSubmit()` e não `onSubmit`: passado direto,
                            // o React entregaria o evento do mouse como termo.
                            onClick={() => onSubmit()}
                        >
                            <Search size={18} aria-hidden />
                        </button>
                    ) : (
                        <Search
                            size={18}
                            aria-hidden
                            style={{
                                color: 'var(--sm-texto-fraco)',
                                marginRight: 8,
                            }}
                        />
                    )}
                </div>
            </div>

            {exemplos.length > 0 && (
                <div className="busca-exemplos">
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                        }}
                    >
                        <Lightbulb size={14} aria-hidden /> Tente:
                    </span>

                    {/* Exemplos ACIONÁVEIS: clicar preenche o campo e já busca. */}
                    {exemplos.map((exemplo) => (
                        <button
                            key={exemplo}
                            type="button"
                            className="busca-exemplo"
                            title={`Buscar por "${exemplo}"`}
                            onClick={() => {
                                setBusca(exemplo);
                                onSubmit?.(exemplo);
                            }}
                        >
                            {exemplo}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

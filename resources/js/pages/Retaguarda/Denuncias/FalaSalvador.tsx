import { Head } from '@inertiajs/react';
import { Info, PhoneIncoming, Plus, X } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { PainelDeDenuncias } from '@/components/retaguarda/painel-de-denuncias';
import type { Sugestao } from '@/dados-prototipo/administrativo';
import { useEnvio } from '@/hooks/use-envio';
import { index, registrar } from '@/routes/retaguarda/denuncias/fala-salvador';

/**
 * Denúncias do Fala Salvador (156).
 *
 * Casca fina, como a irmã do e-Salvador: a mecânica do módulo vive em
 * `PainelDeDenuncias`. Ver o cabeçalho de `ESalvador.tsx` para o porquê.
 *
 * ── O que o Fala Salvador tem de diferente ──────────────────────────────────
 *
 * Não há integração. Só os LÍDERES de equipe acessam o Fala Salvador, e o SEFAL
 * é intermediário de registro: o líder digita aqui o que recebeu por telefone,
 * para o caso andar pelo mesmo fluxo das outras demandas — e continua respondendo
 * ao cidadão no próprio Fala Salvador. Por isso esta tela tem o que a do
 * e-Salvador não tem: o formulário de REGISTRO, que nasce já na mesa do líder
 * (`Encaminhada ao líder`), com a equipe dele.
 *
 * O atendimento é por TELEFONE, e isso muda o dado: a denúncia pode ser
 * ANÔNIMA, o relato é a transcrição do que o atendente ouviu — texto mais solto,
 * às vezes sem número nem ponto de referência —, e não há anexo.
 *
 * ⚠️ O formulário é o MÍNIMO para o caso existir no fluxo. O formulário
 * específico do canal (com o que o Fala Salvador de fato entrega — o dono tem
 * fotos e documentos) vem depois: PEND-023.
 */
type Props = ComponentProps<typeof PainelDeDenuncias> & {
    /** Esta pessoa registra o canal aqui? Quem responde é o servidor (líder ou administrador). */
    registra: boolean;
    bairros: string[];
    sugestoes: Record<string, Sugestao>;
};

export default function FalaSalvador({ registra, bairros, sugestoes, ...painel }: Props) {
    const [aberto, setAberto] = useState(false);

    return (
        <>
            <Head title="Denúncias do Fala Salvador" />

            {registra && (
                <section className="card-premium" style={{ marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: 12,
                            flexWrap: 'wrap',
                        }}
                    >
                        <div>
                            <h2 className="card-titulo" style={{ margin: 0 }}>
                                <PhoneIncoming size={16} aria-hidden /> Recebeu uma denúncia no
                                Fala Salvador?
                            </h2>
                            <p className="card-sub" style={{ margin: '4px 0 0' }}>
                                Registre aqui para ela entrar na sua mesa e seguir o fluxo —
                                direcionar aos fiscais, receber o retorno. A resposta ao cidadão
                                continua no Fala Salvador.
                            </p>
                        </div>
                        <button
                            type="button"
                            className={aberto ? 'btn btn-secondary btn-sm' : 'btn btn-primary btn-sm'}
                            onClick={() => setAberto((a) => !a)}
                        >
                            {aberto ? (
                                <>
                                    <X size={15} aria-hidden /> Fechar
                                </>
                            ) : (
                                <>
                                    <Plus size={15} aria-hidden /> Registrar denúncia
                                </>
                            )}
                        </button>
                    </div>

                    {aberto && (
                        <RegistroDoFalaSalvador
                            bairros={bairros}
                            sugestoes={sugestoes}
                            equipesDoLider={painel.equipesDoLider}
                            equipes={painel.equipes}
                            aoRegistrar={() => setAberto(false)}
                        />
                    )}
                </section>
            )}

            <PainelDeDenuncias {...painel} />
        </>
    );
}

FalaSalvador.layout = {
    breadcrumbs: [
        { title: 'Denúncias', href: index() },
        { title: 'Fala Salvador', href: index() },
    ],
};

// ── O formulário de registro ─────────────────────────────────────────────────

type RegistroProps = {
    bairros: string[];
    sugestoes: Record<string, Sugestao>;
    equipesDoLider: string[];
    equipes: ComponentProps<typeof PainelDeDenuncias>['equipes'];
    aoRegistrar: () => void;
};

function RegistroDoFalaSalvador({ bairros, sugestoes, equipesDoLider, equipes, aoRegistrar }: RegistroProps) {
    const hoje = new Date().toISOString().slice(0, 10);
    const { enviando, ocupado, enviar } = useEnvio();

    /*
     * Quem lidera UMA equipe não escolhe — ela é a dele. Quem lidera mais de uma
     * (ou o administrador, que não lidera nenhuma) escolhe entre as que pode.
     */
    const escolhe = equipesDoLider.length !== 1;
    const opcoes = equipesDoLider.length > 0 ? equipesDoLider : equipes.map((e) => e.equipe);

    const vazio = {
        documento_origem: '',
        recebida_em: hoje,
        anonima: false,
        requerente: '',
        contato: '',
        assunto: '',
        endereco: '',
        bairro: '',
        descricao: '',
        equipe: escolhe ? '' : equipesDoLider[0],
    };

    const [form, setForm] = useState({ ...vazio });
    const [erros, setErros] = useState<Record<string, string>>({});

    function mudar<C extends keyof typeof vazio>(campo: C, valor: (typeof vazio)[C]) {
        setForm((atual) => ({ ...atual, [campo]: valor }));
    }

    const sugestao = form.bairro ? (sugestoes[form.bairro] ?? null) : null;
    // O bairro sugere uma equipe — que pode não ser a do líder. Aviso, não bloqueio:
    // o cidadão ligou para ELE; a sugestão só diz se o ponto é do território dele.
    const foraDoTerritorio =
        sugestao !== null && equipesDoLider.length > 0 && !equipesDoLider.includes(sugestao.equipe);

    function nomeDaEquipe(codigo: string): string {
        const e = equipes.find((x) => x.equipe === codigo);

        return e === undefined ? `Equipe ${codigo}` : `Equipe ${e.equipe} · ${e.area}`;
    }

    function submeter() {
        enviar(
            'registrar-fala-salvador',
            registrar().url,
            {
                ...form,
                equipe: form.equipe || null,
                requerente: form.anonima ? null : form.requerente,
                contato: form.anonima ? null : form.contato,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setForm({ ...vazio });
                    setErros({});
                    aoRegistrar();
                },
                onError: (e) => setErros(e as Record<string, string>),
            },
        );
    }

    const erro = (campo: string) =>
        erros[campo] === undefined ? null : (
            <p className="form-ajuda" style={{ color: 'var(--sm-perigo)' }}>
                {erros[campo]}
            </p>
        );

    return (
        <form
            style={{ marginTop: 18 }}
            onSubmit={(e) => {
                e.preventDefault();
                submeter();
            }}
        >
            <div className="rt-form-linha">
                <div className="form-group">
                    <label className="form-label" htmlFor="fs-documento">
                        Nº do atendimento no Fala Salvador
                    </label>
                    <input
                        id="fs-documento"
                        type="text"
                        className="form-control"
                        value={form.documento_origem}
                        maxLength={40}
                        placeholder="Ex.: 156-2026-884120"
                        onChange={(e) => mudar('documento_origem', e.target.value)}
                    />
                    {erro('documento_origem')}
                </div>

                <div className="form-group">
                    <label className="form-label" htmlFor="fs-recebida">
                        Data do atendimento
                    </label>
                    <input
                        id="fs-recebida"
                        type="date"
                        className="form-control"
                        value={form.recebida_em}
                        max={hoje}
                        onChange={(e) => mudar('recebida_em', e.target.value)}
                    />
                    {erro('recebida_em')}
                </div>

                {escolhe && (
                    <div className="form-group">
                        <label className="form-label" htmlFor="fs-equipe">
                            Equipe
                        </label>
                        <select
                            id="fs-equipe"
                            className="form-control"
                            value={form.equipe}
                            onChange={(e) => mudar('equipe', e.target.value)}
                        >
                            <option value="">Escolha a equipe…</option>
                            {opcoes.map((c) => (
                                <option key={c} value={c}>
                                    {nomeDaEquipe(c)}
                                </option>
                            ))}
                        </select>
                        {erro('equipe')}
                    </div>
                )}
            </div>

            <div className="form-group">
                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                    <input
                        type="checkbox"
                        checked={form.anonima}
                        onChange={(e) => mudar('anonima', e.target.checked)}
                    />
                    <span>Denúncia anônima (quem ligou não se identificou)</span>
                </label>
            </div>

            {!form.anonima && (
                <div className="rt-form-linha">
                    <div className="form-group">
                        <label className="form-label" htmlFor="fs-requerente">
                            Quem ligou
                        </label>
                        <input
                            id="fs-requerente"
                            type="text"
                            className="form-control"
                            value={form.requerente}
                            maxLength={150}
                            onChange={(e) => mudar('requerente', e.target.value)}
                        />
                        {erro('requerente')}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="fs-contato">
                            Contato
                        </label>
                        <input
                            id="fs-contato"
                            type="text"
                            className="form-control"
                            value={form.contato}
                            maxLength={80}
                            placeholder="Telefone"
                            onChange={(e) => mudar('contato', e.target.value)}
                        />
                    </div>
                </div>
            )}

            <div className="rt-form-linha">
                <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                    <label className="form-label" htmlFor="fs-endereco">
                        Endereço da ocorrência
                    </label>
                    <input
                        id="fs-endereco"
                        type="text"
                        className="form-control"
                        value={form.endereco}
                        maxLength={200}
                        placeholder="Rua, número e ponto de referência — como o cidadão descreveu"
                        onChange={(e) => mudar('endereco', e.target.value)}
                    />
                    {erro('endereco')}
                </div>
            </div>

            <div className="form-group">
                <label className="form-label" htmlFor="fs-bairro">
                    Bairro
                </label>
                <select
                    id="fs-bairro"
                    className="form-control"
                    value={form.bairro}
                    onChange={(e) => mudar('bairro', e.target.value)}
                >
                    <option value="">Escolha o bairro…</option>
                    {bairros.map((b) => (
                        <option key={b} value={b}>
                            {b}
                        </option>
                    ))}
                </select>
                {erro('bairro')}
                {foraDoTerritorio && sugestao !== null && (
                    <div className="rt-sugestao" style={{ marginTop: 8 }}>
                        <Info size={16} aria-hidden />
                        <div>
                            <strong>
                                {form.bairro} é do território da Equipe {sugestao.equipe} · {sugestao.area}.
                            </strong>{' '}
                            Você pode registrar mesmo assim — o caso fica na sua mesa. Se não for seu,
                            devolva ao Chefe de Setor depois de registrar, para ele encaminhar à equipe
                            certa.
                        </div>
                    </div>
                )}
            </div>

            <div className="form-group">
                <label className="form-label" htmlFor="fs-assunto">
                    Assunto
                </label>
                <input
                    id="fs-assunto"
                    type="text"
                    className="form-control"
                    value={form.assunto}
                    maxLength={180}
                    placeholder="O caso em uma linha"
                    onChange={(e) => mudar('assunto', e.target.value)}
                />
                {erro('assunto')}
            </div>

            <div className="form-group">
                <label className="form-label" htmlFor="fs-descricao">
                    O que foi relatado
                </label>
                <textarea
                    id="fs-descricao"
                    className="form-control"
                    rows={4}
                    value={form.descricao}
                    maxLength={2000}
                    placeholder="A transcrição do que o cidadão disse, como o atendente registrou"
                    onChange={(e) => mudar('descricao', e.target.value)}
                />
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
                <BotaoAcao
                    type="submit"
                    icone={<PhoneIncoming size={15} aria-hidden />}
                    carregando={enviando === 'registrar-fala-salvador'}
                    ocupado={ocupado}
                    rotuloCarregando="Registrando…"
                >
                    Registrar na minha mesa
                </BotaoAcao>
            </div>
        </form>
    );
}

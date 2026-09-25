import { Info, Plus, Save, X } from 'lucide-react';
import { useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import type { EquipeResumo, Sugestao } from '@/dados-prototipo/administrativo';
import { useEnvio } from '@/hooks/use-envio';
import { hojeISO } from '@/lib/datas';
import { registrar } from '@/routes/retaguarda/denuncias';

/** O que o formulário precisa saber de cada canal em que grava. */
export interface CanalDeRegistro {
    slug: string;
    nome: string;
    admite_anonima: boolean;
    registro?: 'chefe' | 'lider';
    /** O canal recebe arquivo junto (ofício digitalizado, e-mail, foto)? */
    tem_anexo?: boolean;
}

/**
 * O CADASTRO manual de uma demanda — o mesmo formulário nas quatro caixas.
 *
 * Quem digita depende do canal, e quem decide é o SERVIDOR (a prop `registra`
 * da tela): o Fala Salvador é do líder, e o caso nasce na mesa dele, com a
 * equipe dele; os demais são do Chefe de Setor, e nascem `Recebida`, esperando
 * o encaminhamento. Na caixa do e-Salvador o chefe escolhe se é denúncia ou
 * licença — a licença chega pelo mesmo portal e mora na aba própria.
 *
 * ⚠️ É o MÍNIMO para o caso existir no fluxo. O formulário específico de cada
 * canal (o que o Fala Salvador e o e-Protocolo de fato entregam) vem depois:
 * PEND-023.
 */
export function RegistroDeDemanda({
    canais,
    bairros,
    sugestoes,
    equipes,
    equipesDoLider,
}: {
    /** Os canais em que esta pessoa grava nesta tela — o primeiro é o padrão. */
    canais: CanalDeRegistro[];
    bairros: string[];
    sugestoes: Record<string, Sugestao>;
    equipes: EquipeResumo[];
    equipesDoLider: string[];
}) {
    const [aberto, setAberto] = useState(false);
    const [slug, setSlug] = useState(canais[0]?.slug ?? '');
    const canal = canais.find((c) => c.slug === slug) ?? canais[0];

    if (canal === undefined) {
        return null;
    }

    const doLider = canal.registro === 'lider';

    return (
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
                        <Plus size={16} aria-hidden /> Registrar demanda
                    </h2>
                    <p className="card-sub" style={{ margin: '4px 0 0' }}>
                        {doLider
                            ? 'Registre aqui o que você recebeu: o caso entra na sua mesa para direcionar aos fiscais. A resposta ao cidadão continua no canal.'
                            : 'Registre o que chegou até você: a demanda entra como recebida e espera o seu encaminhamento à equipe.'}
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
                            <Plus size={15} aria-hidden /> Registrar
                        </>
                    )}
                </button>
            </div>

            {aberto && (
                <Formulario
                    key={canal.slug}
                    canal={canal}
                    canais={canais}
                    trocarCanal={setSlug}
                    bairros={bairros}
                    sugestoes={sugestoes}
                    equipes={equipes}
                    equipesDoLider={equipesDoLider}
                    aoRegistrar={() => setAberto(false)}
                />
            )}
        </section>
    );
}

function Formulario({
    canal,
    canais,
    trocarCanal,
    bairros,
    sugestoes,
    equipes,
    equipesDoLider,
    aoRegistrar,
}: {
    canal: CanalDeRegistro;
    canais: CanalDeRegistro[];
    trocarCanal: (slug: string) => void;
    bairros: string[];
    sugestoes: Record<string, Sugestao>;
    equipes: EquipeResumo[];
    equipesDoLider: string[];
    aoRegistrar: () => void;
}) {
    const hoje = hojeISO();
    const { enviando, ocupado, enviar } = useEnvio();

    const doLider = canal.registro === 'lider';
    const avulsa = canal.slug === 'avulsa';

    /*
     * Equipe só no cadastro do LÍDER, e só se ele lidera mais de uma (ou é o
     * administrador, que não lidera nenhuma). No do chefe a equipe é escolhida
     * depois, no encaminhamento — é esse o ato dele.
     */
    const escolheEquipe = doLider && equipesDoLider.length !== 1;
    const opcoes = equipesDoLider.length > 0 ? equipesDoLider : equipes.map((e) => e.equipe);

    const vazio = {
        // Só a avulsa usa: de onde veio o pedido (dono, 25/09/2026 — o ofício é um tipo de avulsa).
        tipo_avulsa: 'pedido-de-superior',
        documento_origem: '',
        recebida_em: hoje,
        anonima: false,
        requerente: '',
        contato: '',
        assunto: '',
        endereco: '',
        bairro: '',
        descricao: '',
        equipe: doLider && !escolheEquipe ? (equipesDoLider[0] ?? '') : '',
    };

    const [form, setForm] = useState({ ...vazio });
    // Os arquivos ficam fora do `form`: arquivo não se "limpa" trocando o valor do campo.
    const [anexos, setAnexos] = useState<File[]>([]);
    const [chaveDoCampoDeArquivo, setChaveDoCampoDeArquivo] = useState(0);
    const [erros, setErros] = useState<Record<string, string>>({});

    function mudar<C extends keyof typeof vazio>(campo: C, valor: (typeof vazio)[C]) {
        setForm((atual) => ({ ...atual, [campo]: valor }));
    }

    const sugestao = form.bairro ? (sugestoes[form.bairro] ?? null) : null;
    // No cadastro do líder, o bairro pode ser território de outra equipe: aviso,
    // não bloqueio — o cidadão ligou para ELE.
    const foraDoTerritorio =
        doLider && sugestao !== null && equipesDoLider.length > 0 && !equipesDoLider.includes(sugestao.equipe);

    function nomeDaEquipe(codigo: string): string {
        const e = equipes.find((x) => x.equipe === codigo);

        return e === undefined ? `Equipe ${codigo}` : `Equipe ${e.equipe} · ${e.area}`;
    }

    function submeter() {
        enviar(
            'registrar-demanda',
            registrar({ canal: canal.slug }).url,
            {
                ...form,
                documento_origem: form.documento_origem.trim() === '' ? null : form.documento_origem,
                tipo_avulsa: avulsa ? form.tipo_avulsa : null,
                equipe: form.equipe || null,
                anonima: canal.admite_anonima ? form.anonima : false,
                requerente: form.anonima ? null : form.requerente,
                contato: form.anonima ? null : form.contato,
                // Com arquivo, o Inertia manda como formulário multipart sozinho.
                anexos: canal.tem_anexo ? anexos : [],
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setForm({ ...vazio });
                    setAnexos([]);
                    setChaveDoCampoDeArquivo((n) => n + 1);
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

    const obrigatorio = <span aria-hidden style={{ color: 'var(--sm-perigo)' }}>*</span>;

    return (
        <form
            style={{ marginTop: 18 }}
            onSubmit={(e) => {
                e.preventDefault();
                submeter();
            }}
        >
            <div className="rt-form-linha">
                {canais.length > 1 && (
                    <div className="form-group">
                        <label className="form-label" htmlFor="rd-canal">
                            Tipo {obrigatorio}
                        </label>
                        <select
                            id="rd-canal"
                            className="form-control"
                            value={canal.slug}
                            onChange={(e) => trocarCanal(e.target.value)}
                        >
                            {canais.map((c) => (
                                <option key={c.slug} value={c.slug}>
                                    {c.slug === 'nova-licenca' ? 'Licença' : 'Denúncia'}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {avulsa && (
                    <div className="form-group">
                        <label className="form-label" htmlFor="rd-tipo-avulsa">
                            Chegou como {obrigatorio}
                        </label>
                        <select
                            id="rd-tipo-avulsa"
                            className="form-control"
                            value={form.tipo_avulsa}
                            onChange={(e) => mudar('tipo_avulsa', e.target.value)}
                        >
                            <option value="pedido-de-superior">Pedido de superior (ligação ou e-mail)</option>
                            <option value="oficio">Ofício de órgão ou do Ministério Público</option>
                        </select>
                        {erro('tipo_avulsa')}
                    </div>
                )}

                <div className="form-group">
                    <label className="form-label" htmlFor="rd-documento">
                        {avulsa ? (
                            form.tipo_avulsa === 'oficio' ? 'Nº do ofício (se houver)' : 'Nº do e-mail (se houver)'
                        ) : (
                            <>Nº no {canal.nome} {obrigatorio}</>
                        )}
                    </label>
                    <input
                        id="rd-documento"
                        type="text"
                        className="form-control"
                        value={form.documento_origem}
                        maxLength={40}
                        placeholder={
                            avulsa
                                ? form.tipo_avulsa === 'oficio'
                                    ? 'Ex.: Ofício nº 123/2026'
                                    : 'Foi uma ligação? Deixe em branco'
                                : 'O número que o canal deu'
                        }
                        onChange={(e) => mudar('documento_origem', e.target.value)}
                    />
                    {erro('documento_origem')}
                </div>

                <div className="form-group">
                    <label className="form-label" htmlFor="rd-recebida">
                        Recebida em {obrigatorio}
                    </label>
                    <input
                        id="rd-recebida"
                        type="date"
                        className="form-control"
                        value={form.recebida_em}
                        max={hoje}
                        onChange={(e) => mudar('recebida_em', e.target.value)}
                    />
                    {erro('recebida_em')}
                </div>

                {escolheEquipe && (
                    <div className="form-group">
                        <label className="form-label" htmlFor="rd-equipe">
                            Equipe {obrigatorio}
                        </label>
                        <select
                            id="rd-equipe"
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

            {canal.admite_anonima && (
                <div className="form-group">
                    <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                        <input
                            type="checkbox"
                            checked={form.anonima}
                            onChange={(e) => mudar('anonima', e.target.checked)}
                        />
                        <span>Denúncia anônima (quem relatou não se identificou)</span>
                    </label>
                </div>
            )}

            {!form.anonima && (
                <div className="rt-form-linha">
                    <div className="form-group">
                        <label className="form-label" htmlFor="rd-requerente">
                            {avulsa ? (form.tipo_avulsa === 'oficio' ? 'Órgão que enviou' : 'Quem pediu') : 'Requerente'} {obrigatorio}
                        </label>
                        <input
                            id="rd-requerente"
                            type="text"
                            className="form-control"
                            value={form.requerente}
                            maxLength={150}
                            onChange={(e) => mudar('requerente', e.target.value)}
                        />
                        {erro('requerente')}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="rd-contato">
                            Contato
                        </label>
                        <input
                            id="rd-contato"
                            type="text"
                            className="form-control"
                            value={form.contato}
                            maxLength={80}
                            placeholder="Telefone ou e-mail"
                            onChange={(e) => mudar('contato', e.target.value)}
                        />
                    </div>
                </div>
            )}

            <div className="form-group">
                <label className="form-label" htmlFor="rd-endereco">
                    Endereço da ocorrência {obrigatorio}
                </label>
                <input
                    id="rd-endereco"
                    type="text"
                    className="form-control"
                    value={form.endereco}
                    maxLength={200}
                    placeholder="Rua, número e ponto de referência"
                    onChange={(e) => mudar('endereco', e.target.value)}
                />
                {erro('endereco')}
            </div>

            <div className="form-group">
                <label className="form-label" htmlFor="rd-bairro">
                    Bairro {obrigatorio}
                </label>
                <select
                    id="rd-bairro"
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
                            encaminhe ao Chefe de Setor depois de registrar.
                        </div>
                    </div>
                )}
            </div>

            <div className="form-group">
                <label className="form-label" htmlFor="rd-assunto">
                    Assunto {obrigatorio}
                </label>
                <input
                    id="rd-assunto"
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
                <label className="form-label" htmlFor="rd-descricao">
                    O que foi relatado
                </label>
                <textarea
                    id="rd-descricao"
                    className="form-control"
                    rows={4}
                    value={form.descricao}
                    maxLength={2000}
                    placeholder="O relato, como chegou"
                    onChange={(e) => mudar('descricao', e.target.value)}
                />
            </div>

            {canal.tem_anexo && (
                <div className="form-group">
                    <label className="form-label" htmlFor="rd-anexos">
                        Anexos
                    </label>
                    <input
                        key={chaveDoCampoDeArquivo}
                        id="rd-anexos"
                        type="file"
                        className="form-control"
                        multiple
                        accept=".pdf,.doc,.docx,.odt,.txt,.rtf,.jpg,.jpeg,.png,.webp,.gif,.bmp"
                        onChange={(e) => setAnexos(Array.from(e.target.files ?? []))}
                    />
                    <p className="form-ajuda">
                        O ofício digitalizado, o e-mail, a foto — até cinco arquivos de até 10 MB, em PDF, documento
                        ou imagem. Ficam na demanda e podem ser vistos e baixados por quem trabalha nela.
                    </p>
                    {erro('anexos')}
                    {Object.keys(erros)
                        .filter((chave) => chave.startsWith('anexos.'))
                        .map((chave) => (
                            <p key={chave} className="form-ajuda" style={{ color: 'var(--sm-perigo)' }}>
                                {erros[chave]}
                            </p>
                        ))}
                </div>
            )}

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
                <BotaoAcao
                    type="submit"
                    icone={<Save size={15} aria-hidden />}
                    carregando={enviando === 'registrar-demanda'}
                    ocupado={ocupado}
                    rotuloCarregando="Registrando…"
                >
                    {doLider ? 'Registrar na minha mesa' : 'Registrar demanda'}
                </BotaoAcao>
            </div>
        </form>
    );
}

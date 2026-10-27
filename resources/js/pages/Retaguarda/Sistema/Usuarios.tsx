import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    CircleCheck,
    CircleSlash,
    Eye,
    KeyRound,
    List,
    Mail,
    Pencil,
    Plus,
    RotateCcw,
    ShieldCheck,
    Trash2,
    TriangleAlert,
    Undo2,
    UserX,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import { Paginacao, useOrdenacao, usePaginacao } from '@/components/retaguarda/th-ordenavel';
import { useAcoes } from '@/hooks/use-acoes';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { dataHoraBR, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { index as areasEEquipes } from '@/routes/retaguarda/areas-e-equipes';
import { convite, destroy, index, restaurar, store, update } from '@/routes/retaguarda/usuarios';

/**
 * Sistema › Usuários — quem tem conta na Retaguarda, e em que setor.
 *
 * A tela do Codecon trazida para cá (pedido do dono, 25/09/2026): incluir com
 * convite de primeiro acesso por e-mail, alterar setores e situação, reenviar o
 * convite, excluir para a lixeira e restaurar. As colunas das duas listagens vêm
 * do catálogo (`usuarios.ativos` e `usuarios.excluidos`, em
 * `config/listagens_da_retaguarda.php`).
 *
 * O registro abre em modo NAVEGAÇÃO — para olhar, não para alterar sem querer;
 * alterar é um clique consciente em "Editar". O que é regra (o Chefe de Setor é
 * um só, administrador só se dá entre administradores, ninguém se tranca do lado
 * de fora) é conferido no servidor; a tela só AVISA antes, para ninguém ser
 * surpreendido depois de salvar.
 */

interface Usuario {
    id: number;
    login: string;
    name: string;
    email: string;
    ativo: boolean;
    senhaDefinida: boolean;
    isGerente: boolean;
    isAdminUsuarios: boolean;
    setores: string[];
    lidera: string[];
    fiscalEm: string[];
    criadoEm: string | null;
}

interface Excluido {
    id: number;
    login: string;
    name: string;
    email: string;
    setores: string[];
    excluidoEm: string;
    remocaoEm: string | null;
    diasRestantes: number | null;
    temHistorico: boolean;
}

interface SetorOpcao {
    slug: string;
    nome: string;
    descricao: string;
}

type Aba = 'localizar' | 'excluidos' | 'registro';
type Modo = 'navegacao' | 'edicao';

const ADMINISTRADOR = 'administrador';
const CHEFE = 'chefe-de-setor';
const LIDER = 'lider-de-equipe';

interface Formulario {
    login: string;
    name: string;
    email: string;
    setores: string[];
    ativo: boolean;
    is_gerente: boolean;
    is_admin_usuarios: boolean;
}

function formularioDe(u: Usuario | null): Formulario {
    return {
        login: u?.login ?? '',
        name: u?.name ?? '',
        email: u?.email ?? '',
        setores: u?.setores ?? [],
        ativo: u?.ativo ?? true,
        is_gerente: u?.isGerente ?? false,
        is_admin_usuarios: u?.isAdminUsuarios ?? false,
    };
}

/** O que a busca reconhece como situação, além do texto livre. */
type Faceta = 'ativos' | 'inativos' | 'sem-setor' | 'pendente' | 'concluido';

const FACETAS = [
    { expressao: /\binativ\w*\b|\bdesativad\w*\b/, valor: 'inativos' as const },
    { expressao: /\bativos?\b/, valor: 'ativos' as const },
    { expressao: /\bsem (setor|cargo)\w*\b/, valor: 'sem-setor' as const },
    {
        expressao: /\b(1o |primeiro )?acesso pendente\b|\bpendentes?\b|\bsem senha\b|\bnao entrou\b/,
        valor: 'pendente' as const,
    },
    { expressao: /\bsenha definida\b|\bja entrou\b/, valor: 'concluido' as const },
];

/** Nomes como se falam: "A", "A e B", "A, B e C". */
function emLista(nomes: string[]): string {
    return nomes.length <= 1 ? (nomes[0] ?? '') : `${nomes.slice(0, -1).join(', ')} e ${nomes[nomes.length - 1]}`;
}

/** As equipes da conta, como se leem: "Líder de C1 · Fiscal em A2". */
function equipesEmTexto(u: Usuario): string {
    const partes = [
        u.lidera.length > 0 ? `Líder de ${u.lidera.join(', ')}` : null,
        u.fiscalEm.length > 0 ? `Fiscal em ${u.fiscalEm.join(', ')}` : null,
    ].filter(Boolean);

    return partes.length > 0 ? partes.join(' · ') : VAZIO;
}

export default function Usuarios({
    usuarios,
    excluidos,
    setoresOpcoes,
    diasRetencao,
    euId,
    mexeEmAdministrador,
    listagens,
}: {
    usuarios: Usuario[];
    excluidos: Excluido[];
    setoresOpcoes: SetorOpcao[];
    diasRetencao: number;
    euId: number | null;
    mexeEmAdministrador: boolean;
    listagens: Listagens;
}) {
    const gradeAtivos = listagens['usuarios.ativos'];
    const gradeExcluidos = listagens['usuarios.excluidos'];

    const [aba, setAba] = useState<Aba>('localizar');
    const [aberto, setAberto] = useState<Usuario | null>(null);
    const [modo, setModo] = useState<Modo>('edicao');
    const [form, setForm] = useState<Formulario>(() => formularioDe(null));
    const [erros, setErros] = useState<Record<string, string>>({});
    const [busca, setBusca] = useState('');
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);
    const [restaurando, setRestaurando] = useState<Excluido | null>(null);

    const { enviando, ocupado, enviar, guardar } = useEnvio();
    const acoes = useAcoes();

    const nomeDoSetor = useMemo(() => {
        const mapa = new Map(setoresOpcoes.map((s) => [s.slug, s.nome]));

        return (slug: string) => mapa.get(slug) ?? slug;
    }, [setoresOpcoes]);

    const setoresEmTexto = (slugs: string[]) =>
        slugs.length > 0 ? slugs.map(nomeDoSetor).join(', ') : 'Sem cargo';

    // ── Localizar ─────────────────────────────────────────────────────────

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return usuarios.filter((u) => {
            if (facetas.includes('ativos') && !u.ativo) {
return false;
}

            if (facetas.includes('inativos') && u.ativo) {
return false;
}

            if (facetas.includes('sem-setor') && u.setores.length > 0) {
return false;
}

            if (facetas.includes('pendente') && u.senhaDefinida) {
return false;
}

            if (facetas.includes('concluido') && !u.senhaDefinida) {
return false;
}

            // Cada termo precisa casar em ALGUM campo — "chefe" acha pelo setor
            // por extenso, "c1" acha o líder da equipe C1.
            return casaTermos(termos, [
                u.name,
                u.login,
                u.email,
                ...u.setores.map(nomeDoSetor),
                ...u.lidera,
                ...u.fiscalEm,
            ]);
        });
    }, [usuarios, busca, nomeDoSetor]);

    const ord = useOrdenacao(filtrados, { campo: 'usuario', acessor: 'name' });
    const pag = usePaginacao(ord.itens);

    const acessoresAtivos: Record<string, AcessorOrd<Usuario> | undefined> = {
        usuario: 'name',
        email: 'email',
        setores: (u) => setoresEmTexto(u.setores),
        primeiroAcesso: (u) => u.senhaDefinida,
        situacao: (u) => u.ativo,
    };

    function celulaAtivo(u: Usuario, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'usuario') {
            return {
                conteudo: (
                    <>
                        <strong>{u.name}</strong>
                        {u.id === euId && <span className="form-ajuda"> (você)</span>}
                        <br />
                        <span className="cell-id">{u.login}</span>
                    </>
                ),
                dica: `${u.name} — matrícula ${u.login}`,
            };
        }

        if (chave === 'email') {
            return { conteudo: u.email, dica: u.email };
        }

        if (chave === 'setores') {
            return u.setores.length === 0
                ? {
                      conteudo: <span className="selo selo-perigo">Sem cargo</span>,
                      dica: 'Sem cargo, a conta entra no sistema mas não abre tela nenhuma além do Início e do perfil.',
                  }
                : {
                      conteudo: setoresEmTexto(u.setores),
                      dica: setoresEmTexto(u.setores),
                  };
        }

        if (chave === 'primeiroAcesso') {
            return u.senhaDefinida
                ? {
                      conteudo: <span className="selo selo-ok">Concluído</span>,
                      dica: 'A pessoa já escolheu a própria senha.',
                  }
                : {
                      conteudo: <span className="selo selo-aviso">Pendente</span>,
                      dica: 'A pessoa ainda não definiu a senha. Abra a conta para reenviar o convite.',
                  };
        }

        return {
            conteudo: (
                <span className={cn('selo', u.ativo ? 'selo-ok' : 'selo-neutro')}>
                    {u.ativo ? <CircleCheck size={13} aria-hidden /> : <CircleSlash size={13} aria-hidden />}{' '}
                    {u.ativo ? 'Ativo' : 'Inativo'}
                </span>
            ),
        };
    }

    const linhasExportacaoAtivos = ord.itens.map((u) => ({
        usuario: u.name,
        login: u.login,
        email: u.email,
        setores: setoresEmTexto(u.setores),
        equipes: equipesEmTexto(u),
        primeiroAcesso: u.senhaDefinida ? 'Concluído' : 'Pendente',
        situacao: u.ativo ? 'Ativo' : 'Inativo',
    }));

    // ── Excluídos ─────────────────────────────────────────────────────────

    const ordExcluidos = useOrdenacao(excluidos, { campo: 'excluidoEm', dir: 'desc', acessor: 'excluidoEm' });
    const pagExcluidos = usePaginacao(ordExcluidos.itens);

    const acessoresExcluidos: Record<string, AcessorOrd<Excluido> | undefined> = {
        usuario: 'name',
        email: 'email',
        excluidoEm: 'excluidoEm',
        remocao: (e) => e.remocaoEm ?? '9999',
    };

    function textoDaRemocao(e: Excluido): string {
        if (e.temHistorico || e.remocaoEm === null) {
            return 'Não será removida — tem histórico';
        }

        const quando = e.diasRestantes === 0 ? 'hoje' : `em ${contar(e.diasRestantes ?? 0, 'dia', 'dias')}`;

        return `${dataHoraBR(e.remocaoEm)} (${quando})`;
    }

    function celulaExcluido(e: Excluido, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'usuario') {
            return {
                conteudo: (
                    <>
                        <strong>{e.name}</strong>
                        <br />
                        <span className="cell-id">{e.login}</span>
                    </>
                ),
                dica: `${e.name} — matrícula ${e.login}`,
            };
        }

        if (chave === 'email') {
            return { conteudo: e.email, dica: e.email };
        }

        if (chave === 'excluidoEm') {
            return { conteudo: dataHoraBR(e.excluidoEm), dica: dataHoraBR(e.excluidoEm) };
        }

        return e.temHistorico
            ? {
                  conteudo: <span className="selo selo-info">Não será removida — tem histórico</span>,
                  dica: 'A conta aparece em trâmites, vistorias ou decisões. Ela fica guardada, sem acesso, para o nome continuar no que fez.',
              }
            : {
                  conteudo: (
                      <span className={cn('selo', (e.diasRestantes ?? 0) <= 1 ? 'selo-perigo' : 'selo-aviso')}>
                          {textoDaRemocao(e)}
                      </span>
                  ),
                  dica: textoDaRemocao(e),
              };
    }

    const linhasExportacaoExcluidos = ordExcluidos.itens.map((e) => ({
        usuario: e.name,
        login: e.login,
        email: e.email,
        setores: setoresEmTexto(e.setores),
        excluidoEm: dataHoraBR(e.excluidoEm),
        remocao: textoDaRemocao(e),
    }));

    // ── Registro ──────────────────────────────────────────────────────────

    const novo = aberto === null;
    const somenteLeitura = modo === 'navegacao';
    const souEu = aberto !== null && aberto.id === euId;
    const podeGravar = novo ? acoes.incluir : acoes.habilitado;
    // Conta de administrador só se altera entre administradores.
    const contaTravada = aberto !== null && aberto.setores.includes(ADMINISTRADOR) && !mexeEmAdministrador;

    /** Quem hoje é Chefe de Setor e deixaria de ser se esta conta for marcada. */
    const chefesQueSaem = usuarios.filter((u) => u.setores.includes(CHEFE) && u.id !== aberto?.id).map((u) => u.name);

    function abrir(u: Usuario) {
        setAberto(u);
        setForm(formularioDe(u));
        setErros({});
        setModo('navegacao');
        setAba('registro');
    }

    function incluir() {
        if (!acoes.incluir) {
return;
}

        setAberto(null);
        setForm(formularioDe(null));
        setErros({});
        setModo('edicao');
        setAba('registro');
    }

    function voltarParaLista() {
        setAba('localizar');
        setAberto(null);
        setErros({});
    }

    function alternarSetor(slug: string) {
        if (somenteLeitura) {
return;
}

        setForm((atual) => ({
            ...atual,
            setores: atual.setores.includes(slug)
                ? atual.setores.filter((s) => s !== slug)
                : [...atual.setores, slug],
        }));
    }

    function salvar() {
        const dados = { ...form, name: form.name.trim(), email: form.email.trim(), login: form.login.trim() };
        const opcoes = {
            onSuccess: () => voltarParaLista(),
            onError: (recebidos: Record<string, string>) => setErros(recebidos),
        };

        if (aberto === null) {
            enviar('salvar', store().url, dados, opcoes);

            return;
        }

        router.put(update(aberto.id).url, dados, guardar('salvar', opcoes));
    }

    function excluir() {
        if (aberto === null) {
return;
}

        router.delete(
            destroy(aberto.id).url,
            guardar('excluir', {
                onSuccess: () => {
                    setConfirmandoExclusao(false);
                    voltarParaLista();
                },
            }),
        );
    }

    function reenviarConvite() {
        if (aberto === null) {
return;
}

        enviar('convite', convite(aberto.id).url, {});
    }

    function confirmarRestauracao() {
        if (restaurando === null) {
return;
}

        enviar('restaurar', restaurar(restaurando.id).url, {}, { onSuccess: () => setRestaurando(null) });
    }

    const listaDeErros = Object.values(erros);
    const marcouChefe = form.setores.includes(CHEFE) && !(aberto?.setores.includes(CHEFE) ?? false);
    const desmarcouChefe = !form.setores.includes(CHEFE) && (aberto?.setores.includes(CHEFE) ?? false);
    const acesso = form.setores.includes(ADMINISTRADOR)
        ? { rotulo: 'Administrador', tom: 'selo-ok' }
        : form.setores.length > 0
          ? { rotulo: 'Por cargo', tom: 'selo-info' }
          : { rotulo: 'Sem acesso a telas', tom: 'selo-perigo' };

    return (
        <>
            <Head title="Usuários" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Usuários</h1>
                    <p>
                        Quem tem conta na Retaguarda e com que cargo. O <strong>cargo</strong> define o que cada um
                        vê e faz — o que cada cargo abre é configurado no Modo Gerente. A conta nova recebe por
                        e-mail o convite para definir a senha. Excluídos ficam {contar(diasRetencao, 'dia', 'dias')}{' '}
                        na lixeira antes da remoção definitiva.
                    </p>
                </div>
            </div>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Usuários">
                    <button type="button" role="tab" className="aba" aria-selected={aba === 'localizar'} onClick={voltarParaLista}>
                        <List size={16} aria-hidden />
                        <span className="aba-rotulo">Localizar ({usuarios.length})</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'excluidos'}
                        onClick={() => {
                            setAba('excluidos');
                            setAberto(null);
                        }}
                    >
                        <UserX size={16} aria-hidden />
                        <span className="aba-rotulo">Excluídos ({excluidos.length})</span>
                    </button>
                    {(aberto !== null || acoes.incluir) && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'registro'}
                            onClick={aberto === null ? incluir : () => setAba('registro')}
                        >
                            {aberto === null ? <Plus size={16} aria-hidden /> : <Eye size={16} aria-hidden />}
                            <span className="aba-rotulo">{aberto === null ? 'Incluir' : aberto.name}</span>
                        </button>
                    )}
                </div>

                {aba === 'localizar' && (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder="Procure por nome, matrícula, e-mail, cargo ou equipe"
                            exemplos={['acesso pendente', 'sem cargo', 'inativos', 'líder de equipe']}
                        />

                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                            {acoes.incluir && (
                                <BotaoAcao icone={<Plus size={16} aria-hidden />} ocupado={ocupado} onClick={incluir}>
                                    Incluir
                                </BotaoAcao>
                            )}

                            <BotaoExportar
                                titulo="Usuários"
                                subtitulo="Sistema › Usuários"
                                contexto={busca.trim() ? `Busca: "${busca.trim()}"` : 'Todas as contas'}
                                colunas={gradeAtivos.exportacao}
                                linhas={linhasExportacaoAtivos}
                            />
                        </div>

                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela — para abrir a conta.
                            </p>
                        )}

                        <div className="table-wrap">
                            <table className="data-table enxuta">
                                <thead>
                                    <tr>
                                        <CabecaDaGrade grade={gradeAtivos.grade} ord={ord} acessores={acessoresAtivos} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td colSpan={gradeAtivos.grade.length} className="tabela-vazia">
                                                {usuarios.length === 0
                                                    ? 'Nenhuma conta cadastrada ainda.'
                                                    : 'Nenhuma conta casa com a busca. Limpe o campo para ver todas.'}
                                            </td>
                                        </tr>
                                    )}

                                    {pag.visiveis.map((u) => (
                                        <tr key={u.id} {...linhaClicavel(() => abrir(u), `Abrir a conta de ${u.name}`)}>
                                            {gradeAtivos.grade.map((coluna) => {
                                                const { conteudo, dica } = celulaAtivo(u, coluna.chave);

                                                return (
                                                    <Celula key={coluna.chave} coluna={coluna} dica={dica}>
                                                        {conteudo}
                                                    </Celula>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Paginacao {...pag.props} />
                    </>
                )}

                {aba === 'excluidos' && (
                    <>
                        <p className="form-ajuda" style={{ margin: '4px 0 12px' }}>
                            A conta excluída não entra mais no sistema. Ela é removida de vez{' '}
                            {contar(diasRetencao, 'dia', 'dias')} depois da exclusão — menos a que tem histórico
                            (trâmites, vistorias, decisões), que fica guardada para o nome continuar no que fez. Até
                            lá, clique na linha para <strong>restaurar</strong>.
                        </p>

                        <div style={{ display: 'flex', alignItems: 'center', marginBottom: 10 }}>
                            <BotaoExportar
                                titulo="Usuários excluídos"
                                subtitulo="Sistema › Usuários › Excluídos"
                                contexto="Lixeira"
                                colunas={gradeExcluidos.exportacao}
                                linhas={linhasExportacaoExcluidos}
                            />
                        </div>

                        <div className="table-wrap">
                            <table className="data-table enxuta">
                                <thead>
                                    <tr>
                                        <CabecaDaGrade grade={gradeExcluidos.grade} ord={ordExcluidos} acessores={acessoresExcluidos} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {pagExcluidos.visiveis.length === 0 && (
                                        <tr>
                                            <td colSpan={gradeExcluidos.grade.length} className="tabela-vazia">
                                                A lixeira está vazia.
                                            </td>
                                        </tr>
                                    )}

                                    {pagExcluidos.visiveis.map((e) => (
                                        <tr
                                            key={e.id}
                                            {...linhaClicavel(
                                                () => (acoes.habilitado ? setRestaurando(e) : undefined),
                                                `Restaurar a conta de ${e.name}`,
                                            )}
                                        >
                                            {gradeExcluidos.grade.map((coluna) => {
                                                const { conteudo, dica } = celulaExcluido(e, coluna.chave);

                                                return (
                                                    <Celula key={coluna.chave} coluna={coluna} dica={dica}>
                                                        {conteudo}
                                                    </Celula>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Paginacao {...pagExcluidos.props} />
                    </>
                )}

                {aba === 'registro' && (
                    <>
                        {/* Os botões do registro vêm EM CIMA, antes do formulário (dono, 25/09/2026). */}
                        <div className="rt-barra-registro">
                            <BotaoAcao className="btn btn-secondary btn-sm" icone={<Undo2 size={16} aria-hidden />} ocupado={ocupado} onClick={voltarParaLista}>
                                Voltar
                            </BotaoAcao>

                            {aberto !== null && somenteLeitura && (
                                <>
                                    {acoes.excluir && !souEu && !contaTravada && (
                                        <BotaoAcao
                                            className="btn btn-perigo btn-sm"
                                            icone={<Trash2 size={16} aria-hidden />}
                                            ocupado={ocupado}
                                            onClick={() => setConfirmandoExclusao(true)}
                                        >
                                            Excluir
                                        </BotaoAcao>
                                    )}

                                    {acoes.habilitado && !aberto.senhaDefinida && (
                                        <BotaoAcao
                                            className="btn btn-secondary btn-sm"
                                            icone={<Mail size={16} aria-hidden />}
                                            carregando={enviando === 'convite'}
                                            ocupado={ocupado}
                                            rotuloCarregando="Enviando…"
                                            onClick={reenviarConvite}
                                        >
                                            Enviar convite
                                        </BotaoAcao>
                                    )}

                                    {acoes.habilitado && !contaTravada && (
                                        <BotaoAcao icone={<Pencil size={16} aria-hidden />} ocupado={ocupado} onClick={() => setModo('edicao')}>
                                            Editar
                                        </BotaoAcao>
                                    )}
                                </>
                            )}

                            {modo === 'edicao' && podeGravar && (
                                <BotaoAcao
                                    icone={<Check size={16} aria-hidden />}
                                    carregando={enviando === 'salvar'}
                                    ocupado={ocupado}
                                    rotuloCarregando="Salvando…"
                                    onClick={salvar}
                                >
                                    {novo ? 'Criar conta e enviar convite' : 'Salvar'}
                                </BotaoAcao>
                            )}
                        </div>

                        {listaDeErros.length > 0 && (
                            <div className="form-erro" style={{ marginBottom: 16 }}>
                                <TriangleAlert size={15} aria-hidden /> Não foi possível salvar:
                                <ul style={{ margin: '6px 0 0', paddingLeft: 20 }}>
                                    {listaDeErros.map((mensagem) => (
                                        <li key={mensagem}>{mensagem}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* O resumo que o administrador precisa antes de mexer: que
                            acesso a conta tem, se a pessoa já entrou, se está ativa. */}
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 16 }}>
                            <span className={cn('selo', acesso.tom)}>
                                <ShieldCheck size={13} aria-hidden /> Acesso: {acesso.rotulo}
                            </span>
                            {!novo && (
                                <span className={cn('selo', aberto.senhaDefinida ? 'selo-ok' : 'selo-aviso')}>
                                    <KeyRound size={13} aria-hidden /> 1º acesso: {aberto.senhaDefinida ? 'concluído' : 'pendente'}
                                </span>
                            )}
                            <span className={cn('selo', form.ativo ? 'selo-ok' : 'selo-neutro')}>
                                {form.ativo ? 'Conta ativa' : 'Conta inativa'}
                            </span>
                        </div>

                        {contaTravada && (
                            <p className="form-ajuda" style={{ marginBottom: 12 }}>
                                <TriangleAlert size={14} aria-hidden /> Esta conta é de administrador: só outro
                                administrador pode alterá-la ou excluí-la.
                            </p>
                        )}

                        <p className="card-titulo" style={{ margin: '0 0 8px', fontSize: 15 }}>
                            Identificação
                        </p>

                        <div className="form-group">
                            <label className="form-label" htmlFor="usuario-login">
                                Matrícula <span aria-hidden>*</span>
                            </label>
                            <input
                                id="usuario-login"
                                className="form-control"
                                value={form.login}
                                maxLength={30}
                                disabled={!novo}
                                placeholder="ex.: maria.souza"
                                autoComplete="off"
                                onChange={(e) => setForm({ ...form, login: e.target.value })}
                            />
                            <p className="form-ajuda">
                                {novo
                                    ? 'É com ela que a pessoa entra no sistema. Não muda depois: identifica quem fez cada registro.'
                                    : 'A matrícula não muda: é ela que identifica quem fez cada registro.'}
                            </p>
                            {erros.login && <p className="form-erro">{erros.login}</p>}
                        </div>

                        <div className="form-group">
                            <label className="form-label" htmlFor="usuario-nome">
                                Nome <span aria-hidden>*</span>
                            </label>
                            <input
                                id="usuario-nome"
                                className="form-control"
                                value={form.name}
                                maxLength={255}
                                disabled={somenteLeitura}
                                placeholder="Nome completo do servidor"
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                            {erros.name && <p className="form-erro">{erros.name}</p>}
                        </div>

                        <div className="form-group">
                            <label className="form-label" htmlFor="usuario-email">
                                E-mail <span aria-hidden>*</span>
                            </label>
                            <input
                                id="usuario-email"
                                type="email"
                                className="form-control"
                                value={form.email}
                                maxLength={255}
                                disabled={somenteLeitura}
                                placeholder="nome@salvador.ba.gov.br"
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                            />
                            <p className="form-ajuda">É por ele que chegam o convite de primeiro acesso e a redefinição de senha.</p>
                            {erros.email && <p className="form-erro">{erros.email}</p>}
                        </div>

                        <p className="card-titulo" style={{ margin: '18px 0 8px', fontSize: 15 }}>
                            Cargo
                        </p>

                        {erros.setores && (
                            <p className="form-erro" style={{ marginBottom: 10 }}>
                                <TriangleAlert size={14} aria-hidden /> {erros.setores}
                            </p>
                        )}

                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 10 }}>
                            {setoresOpcoes.map((s) => {
                                const marcado = form.setores.includes(s.slug);
                                // Dar ou tirar Administrador é só de administrador; e
                                // ninguém tira de si mesmo (se trancaria do lado de fora).
                                const travado =
                                    somenteLeitura ||
                                    (s.slug === ADMINISTRADOR && (!mexeEmAdministrador || (souEu && marcado)));

                                return (
                                    <label
                                        key={s.slug}
                                        htmlFor={`setor-${s.slug}`}
                                        style={{
                                            display: 'flex',
                                            gap: 10,
                                            alignItems: 'flex-start',
                                            padding: '12px 14px',
                                            borderRadius: 10,
                                            border: `1px solid ${marcado ? 'var(--sm-primaria, #1d4ed8)' : 'var(--sm-borda, #e2e8f0)'}`,
                                            background: marcado ? 'var(--sm-primaria-suave, #eff6ff)' : 'transparent',
                                            cursor: travado ? 'default' : 'pointer',
                                            opacity: travado && !marcado ? 0.7 : 1,
                                        }}
                                    >
                                        <input
                                            id={`setor-${s.slug}`}
                                            type="checkbox"
                                            checked={marcado}
                                            disabled={travado}
                                            onChange={() => alternarSetor(s.slug)}
                                            aria-label={s.nome}
                                            aria-describedby={`setor-${s.slug}-descricao`}
                                            style={{ width: 16, height: 16, marginTop: 2 }}
                                        />
                                        <span>
                                            <strong>{s.nome}</strong>
                                            <br />
                                            <span id={`setor-${s.slug}-descricao`} className="form-ajuda" style={{ margin: 0 }}>
                                                {s.descricao}
                                            </span>
                                        </span>
                                    </label>
                                );
                            })}
                        </div>

                        {/* O Chefe de Setor é um só: quem administra precisa saber QUEM
                            sai antes de salvar, e o que acontece se ninguém ficar. */}
                        {!somenteLeitura && marcouChefe && chefesQueSaem.length > 0 && (
                            <p className="form-ajuda" style={{ marginTop: 10, color: 'var(--sm-aviso, #b45309)' }}>
                                <TriangleAlert size={14} aria-hidden /> O setor tem um Chefe de Setor só: ao salvar,{' '}
                                <strong>{emLista(chefesQueSaem)}</strong> {chefesQueSaem.length === 1 ? 'deixa' : 'deixam'} de ser
                                Chefe de Setor.
                            </p>
                        )}
                        {!somenteLeitura && desmarcouChefe && chefesQueSaem.length === 0 && (
                            <p className="form-ajuda" style={{ marginTop: 10, color: 'var(--sm-aviso, #b45309)' }}>
                                <TriangleAlert size={14} aria-hidden /> Ao salvar, o sistema fica <strong>sem Chefe de Setor</strong>:
                                a Caixa de Entrada fica sem dono até alguém ser marcado.
                            </p>
                        )}

                        <p className="card-titulo" style={{ margin: '18px 0 8px', fontSize: 15 }}>
                            Equipes
                        </p>
                        {novo ? (
                            <p className="form-ajuda">
                                A equipe do líder e a dos fiscais são vinculadas em{' '}
                                <Link href={areasEEquipes().url}>Áreas e Equipes</Link>, depois que a conta existir.
                            </p>
                        ) : (
                            <>
                                <dl className="rt-ficha">
                                    <div>
                                        <dt>Líder de</dt>
                                        <dd>{aberto.lidera.length > 0 ? aberto.lidera.join(', ') : VAZIO}</dd>
                                    </div>
                                    <div>
                                        <dt>Fiscal em</dt>
                                        <dd>{aberto.fiscalEm.length > 0 ? aberto.fiscalEm.join(', ') : VAZIO}</dd>
                                    </div>
                                </dl>
                                <p className="form-ajuda" style={{ marginTop: 6 }}>
                                    O vínculo com a equipe é feito em <Link href={areasEEquipes().url}>Áreas e Equipes</Link>.
                                    {form.setores.includes(LIDER) && aberto.lidera.length === 0 && (
                                        <>
                                            {' '}
                                            <strong>Esta conta é Líder de Equipe, mas não lidera nenhuma equipe ainda</strong> — sem
                                            isso, as Fiscalizações não chegam a ela.
                                        </>
                                    )}
                                    {!form.setores.includes(LIDER) && aberto.lidera.length > 0 && (
                                        <>
                                            {' '}
                                            <strong>Ela continua como líder de {aberto.lidera.join(', ')}</strong>, mas sem o cargo
                                            Líder de Equipe não abre as telas do líder.
                                        </>
                                    )}
                                </p>
                            </>
                        )}

                        <p className="card-titulo" style={{ margin: '18px 0 8px', fontSize: 15 }}>
                            Situação
                        </p>
                        <div className="form-group">
                            <label className="form-label" htmlFor="usuario-ativo" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                <input
                                    id="usuario-ativo"
                                    type="checkbox"
                                    checked={form.ativo}
                                    disabled={somenteLeitura || souEu}
                                    onChange={(e) => setForm({ ...form, ativo: e.target.checked })}
                                    style={{ width: 16, height: 16 }}
                                />
                                Conta ativa (pode entrar no sistema)
                            </label>
                            <p className="form-ajuda">
                                {souEu
                                    ? 'Você não pode desativar a sua própria conta.'
                                    : 'Desmarcada, a pessoa é barrada no login com o aviso de conta inativa — e o que ela registrou continua com o nome dela.'}
                            </p>
                            {erros.ativo && <p className="form-erro">{erros.ativo}</p>}
                        </div>

                        {/* As duas marcas da conta, como no Codecon. Só o administrador
                            as dá ou tira: quem administra usuários pela marca não pode
                            dar a si mesmo o poder de distribuir acesso. */}
                        <p className="card-titulo" style={{ margin: '18px 0 4px', fontSize: 15 }}>
                            Modo Gerente
                        </p>
                        <p className="form-ajuda" style={{ marginBottom: 10 }}>
                            O acesso desta conta é definido pelo <strong>cargo</strong> acima. O que cada cargo vê e faz em
                            cada tela é configurado no Modo Gerente (a chave ao lado dos itens do menu).
                        </p>
                        {erros.marcas && <p className="form-erro">{erros.marcas}</p>}
                        <div className="rt-form-linha">
                            <div className="form-group">
                                <label className="form-label" htmlFor="usuario-gerente" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                    <input
                                        id="usuario-gerente"
                                        type="checkbox"
                                        checked={form.is_gerente}
                                        disabled={somenteLeitura || !mexeEmAdministrador}
                                        onChange={(e) => setForm({ ...form, is_gerente: e.target.checked })}
                                        style={{ width: 16, height: 16 }}
                                    />
                                    Pode ativar o Modo Gerente
                                </label>
                                <p className="form-ajuda">Configura o que cada cargo vê e faz em cada tela.</p>
                            </div>
                            <div className="form-group">
                                <label className="form-label" htmlFor="usuario-admin-usuarios" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                    <input
                                        id="usuario-admin-usuarios"
                                        type="checkbox"
                                        checked={form.is_admin_usuarios}
                                        disabled={somenteLeitura || !mexeEmAdministrador}
                                        onChange={(e) => setForm({ ...form, is_admin_usuarios: e.target.checked })}
                                        style={{ width: 16, height: 16 }}
                                    />
                                    Administrador de usuários
                                </label>
                                <p className="form-ajuda">
                                    Mesmo poder de um administrador <strong>nesta tela</strong> — criar, editar, excluir,
                                    restaurar e enviar convite —, sem acesso às demais telas de administração.
                                </p>
                            </div>
                        </div>
                        {!mexeEmAdministrador && (
                            <p className="form-ajuda">Só um administrador dá ou tira estas marcas.</p>
                        )}

                        {/* Em edição, Voltar e Salvar também no pé (dono, 25/09/2026). */}
                        {modo === 'edicao' && (
                            <div className="rt-barra-registro rt-barra-registro-pe">
                                <BotaoAcao className="btn btn-secondary btn-sm" icone={<Undo2 size={16} aria-hidden />} ocupado={ocupado} onClick={voltarParaLista}>
                                    Voltar
                                </BotaoAcao>
                                {modo === 'edicao' && podeGravar && (
                                    <BotaoAcao
                                        icone={<Check size={16} aria-hidden />}
                                        carregando={enviando === 'salvar'}
                                        ocupado={ocupado}
                                        rotuloCarregando="Salvando…"
                                        onClick={salvar}
                                    >
                                        {novo ? 'Criar conta e enviar convite' : 'Salvar'}
                                    </BotaoAcao>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>

            {confirmandoExclusao && aberto !== null && (
                <ModalConfirm
                    titulo={`Excluir a conta de ${aberto.name}?`}
                    mensagem={
                        <>
                            A conta <strong>{aberto.login}</strong> vai para a aba Excluídos e deixa de entrar no sistema.
                            Se tiver histórico (trâmites, vistorias, decisões), ela fica guardada para o nome continuar no
                            que fez; se não tiver, é removida de vez em {contar(diasRetencao, 'dia', 'dias')}. Até lá, pode
                            ser restaurada.
                            {aberto.lidera.length > 0 && (
                                <>
                                    {' '}
                                    <strong>Ela é líder de {aberto.lidera.join(', ')}</strong>: enquanto estiver excluída, a
                                    equipe fica sem líder.
                                </>
                            )}
                        </>
                    }
                    rotuloConfirmar="Mover para Excluídos"
                    destrutiva
                    iconeConfirmar={<Trash2 size={16} aria-hidden />}
                    processando={enviando === 'excluir'}
                    onCancelar={() => setConfirmandoExclusao(false)}
                    onConfirmar={excluir}
                />
            )}

            {restaurando !== null && (
                <ModalConfirm
                    titulo={`Restaurar a conta de ${restaurando.name}?`}
                    mensagem={
                        <>
                            A conta <strong>{restaurando.login}</strong> volta para a lista com os mesmos cargos —{' '}
                            {setoresEmTexto(restaurando.setores)} — e as mesmas equipes, e a pessoa volta a poder entrar.
                        </>
                    }
                    rotuloConfirmar="Restaurar"
                    iconeConfirmar={<RotateCcw size={16} aria-hidden />}
                    processando={enviando === 'restaurar'}
                    onCancelar={() => setRestaurando(null)}
                    onConfirmar={confirmarRestauracao}
                />
            )}
        </>
    );
}

Usuarios.layout = {
    breadcrumbs: [
        {
            title: 'Usuários',
            href: index(),
        },
    ],
};

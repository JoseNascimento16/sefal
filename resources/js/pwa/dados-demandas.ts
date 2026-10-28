/* ============================================================================
   Trabalho DIRIGIDO: as demandas que chegam à equipe do fiscal
   ----------------------------------------------------------------------------
   Metade do serviço do fiscal chega empacotada: o canal (e-Salvador, Fala
   Salvador, avulsas…) entrega a demanda, o Chefe de Setor a encaminha ao LÍDER
   da equipe, e o líder a direciona aos fiscais — ou a anexa a uma operação. Só
   então ela aparece aqui.

   A FILA VEM DO SERVIDOR (`/app/api/kit`), já recortada: só o que está na mão
   das equipes de quem entrou, mais o que elas concluíram nos últimos 30 dias.
   O formato de `Demanda` abaixo é o contrato — o servidor o monta em
   `App\Support\Aplicativo\AplicativoParaTela::demanda()`.

   O vocabulário é o da Retaguarda, sem tradução: o canal é a chave de
   `Demanda::CANAIS` e a situação é a de `Demanda::SITUACOES`. Dois vocabulários
   para a mesma coisa divergem no primeiro ajuste.

   O despacho do fiscal leva `consideracoes` (texto) e `recomendacoes` (CHAVES
   de `RECOMENDACOES`) — a chave é o que a Retaguarda soma e traduz para a
   redação longa (`config/prototipo_denuncias.php` → `recomendacoes_do_fiscal`).
   ============================================================================ */

import { PRAZOS_NP, nomeDoDocumento } from './dados-documentos';
import { EQUIPES } from './sessao';

/**
 * Os canais por onde a demanda chega — as chaves de `Demanda::CANAIS`.
 *
 * No e-Salvador o cidadão está autenticado no portal, então o requerente vem
 * sempre identificado. No Fala Salvador o relato é a transcrição do atendimento
 * e pode ser anônimo. As avulsas são o pedido de superior e o ofício.
 */
export type Origem = 'e-salvador' | 'fala-salvador' | 'nova-licenca' | 'avulsa' | 'e-protocolo' | 'ronda';

export const ORIGENS: Record<
    Origem,
    { rotulo: string; curto: string; emoji: string; explica: string }
> = {
    'e-salvador': {
        rotulo: 'e-Salvador',
        curto: 'e-Salvador',
        emoji: '🖥️',
        explica: 'Denúncia aberta no portal do município. Quem abre está autenticado, então o requerente vem identificado.',
    },
    'fala-salvador': {
        rotulo: 'Fala Salvador',
        curto: 'Fala Salvador',
        emoji: '📞',
        explica: 'Denúncia recebida por telefone. O relato é a transcrição do atendimento, e pode ser anônima.',
    },
    'nova-licenca': {
        rotulo: 'Nova licença',
        curto: 'Licença',
        emoji: '📝',
        explica: 'Pedido de licença que precisa de vistoria no local antes da decisão.',
    },
    avulsa: {
        rotulo: 'Avulsa',
        curto: 'Avulsa',
        emoji: '📌',
        explica: 'Pedido de superior ou ofício, registrado na Retaguarda.',
    },
    'e-protocolo': {
        rotulo: 'e-Protocolo',
        curto: 'e-Protocolo',
        emoji: '📂',
        explica: 'Processo aberto no e-Protocolo do município.',
    },
    /* Não é canal: é a Fiscalização SEM denúncia (a ronda) que voltou à fila —
       o retorno da notificação lavrada nela, ou a volta que o líder determinou. */
    ronda: {
        rotulo: 'Ronda da equipe',
        curto: 'Ronda',
        emoji: '🚶',
        explica: 'Fiscalização sem denúncia, nascida na ronda da equipe, que voltou à fila: retorno da notificação ou volta determinada pelo líder.',
    },
};

/**
 * As situações da denúncia, na ordem do fluxo — o MESMO catálogo fechado da
 * Retaguarda (`prototipo_denuncias.situacoes`).
 *
 * As quatro primeiras e as duas últimas existem aqui de propósito, mesmo o
 * fiscal não as vendo: é o que prova o recorte. Denúncia em triagem, devolvida
 * ou arquivada NÃO é trabalho de campo, e some da fila sem virar tarefa de
 * ninguém.
 */
export const SITUACOES = [
    'Em pré-triagem',
    'Recebida',
    'Encaminhada ao líder',
    'Direcionada aos fiscais',
    'Em operação',
    'Em campo',
    'Aguardando regularização',
    'Retorno vencido',
    'Agrupada',
    'Concluída',
    'Devolvida',
    'Arquivada',
] as const;

export type Situacao = (typeof SITUACOES)[number];

/**
 * COMO a vistoria terminou — lista fechada, igual à da Retaguarda
 * (`prototipo_denuncias.desfechos`).
 *
 * Não é texto livre porque é o que o relatório soma: "quantas denúncias se
 * resolveram sem documento" é a pergunta que mede se a fiscalização está sendo
 * educativa. E é o desfecho que, do outro lado, fecha o passo do trâmite — se
 * as listas divergirem, o campo dirá uma coisa e a Retaguarda outra.
 */
export const DESFECHOS = [
    'Regularizado no local',
    'Nada encontrado no local',
    'Notificação Preliminar emitida',
    'Regularizado após notificação',
    'Retorno com a situação mantida',
    'Auto de Apreensão lavrado',
] as const;

export type Desfecho = (typeof DESFECHOS)[number];

/**
 * Os desfechos que cada ato pode produzir — dois recortes da MESMA lista, nunca
 * um vocabulário paralelo.
 *
 * A primeira vistoria não pode terminar em "regularizado após notificação": não
 * havia notificação. E o retorno não volta a "nada encontrado": ele existe para
 * dizer se o notificado cumpriu o prazo ou não. Oferecer os seis nos dois
 * momentos deixaria o fiscal registrar um desfecho que a Retaguarda não sabe
 * encaixar no trâmite.
 */
export const DESFECHOS_DE_VISTORIA: Desfecho[] = [
    'Regularizado no local',
    'Nada encontrado no local',
    'Notificação Preliminar emitida',
    'Auto de Apreensão lavrado',
];

export const DESFECHOS_DE_RETORNO: Desfecho[] = [
    'Regularizado após notificação',
    'Retorno com a situação mantida',
];

/**
 * O que cada desfecho quer dizer, em uma linha — o texto que o aplicativo
 * escreve embaixo da opção.
 *
 * O fiscal escolhe de pé, olhando para a barraca. "Regularizado após
 * notificação" e "Retorno com a situação mantida" são o mesmo ato com resultados
 * opostos, e a diferença entre eles decide se a denúncia encerra ou sobe ao
 * Chefe de Setor: nomear sem explicar convidaria ao toque errado.
 */
export const DESFECHOS_EXPLICADOS: Record<Desfecho, string> = {
    'Regularizado no local': 'Orientado, o ambulante desfez a montagem e saiu do local na hora. Sem documento.',
    'Nada encontrado no local': 'A equipe esteve no endereço e não constatou nada.',
    'Notificação Preliminar emitida': 'Aponta o que sanar e dá prazo. A Notificação é lavrada em seguida.',
    'Regularizado após notificação': 'O prazo foi cumprido: o ponto está resolvido. O líder lê e encaminha o encerramento.',
    'Retorno com a situação mantida': 'O prazo passou e o ponto continua igual. Volta ao líder, que decide a próxima medida.',
    'Auto de Apreensão lavrado': 'Recolhe material e mercadoria, com guarda no SEGUB.',
};

/**
 * Como o LOCAL fica depois de cada desfecho — a leitura curta do mapa e da
 * lista de registros ("regular" / "irregular").
 *
 * É DERIVADA, e não escolhida ao lado do desfecho: perguntar as duas coisas ao
 * fiscal deixaria ele registrar "Auto de Apreensão lavrado" num ponto marcado
 * como regular. Regular aqui quer dizer ponto LIBERADO — foi o que "regularizado
 * no local" e "nada encontrado" significam.
 */
export const LOCAL_APOS_O_DESFECHO: Record<Desfecho, 'regular' | 'irregular'> = {
    'Regularizado no local': 'regular',
    'Nada encontrado no local': 'regular',
    'Notificação Preliminar emitida': 'irregular',
    'Regularizado após notificação': 'regular',
    'Retorno com a situação mantida': 'irregular',
    'Auto de Apreensão lavrado': 'irregular',
};

/** O documento que cada desfecho obriga a lavrar — `null` quando não há papel. */
export const DOCUMENTO_DO_DESFECHO: Record<Desfecho, 'np' | 'aa' | null> = {
    'Regularizado no local': null,
    'Nada encontrado no local': null,
    'Notificação Preliminar emitida': 'np',
    'Regularizado após notificação': null,
    'Retorno com a situação mantida': null,
    'Auto de Apreensão lavrado': 'aa',
};

/**
 * O que impede CONCLUIR o registro — `null` quando nada impede.
 *
 * Regra do dono (04/09/2026): concluir sem documento só quando o desfecho não
 * lava papel nenhum. Desfecho que anuncia documento ("Notificação Preliminar
 * emitida", "Auto de Apreensão lavrado") e nenhum documento atrás dele deixaria
 * a Retaguarda com um passo que promete uma via que não existe — e o notificado
 * sem o papel que faz o prazo correr.
 *
 * A régua é o `DOCUMENTO_DO_DESFECHO`, e não uma segunda lista de "desfechos que
 * concluem": duas listas para a mesma decisão divergem no primeiro ajuste. E o
 * retorno segue concluindo sem papel, porque os desfechos dele não lavram nada.
 *
 * Devolve a frase pronta porque a lei do projeto é que impedimento NUNCA seja
 * silencioso: quem barra tem de dizer o motivo e o que fazer.
 */
export type Impedimento = {
    motivo: string;
    comoResolver: string;
    /** O documento que falta — a tela usa para abrir o formulário certo. */
    documento: 'np' | 'aa';
    /** "a Notificação Preliminar" / "o Auto de Apreensão", pronto para a frase. */
    nomeComArtigo: string;
};

export const impedimentoParaConcluir = (
    desfecho: Desfecho,
    documentoLavrado: boolean,
): Impedimento | null => {
    const exigido = DOCUMENTO_DO_DESFECHO[desfecho];

    if (!exigido || documentoLavrado) {
        return null;
    }

    /* O artigo vem junto do nome porque a frase é montada em três lugares e os
       dois documentos têm gêneros diferentes: sem isto, a tela escrevia "lavra
       Notificação Preliminar", que não é português de gente. */
    const nome = `${exigido === 'np' ? 'a' : 'o'} ${nomeDoDocumento(exigido)}`;

    return {
        motivo: `O desfecho “${desfecho}” lavra ${nome}, e ${exigido === 'np' ? 'ela' : 'ele'} ainda não foi lavrad${exigido === 'np' ? 'a' : 'o'} neste registro.`,
        comoResolver: `Lavre ${nome} para concluir — ou volte e troque o desfecho, se nada foi lavrado no local.`,
        documento: exigido,
        nomeComArtigo: nome,
    };
};

/**
 * As RECOMENDAÇÕES do fiscal no despacho — lista fechada, um toque cada.
 *
 * O que o Chefe de Setor e o Coordenador leem para saber o que fazer com o
 * registro é a recomendação de quem esteve no ponto. De pé na calçada ninguém
 * digita parecer, então o caminho curto é o atalho — e ele é FECHADO pelo mesmo
 * motivo do desfecho: recomendação em texto livre não se soma, e "encaminhar ao
 * SEAB" escrito de sete maneiras não vira fila de trabalho nenhuma.
 *
 * Convive com o texto livre: o atalho diz o QUE fazer, o texto diz o porquê.
 *
 * ⚠️ A lista é do dono. "Encaminhar ao SEAB" veio dele; os demais são proposta a
 * confirmar, e nenhum inventa órgão que o protótipo não conheça — o SEGUB é a
 * guarda dos bens apreendidos e o SGCI é o cadastro de comércio informal, os
 * dois já citados nos impressos.
 *
 * ⚠️ ESTA LISTA ESTÁ UNIFICADA COM A DA RETAGUARDA — e a CHAVE é o contrato.
 * A lista nasceu duas vezes, em frentes paralelas: aqui com chave e rótulo
 * curto, e na Retaguarda como frases inteiras, com outra redação e sem o SEAB.
 * O aplicativo mandava `["seab"]` e o outro lado esperava uma frase, então a
 * recomendação chegaria lá como coisa que ele não sabe ler nem SOMAR ("quantos
 * registros pediram operação na área?").
 *
 * Unificado (decisão do dono, 04/09/2026: "unifique usando a redação curta no
 * app e a explícita no retaguarda"), cada chave tem DUAS redações, e cada uma
 * mora do lado que a usa:
 *
 *   `rotulo` (aqui)  a pílula do CELULAR — de pé na calçada não cabe frase;
 *   `explicito` (lá) o que a Retaguarda mostra a quem DECIDE, em
 *                    `config/prototipo_denuncias.php` →
 *                    `recomendacoes_do_fiscal`, chave por chave.
 *
 * O que viaja entre os dois é a CHAVE, nunca o texto: assim a redação de um lado
 * não mexe no dado do outro, e é a chave que o relatório soma. **Renomear uma
 * chave aqui, ou acrescentar atalho só aqui, quebra o de-para em SILÊNCIO** —
 * nada estoura, e o Chefe de Setor lê a chave crua no lugar da recomendação (é
 * de propósito: a Retaguarda mostra a chave em vez de sumir com ela, porque
 * recomendação que evapora é pior que recomendação feia).
 *
 * ⚠️ E NENHUM TESTE ALCANÇA OS DOIS LADOS: este arquivo e o `config` da
 * Retaguarda vivem em BRANCHES diferentes (`feature/pwa-prototipo` e
 * `feature/prototipo-administrativo`), que não se veem. Cada lado só consegue
 * provar o que ele próprio declara — aqui, que nenhum registro semeado usa chave
 * fora desta lista (`tests/Feature/PwaFilaDeDenunciasTest.php`). A conferência
 * entre as duas listas é À MÃO, e o guarda é esta nota. Quando o aplicativo
 * falar com o servidor, o catálogo passa a vir de lá e esta cópia morre.
 */
const TODAS_AS_RECOMENDACOES: { id: string; rotulo: string; emoji: string }[] = [
    { id: 'retorno', rotulo: 'Sugerir retorno da equipe', emoji: '🔁' },
    { id: 'reprogramar', rotulo: 'Reprogramar a vistoria', emoji: '📅' },
    { id: 'operacao', rotulo: 'Sugerir operação na área', emoji: '🚩' },
    { id: 'passagem', rotulo: 'Manter na passagem semanal', emoji: '🔄' },
    { id: 'chefia', rotulo: 'Encaminhar ao Chefe de Setor', emoji: '⬆️' },
    { id: 'sgci', rotulo: 'Conferir cadastro no SGCI', emoji: '🗂️' },
    { id: 'segub', rotulo: 'Bens recolhidos ao SEGUB', emoji: '📦' },
    { id: 'seab', rotulo: 'Encaminhar ao SEAB', emoji: '📨' },
    { id: 'outro-orgao', rotulo: 'Encaminhar a outro órgão', emoji: '🏛️' },
    { id: 'guarda', rotulo: 'Pedir apoio da guarda municipal', emoji: '🚓' },
    { id: 'nada', rotulo: 'Sem providência adicional', emoji: '✅' },
];

/**
 * Os atalhos que o fiscal vê hoje (dono, 28/10/2026: "só SEAB por enquanto").
 * Os demais ficam na lista acima só para o aparelho ler registros antigos. O
 * servidor aceita a mesma lista (`recomendacoes_oferecidas`).
 */
export const RECOMENDACOES_OFERECIDAS = ['seab'];

export const RECOMENDACOES = TODAS_AS_RECOMENDACOES.filter((r) => RECOMENDACOES_OFERECIDAS.includes(r.id));

/**
 * A chave vira texto para quem lê o registro no APARELHO — sempre a redação
 * curta, que é a que cabe na pílula. A explícita é do outro lado.
 *
 * Chave desconhecida devolve a PRÓPRIA chave, e não "—": recomendação que
 * desaparece deixaria o fiscal reabrindo um registro que ele despachou com dois
 * atalhos e vendo um só.
 */
export const rotuloDaRecomendacao = (id: string): string =>
    TODAS_AS_RECOMENDACOES.find((r) => r.id === id)?.rotulo ?? id;

/**
 * O que cada situação cobra, e de quem — a frase que o aplicativo escreve
 * embaixo do selo.
 *
 * Sem ela o selo seria só uma palavra colorida: "Aguardando regularização" e
 * "Retorno vencido" parecem a mesma coisa, e são opostas — numa a bola está com
 * o notificado, na outra com o Chefe de Setor.
 */
export const SITUACOES_EXPLICADAS: Record<Situacao, string> = {
    'Em pré-triagem': 'Chegou pela integração e ainda está sendo consolidada. Não é trabalho de campo ainda.',
    'Recebida': 'Na mesa do Chefe de Setor. Não é trabalho de campo ainda.',
    'Encaminhada ao líder': 'Na mesa do líder da equipe, que decide quando enviar aos fiscais.',
    'Direcionada aos fiscais': 'Vistoria dirigida à sua equipe. Ir ao local e registrar o desfecho.',
    'Em operação': 'Entra na varredura da operação, em vez de uma ida isolada ao local.',
    'Em campo': 'A equipe está no caso: vistoria em andamento ou retorno já despachado ao líder.',
    'Aguardando regularização': 'Prazo da Notificação correndo — a bola está com o notificado. A equipe volta ao vencer.',
    'Retorno vencido': 'O prazo da Notificação venceu e a equipe ainda não voltou ao ponto. O retorno está atrasado e o líder foi avisado.',
    'Agrupada': 'Repete um caso que já está sendo tratado em outra demanda.',
    'Concluída': 'A vistoria teve desfecho e a demanda se encerrou.',
    'Devolvida': 'Devolvida ao canal de origem. Não chega ao campo.',
    'Arquivada': 'Arquivada. Não chega ao campo.',
};

export const TOM_DA_SITUACAO: Record<Situacao, 'ok' | 'alerta' | 'perigo' | 'info' | 'neutro'> = {
    'Em pré-triagem': 'neutro',
    'Recebida': 'neutro',
    'Encaminhada ao líder': 'neutro',
    'Direcionada aos fiscais': 'info',
    'Em operação': 'info',
    'Em campo': 'alerta',
    'Aguardando regularização': 'alerta',
    'Retorno vencido': 'perigo',
    'Agrupada': 'neutro',
    'Concluída': 'ok',
    'Devolvida': 'neutro',
    'Arquivada': 'neutro',
};

/**
 * As situações que chegam à MÃO DA EQUIPE — a régua do que o aplicativo mostra.
 *
 * As duas pontas importam. Sem esta lista o fiscal veria denúncia em triagem e
 * escolheria o próprio trabalho (é a razão de a Retaguarda não dar aquelas
 * telas a ele); e sem `Concluída`/`Retorno vencido` ele perderia de vista o que
 * a própria equipe já fez.
 */
export const SITUACOES_NA_MAO_DA_EQUIPE: Situacao[] = [
    'Direcionada aos fiscais',
    'Em operação',
    'Em campo',
    'Aguardando regularização',
    'Retorno vencido',
    'Concluída',
];

/** Ainda por fazer: a equipe precisa ir ao local (ou terminar a vistoria aberta). */
export const SITUACOES_A_VISTORIAR: Situacao[] = ['Direcionada aos fiscais', 'Em operação', 'Em campo'];

/**
 * A ficha da PERMISSÃO da SEMOP no SGCI — existe só para quem tem uma.
 *
 * Quem o sistema fiscaliza é o **ambulante**; ser **permissionário** é um
 * atributo dele. Esta ficha é a prova desse atributo: quando ela vem, o
 * ambulante é permissionário; quando não vem, ele trabalha sem permissão — que
 * é o caso da maioria, e não um cadastro faltando.
 *
 * ⚠️ Não há integração: o SGCI é o cadastro de comércio informal do município e
 * o aplicativo, hoje, não fala com ele. Aqui a ficha é DERIVADA do documento
 * lavrado na vistoria (inscrição, atividade e equipamento estão impressos nele),
 * e não inventada ao lado — inventá-la daria dois donos ao mesmo dado.
 */
export type FichaSgci = {
    inscricao: string;
    nome: string;
    atividade: string;
    equipamento: string;
    situacao: 'Ativo' | 'Suspenso' | 'Em análise';
    damEmDia: boolean;
    desde: string;
};

/** O que a equipe registrou no ponto — o passo de vistoria (ou de retorno). */
export type RegistroDeCampo = {
    /** Quando foi, em data BR — calculada do recebimento, como no outro lado. */
    quandoBr: string;
    encontrado: string;
    /** Quem estava no ponto, com a permissão quando havia. */
    ambulante: string | null;
    equipamento: string | null;
    relato: string;
    fotos: number;
    gps: string | null;
    precisaoM: number;
};

/** O documento lavrado em rua, do jeito que a fila precisa dele. */
export type DocumentoDeCampo = {
    tipo: 'np' | 'aa';
    numero: string;
    /** "NP 194903" — como o registro do turno o nomeia. */
    rotulo: string;
    lavradoBr: string;
    /** Prazo impresso na Notificação ("05 dias"); nulo no Auto de Apreensão. */
    prazoRotulo: string | null;
    /** Vencimento do prazo, em data BR — nulo quando o documento não dá prazo. */
    venceBr: string | null;
    /** Positivo = dias que faltam; negativo = dias vencidos; 0 = vence hoje. */
    venceEmDias: number | null;
    notificado: string;
    inscricao: string | null;
    atividade: string | null;
    equipamento: string | null;
    /** Chaves dos motivos assinalados — o texto mora em `dados-documentos.ts`. */
    motivos: string[];
    assinaturas: { rotulo: string; estado: 'assinada' | 'recusada' | 'pendente'; nome?: string }[];
};

export type Demanda = {
    /** `den-<id do servidor>` — o prefixo diz às telas que é uma demanda. */
    id: string;
    /** O id da demanda no servidor — é ele que vai no registro despachado. Nulo na ronda. */
    servidorId: number | null;
    /**
     * A Fiscalização SEM denúncia que voltou à fila (id `fsc-<n>`): o registro
     * vai com ela, e não com a demanda. Nulo quando o item é uma denúncia.
     */
    cicloId: number | null;
    /** Protocolo interno da denúncia (`DEN-NNNN`) — o mesmo dos dois lados. */
    protocolo: string;
    /** O número que o canal de origem deu (ESL-… no portal, 156-… no telefone). */
    documentoOrigem: string;
    origem: Origem;
    assunto: string;
    detalhe: string;
    endereco: string;
    referencia: string | null;
    bairro: string;
    /** O bairro, de novo: é por ele que o aplicativo acha a coordenada. */
    regiao: string;
    lat: number | null;
    lng: number | null;
    /** Já foi vistoriada e despachada: espera a leitura do líder, não o fiscal. */
    aguardaLider: boolean;
    /** Data BR em que a integração entregou a denúncia ao SEFAL. */
    recebidaBr: string;
    prazoBr: string;
    /** Positivo = dias que faltam; negativo = dias vencidos; 0 = vence hoje. */
    prazoDias: number;
    situacao: Situacao;
    /** A área que a triagem escolheu — `null` enquanto ela não foi triada. */
    area: string | null;
    /** A equipe que o Chefe de Setor escolheu — `null` até o direcionamento. */
    equipe: string | null;
    /** A operação a que a denúncia foi anexada, quando foi por esse caminho. */
    operacao: string | null;
    /** A chave da operação (`op-N`) — é por ela que a tela da operação acha as denúncias. */
    operacaoId?: string | null;
    /** O desfecho herdado do último passo do trâmite que declarou um. */
    desfecho: Desfecho | null;
    campo: RegistroDeCampo | null;
    documento: DocumentoDeCampo | null;
    /** Denúncia pode ser anônima: é a realidade do 156. */
    anonima: boolean;
    /** Quem abriu — `null` quando anônima, e a tela escreve "Anônimo". */
    requerente: string | null;
    /**
     * Preenchida quando o ambulante É PERMISSIONÁRIO (tem permissão no SGCI).
     * Nula quando ele não tem permissão — resposta, não lacuna.
     */
    sgci: FichaSgci | null;
};

/** A fila da equipe, como o servidor a entregou no último `kit`. */
export let DEMANDAS: Demanda[] = [];

export function carregarDemandas(lista: Demanda[]): void {
    DEMANDAS = lista;
}

/**
 * A demanda pelo id, procurada no universo INTEIRO.
 *
 * De propósito não filtra por equipe: um registro antigo do aparelho pode
 * apontar para uma denúncia que hoje não está mais na fila, e a tela de detalhe
 * precisa continuar abrindo — sumir sem explicação é pior do que mostrar.
 */
export const acharDemanda = (id: string | null): Demanda | null =>
    (id && DEMANDAS.find((d) => d.id === id)) || null;

/**
 * A ordem da fila: primeiro o que cobra ATO DO FISCAL, depois o que espera
 * outra pessoa, e por fim o que já se encerrou. Dentro de cada grupo, por prazo.
 *
 * Ordenar só por prazo — como antes — misturaria uma denúncia concluída na
 * semana passada com a vistoria que a equipe tem de fazer hoje.
 */
const PESO_DA_SITUACAO: Record<Situacao, number> = {
    /* O retorno atrasado vem antes de tudo: o prazo do notificado já passou. */
    'Retorno vencido': -1,
    'Em campo': 0,
    'Direcionada aos fiscais': 1,
    'Em operação': 2,
    'Aguardando regularização': 3,
    'Concluída': 5,
    'Em pré-triagem': 9,
    'Recebida': 9,
    'Encaminhada ao líder': 9,
    'Agrupada': 9,
    'Devolvida': 9,
    'Arquivada': 9,
};

const naOrdemDaFila = (a: Demanda, b: Demanda): number =>
    PESO_DA_SITUACAO[a.situacao] - PESO_DA_SITUACAO[b.situacao] || a.prazoDias - b.prazoDias;

/**
 * O que chega ao campo: a denúncia da MINHA EQUIPE, já na mão dela.
 *
 * As duas condições são a régua inteira. Sem a primeira, o fiscal veria o
 * serviço do vizinho e não entenderia por que um endereço fora da área dele
 * está na lista; sem a segunda, veria denúncia em triagem — e escolher o próprio
 * trabalho, ou arquivar o que não quisesse atender, é exatamente o que a
 * Retaguarda não lhe dá.
 */
export const demandasDaEquipe = (): Demanda[] =>
    DEMANDAS.filter(
        (d) => (d.equipe === null || EQUIPES.includes(d.equipe)) && SITUACOES_NA_MAO_DA_EQUIPE.includes(d.situacao),
    ).sort(naOrdemDaFila);

/** O que ainda pede ida ao local (ou o fim de uma vistoria já aberta). */
export const demandasAVistoriar = (): Demanda[] =>
    demandasDaEquipe().filter((d) => podeVistoriar(d));

/** Notificação lavrada: o prazo correndo, ou já vencido sem a equipe voltar. */
export const demandasEmRegularizacao = (): Demanda[] =>
    demandasDaEquipe().filter((d) => podeRegistrarRetorno(d));

/**
 * Vistoriadas e despachadas: esperam a leitura do LÍDER, não o fiscal. Sem este
 * bloco, a demanda sumiria da fila no instante do despacho e o fiscal acharia
 * que o registro se perdeu.
 */
export const demandasComOLider = (): Demanda[] =>
    demandasDaEquipe().filter((d) => d.aguardaLider && d.situacao !== 'Concluída');

/** O que a equipe já fechou. */
export const demandasEncerradas = (): Demanda[] =>
    demandasDaEquipe().filter((d) => d.situacao === 'Concluída');

/** Vencidas são as que ainda pedem ato do fiscal — encerrada não vence mais. */
export const demandasVencidas = (): Demanda[] =>
    demandasAVistoriar().filter((d) => d.prazoDias < 0);

/** Quantas foram para OUTRAS equipes — o recorte, em número. */
export const demandasDeOutrasEquipes = (): number =>
    DEMANDAS.filter(
        (d) => d.equipe !== null && !EQUIPES.includes(d.equipe) && SITUACOES_NA_MAO_DA_EQUIPE.includes(d.situacao),
    ).length;

/** Quantas o aplicativo guarda e NÃO mostra: triagem, devolução, arquivamento. */
export const demandasForaDoCampo = (): number =>
    DEMANDAS.filter((d) => !SITUACOES_NA_MAO_DA_EQUIPE.includes(d.situacao)).length;

export function podeVistoriar(d: Demanda): boolean {
    return !d.aguardaLider && SITUACOES_A_VISTORIAR.includes(d.situacao);
}

/* Vencido o prazo, o retorno continua sendo o ato devido — agora atrasado. */
export const podeRegistrarRetorno = (d: Demanda): boolean =>
    !d.aguardaLider && (d.situacao === 'Aguardando regularização' || d.situacao === 'Retorno vencido');

/** Os desfechos que o ato em curso pode produzir — vistoria ou retorno. */
export const desfechosOferecidos = (d: Demanda | null): Desfecho[] =>
    d && podeRegistrarRetorno(d) ? DESFECHOS_DE_RETORNO : DESFECHOS_DE_VISTORIA;

/** "Anônimo" é resposta, não espaço em branco: é a realidade do 156. */
export const rotuloDoRequerente = (d: Demanda): string =>
    d.anonima ? 'Anônimo' : (d.requerente ?? 'Não informado');

/** A frase do prazo, pronta — sem plural preguiçoso e sem conta na tela. */
export const prazoEmPalavras = (dias: number): string => {
    if (dias < -1) {
        return `vencida há ${-dias} dias`;
    }

    if (dias === -1) {
        return 'vencida há 1 dia';
    }

    if (dias === 0) {
        return 'vence hoje';
    }

    return dias === 1 ? 'vence amanhã' : `vence em ${dias} dias`;
};

/** A frase do prazo do DOCUMENTO — quem vence aqui é o prazo do notificado. */
export const prazoDoRetornoEmPalavras = (dias: number): string => {
    if (dias < -1) {
        return `prazo vencido há ${-dias} dias`;
    }

    if (dias === -1) {
        return 'prazo vencido há 1 dia';
    }

    if (dias === 0) {
        return 'prazo vence hoje';
    }

    return dias === 1 ? 'prazo vence amanhã' : `prazo vence em ${dias} dias`;
};

export const tomDoPrazo = (dias: number): 'perigo' | 'alerta' | 'info' =>
    dias < 0 ? 'perigo' : dias <= 1 ? 'alerta' : 'info';

/* A equipe mora na sessão; reexportar aqui evita que as telas que já liam a
   fila tenham de aprender um caminho novo. */
export { EQUIPE, EQUIPES, FISCAL } from './sessao';

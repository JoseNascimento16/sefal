<?php

namespace App\Support\SaudeFluxos\Fluxos;

use App\Support\SaudeFluxos\CatalogoFluxos;
use App\Support\SaudeFluxos\Fluxo;

/**
 * O aplicativo do fiscal visto de dentro (pedido do dono, 28/10/2026): tudo o que o fiscal pode
 * fazer no APP, a partir do Início, e o que o aplicativo já atende ou ainda não.
 *
 * O fluxo da Fiscalização ({@see FluxoDaFiscalizacao}) mostra o APP como um trecho do caminho da
 * demanda; este abre esse trecho: as abas, o mapa, a operação, a ronda, o levantamento de rua, a
 * vistoria até o documento impresso, o retorno da notificação e o fim do plantão. O que vem da
 * Retaguarda entra pelas bordas.
 *
 * PREVISTO (cinza pontilhado) é o que tem lugar no fluxo e o aplicativo ainda não faz: a ficha do
 * SGCI (sem integração) e o cadastro do ambulante em campo (a quarentena "Cadastrado em campo"
 * existe na Retaguarda, mas o aplicativo não a alimenta). Abrir sem sinal não é previsto: sem
 * versão instalável, por decisão do dono (13/10/2026) — está dito no quadradinho da entrada.
 *
 * FONTE ÚNICA deste conhecimento: alterou o aplicativo? Atualize ESTE arquivo no mesmo commit — a
 * tela, o texto exportado e o passo a passo saem daqui.
 */
final class FluxoDoAplicativo implements Fluxo
{
    public static function chave(): string
    {
        return 'aplicativo';
    }

    public static function definicao(): array
    {
        $a = CatalogoFluxos::ATENDIDO;
        $p = CatalogoFluxos::PREVISTO;

        return [
            'chave' => self::chave(),
            'titulo' => 'Aplicativo do fiscal',
            'subtitulo' => 'Tudo o que o fiscal faz no APP',
            'resumo' => 'Do plantão aberto com a conta da Retaguarda ao plantão encerrado: a fila da equipe, o mapa de ambulantes, o calor, a operação, a ronda e o levantamento de rua, '
                .'a vistoria no ponto com GPS, fotos e o documento impresso, o retorno da Notificação Preliminar e o envio — que, sem sinal, espera no aparelho.',
            'nos' => [
                // ── O que chega da Retaguarda
                ['id' => 'entrada-direcionada', 'rotulo' => "Demanda direcionada\npelo líder à equipe", 'tipo' => 'entrada', 'setor' => 'Líder de Equipe', 'col' => 0, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Retaguarda › Fiscalizações › Encaminhar à equipe / Incluir em operação',
                    'descricao' => 'A demanda que o líder envia aos fiscais da equipe (ou inclui numa operação) entra na fila do aplicativo, com a orientação dele, quando houver.'],
                ['id' => 'entrada-volta', 'rotulo' => "Nova vistoria\npedida pelo líder", 'tipo' => 'entrada', 'setor' => 'Líder de Equipe', 'col' => 1, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Retaguarda › Fiscalizações › Mandar a equipe voltar',
                    'descricao' => 'O líder leu o registro e mandou a equipe voltar: a demanda (ou a ronda) volta à fila do aplicativo com a justificativa dele à vista, até a nova vistoria ser feita.'],

                // ── Entrar no plantão
                ['id' => 'app-entrar', 'rotulo' => "Entrar no plantão\nmatrícula e senha", 'tipo' => 'entrada', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Aplicativo › porta',
                    'descricao' => 'O fiscal entra com a conta da Retaguarda. Quem não está em equipe é recusado dizendo o porquê. O aplicativo precisa de sinal para abrir: não há versão instalável (decisão do dono, 13/10/2026).'],
                ['id' => 'app-kit', 'rotulo' => "Kit do turno\nfila, mapa, números e impressos", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 1, 'situacao' => $a,
                    'tela' => 'Aplicativo (carregado ao entrar)',
                    'descricao' => 'O servidor entrega a fila da equipe, os registros do fiscal, os ambulantes do mapa, o calor, as operações, os bairros, os números reservados de Notificação e de Auto, a redação dos impressos, as ocorrências e os atalhos de recomendação.'],
                ['id' => 'app-inicio', 'rotulo' => "Início\no que fazer agora?", 'tipo' => 'decisao', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 2, 'situacao' => $a,
                    'tela' => 'Aplicativo › Início',
                    'descricao' => 'A equipe e a área de quem entrou, os contadores (a vistoriar, registros de hoje, retornos vencidos), as operações abertas e os atalhos para registrar avulsa e levantar a rua.'],

                // ── As frentes de trabalho
                ['id' => 'app-demandas', 'rotulo' => "Minhas demandas\nfila da equipe por ato devido", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 0, 'lin' => 3, 'situacao' => $a,
                    'tela' => 'Aplicativo › Demandas',
                    'descricao' => 'A fila dividida pelo que cabe à equipe: a vistoriar, retorno da notificação (os vencidos no topo), com o líder e já encerradas. O cartão diz por quantas denúncias o caso responde e, quando é o caso, o que o líder pediu.'],
                ['id' => 'app-detalhe', 'rotulo' => "Detalhe da demanda\nrelato, juntadas, pedido do líder", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 0, 'lin' => 4, 'situacao' => $a,
                    'tela' => 'Aplicativo › Demandas › Detalhes',
                    'descricao' => 'O que a ouvidoria recebeu, onde, como chegou à equipe, as denúncias juntadas ao caso, por que a equipe volta ao ponto, o que foi registrado e o documento lavrado.'],
                ['id' => 'app-sgci', 'rotulo' => "Ficha do SGCI\npermissão do ambulante", 'tipo' => 'etapa', 'setor' => 'Sistema', 'col' => 0, 'lin' => 5, 'situacao' => $p,
                    'tela' => 'Integração com o SGCI',
                    'descricao' => 'Previsto: mostrar no detalhe a permissão da SEMOP do ambulante (inscrição, atividade, equipamento, DAM). Sem integração com o SGCI, a tela diz "sem permissão registrada".'],
                ['id' => 'app-mapa', 'rotulo' => "Mapa de ambulantes\npinos e contorno da área", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 1, 'lin' => 3, 'situacao' => $a,
                    'tela' => 'Aplicativo › Mapa',
                    'descricao' => 'Os ambulantes do cadastro na última posição conhecida, com o contorno (ou os corredores) da área da equipe. Abre na área da equipe.'],
                ['id' => 'app-pino', 'rotulo' => "Pino do ambulante\nhistórico das idas", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 1, 'lin' => 4, 'situacao' => $a,
                    'tela' => 'Aplicativo › Mapa › pino',
                    'descricao' => 'As últimas cinco idas da fiscalização ao ponto e o retorno vencido, quando há. Registrar a partir do pino leva o ambulante identificado e move o pino para onde o fiscal esteve.'],
                ['id' => 'app-calor', 'rotulo' => "Mapa de calor\nonde a rua está quente", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 2, 'lin' => 4, 'situacao' => $a,
                    'tela' => 'Aplicativo › Calor',
                    'descricao' => 'As vistorias com irregularidade dos últimos 7, 30 ou 90 dias e as regiões mais quentes. "Fiscalizar" numa região abre o mapa nela.'],
                ['id' => 'app-operacao', 'rotulo' => "Operação\ndenúncias anexadas", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 3, 'situacao' => $a,
                    'tela' => 'Aplicativo › Início › operação',
                    'descricao' => 'A operação aberta para a equipe (ou para o fiscal), com tipo, período, bairros e as denúncias anexadas a ela. A avulsa registrada aqui fica ligada à operação.'],
                ['id' => 'app-ronda', 'rotulo' => "Registro avulso\nronda", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 4, 'lin' => 3, 'situacao' => $a,
                    'tela' => 'Aplicativo › Início › Registrar fiscalização avulsa',
                    'descricao' => 'O que o fiscal encontra andando a rua, sem demanda. O endereço é o que ele escreve; o bairro sugerido é o do catálogo mais perto do GPS. Abre uma Fiscalização própria na equipe.'],
                ['id' => 'app-registros', 'rotulo' => "Meus registros\nreabrir e reimprimir", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 6, 'lin' => 3, 'situacao' => $a,
                    'tela' => 'Aplicativo › Registros',
                    'descricao' => 'Os registros do fiscal (os do servidor e os que ainda estão no aparelho), para reabrir a conclusão e reimprimir a via.'],
                ['id' => 'app-perfil', 'rotulo' => "Perfil\nequipe, área e tema", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 7, 'lin' => 3, 'situacao' => $a,
                    'tela' => 'Aplicativo › Perfil',
                    'descricao' => 'Quem está no plantão, a equipe e a área, o tema claro ou escuro e o botão de encerrar o plantão.'],

                // ── Levantamento de rua (censo)
                ['id' => 'app-levantamento', 'rotulo' => "Levantamento de rua\nrua, bairro e equipes", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 5, 'lin' => 3, 'situacao' => $a,
                    'tela' => 'Aplicativo › Início › Levantamento de ambulantes',
                    'descricao' => 'O censo da força-tarefa numa rua: o cabeçalho com rua, bairro e equipes (os encarregados saem do cadastro das equipes). Não gera demanda nem fiscalização.'],
                ['id' => 'app-linhas', 'rotulo' => "Linhas do censo\nfoto e GPS por ambulante", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 5, 'lin' => 4, 'situacao' => $a,
                    'tela' => 'Aplicativo › Levantamento › linhas',
                    'descricao' => 'Uma linha por ambulante, numerada 001, 002…: nome, documento, contato, atividade, referência, foto do equipamento e o ponto de GPS.'],
                ['id' => 'app-concluir-levantamento', 'rotulo' => "Concluir levantamento\nou fila sem sinal", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 5, 'lin' => 5, 'situacao' => $a,
                    'tela' => 'Aplicativo › Levantamento › Concluir levantamento',
                    'descricao' => 'O levantamento sobe com as fotos e os pontos. Sem sinal, fica na fila do aparelho e sobe com o envio; recusado pelo servidor, volta à tela com o motivo.'],
                ['id' => 'fim-levantamento', 'rotulo' => "Levantamento no servidor\nprotocolo LEV", 'tipo' => 'fim', 'setor' => 'Chefe de Setor / Líder', 'col' => 5, 'lin' => 6, 'situacao' => $a,
                    'tela' => 'Retaguarda › Fiscalização › Levantamentos',
                    'descricao' => 'O censo concluído fica para consulta e exportação na Retaguarda (a planilha da rua), com o recorte do líder.'],

                // ── A vistoria
                ['id' => 'app-vistoria', 'rotulo' => "Fiscalizar no ponto\nGPS, fotos, ocorrências, relato", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 2, 'lin' => 6, 'situacao' => $a,
                    'tela' => 'Aplicativo › Fiscalizar',
                    'descricao' => 'A coordenada medida pelo GPS (sem GPS, o motivo vai junto), fotos, ocorrências marcadas, relato digitado ou ditado, o ambulante vinculado ao cadastro e, se quiser, o retorno combinado. O ditado foi conferido só no navegador, sem aparelho real.'],
                ['id' => 'app-cadastro-campo', 'rotulo' => "Cadastrar ambulante em campo\nvai à conferência do Chefe", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 4, 'lin' => 6, 'situacao' => $p,
                    'tela' => 'Aplicativo › Fiscalizar › Ambulante',
                    'descricao' => 'Previsto: cadastrar no ponto o ambulante que não está no cadastro, com a situação "Cadastrado em campo", para o Chefe de Setor conferir (aprovar, mesclar um duplicado ou corrigir). A quarentena existe na Retaguarda, mas hoje o aplicativo só vincula quem já está cadastrado.'],
                ['id' => 'app-desfecho', 'rotulo' => "Como terminou?\nlista fechada de desfechos", 'tipo' => 'decisao', 'setor' => 'APP do fiscal', 'col' => 2, 'lin' => 7, 'situacao' => $a,
                    'tela' => 'Aplicativo › Fiscalizar › Como a vistoria terminou',
                    'descricao' => 'Regularizado no local (orientado, o ambulante desfez a montagem e saiu) e nada encontrado não lavram documento. Notificação Preliminar emitida e Auto de Apreensão lavrado exigem o documento. No retorno, os desfechos são os do retorno.'],
                ['id' => 'app-documento', 'rotulo' => "Lavrar o documento\nNotificação ou Auto", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 8, 'situacao' => $a,
                    'tela' => 'Aplicativo › Lavrar a Notificação Preliminar / Auto de Apreensão',
                    'descricao' => 'O impresso com a redação do servidor e o número reservado ao fiscal: motivos, prazo e penalidades na Notificação; bens, fundamentação, guarda no SEGUB e destinação no Auto. Assinaturas (ou a recusa) colhidas na tela.'],
                ['id' => 'app-via', 'rotulo' => "Imprimir a via\ntérmica pelo Android", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 4, 'lin' => 8, 'situacao' => $a,
                    'tela' => 'Aplicativo › Via impressa',
                    'descricao' => 'A via sai pelo diálogo de impressão do Android para a térmica pareada (sem impressora, salva em PDF). Conferida só no navegador, sem aparelho real.'],
                ['id' => 'app-conclusao', 'rotulo' => "Conclusão do registro\nconsiderações e atalho", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 2, 'lin' => 9, 'situacao' => $a,
                    'tela' => 'Aplicativo › Conclusão do registro',
                    'descricao' => 'As considerações finais e o atalho de recomendação (hoje só "Encaminhar ao SEAB"). Não conclui sem o documento que o desfecho manda lavrar — o impedimento diz o motivo e abre o formulário.'],
                ['id' => 'app-envio', 'rotulo' => "Enviar ao servidor\nou fila no aparelho", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 2, 'lin' => 10, 'situacao' => $a,
                    'tela' => 'Aplicativo › Enviar',
                    'descricao' => 'O registro sobe ao servidor com as fotos; sem sinal, fica no aparelho e sobe quando o sinal volta (a aba Enviar mostra o que falta e manda agora). O reenvio não duplica, e o que o servidor recusa volta com o motivo.'],
                ['id' => 'fim-lider', 'rotulo' => "Registro com o líder\nna Retaguarda", 'tipo' => 'fim', 'setor' => 'Líder de Equipe', 'col' => 1, 'lin' => 11, 'situacao' => $a,
                    'tela' => 'Retaguarda › Fiscalizações; Aplicativo › Demandas › Com o líder',
                    'descricao' => 'O registro entra na Fiscalização e espera a leitura do líder, que manda a equipe voltar ou encaminha ao Chefe de Setor. No aplicativo, a demanda aparece "Com o líder".'],

                // ── Retorno da Notificação Preliminar
                ['id' => 'app-aguardando', 'rotulo' => "Aguardando regularização\nprazo da Notificação", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 11, 'situacao' => $a,
                    'tela' => 'Aplicativo › Demandas › Retorno da notificação',
                    'descricao' => 'Lavrada a Notificação Preliminar, o caso fica com o notificado até o fim do prazo, sem passar pelo líder, e segue na fila com o prazo da notificação. Vale para a demanda e para a ronda.'],
                ['id' => 'app-retorno', 'rotulo' => "Registrar o retorno\nregularizado ou mantida", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 13, 'situacao' => $a,
                    'tela' => 'Aplicativo › Registrar retorno',
                    'descricao' => 'No fim do prazo, a equipe volta ao ponto e registra "Regularizado após notificação" ou "Retorno com a situação mantida". O retorno vai ao líder.'],
                ['id' => 'app-retorno-vencido', 'rotulo' => "Retorno vencido\nno topo da fila", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 4, 'lin' => 12, 'situacao' => $a,
                    'tela' => 'Aplicativo › Demandas; Retaguarda › Fiscalizações (a decidir)',
                    'descricao' => 'No dia seguinte ao fim do prazo sem retorno, o caso vira "Retorno vencido": sobe ao topo da fila, em vermelho, e alerta o líder. O retorno continua possível.'],

                // ── Fim do plantão
                ['id' => 'fim-plantao', 'rotulo' => "Plantão encerrado\navisa o que ficou no aparelho", 'tipo' => 'fim', 'setor' => 'APP do fiscal', 'col' => 7, 'lin' => 4, 'situacao' => $a,
                    'tela' => 'Aplicativo › Perfil › Encerrar plantão e sair',
                    'descricao' => 'O aparelho é compartilhado: se há registro esperando sinal ou registro aberto, o aplicativo diz quantos ficaram só no aparelho e oferece enviar agora, sair assim mesmo ou continuar no plantão.'],
            ],
            'arestas' => [
                ['de' => 'entrada-direcionada', 'para' => 'app-demandas'],
                ['de' => 'entrada-volta', 'para' => 'app-demandas', 'rotulo' => 'com a justificativa'],
                ['de' => 'app-entrar', 'para' => 'app-kit'],
                ['de' => 'app-kit', 'para' => 'app-inicio'],
                ['de' => 'app-inicio', 'para' => 'app-demandas', 'rotulo' => 'Demandas'],
                ['de' => 'app-inicio', 'para' => 'app-mapa', 'rotulo' => 'Mapa'],
                ['de' => 'app-inicio', 'para' => 'app-calor'],
                ['de' => 'app-inicio', 'para' => 'app-operacao'],
                ['de' => 'app-inicio', 'para' => 'app-ronda'],
                ['de' => 'app-inicio', 'para' => 'app-levantamento'],
                ['de' => 'app-inicio', 'para' => 'app-registros', 'rotulo' => 'Registros'],
                ['de' => 'app-inicio', 'para' => 'app-perfil'],
                ['de' => 'app-demandas', 'para' => 'app-detalhe', 'rotulo' => 'Detalhes'],
                ['de' => 'app-detalhe', 'para' => 'app-sgci'],
                ['de' => 'app-detalhe', 'para' => 'app-vistoria', 'rotulo' => 'Fiscalizar'],
                ['de' => 'app-mapa', 'para' => 'app-pino'],
                ['de' => 'app-pino', 'para' => 'app-vistoria', 'rotulo' => 'registrar no pino'],
                ['de' => 'app-calor', 'para' => 'app-mapa', 'rotulo' => 'fiscalizar a região'],
                ['de' => 'app-operacao', 'para' => 'app-vistoria', 'rotulo' => 'avulsa na operação'],
                ['de' => 'app-ronda', 'para' => 'app-vistoria'],
                ['de' => 'app-levantamento', 'para' => 'app-linhas'],
                ['de' => 'app-linhas', 'para' => 'app-concluir-levantamento'],
                ['de' => 'app-concluir-levantamento', 'para' => 'fim-levantamento'],
                ['de' => 'app-vistoria', 'para' => 'app-cadastro-campo', 'rotulo' => 'ambulante sem cadastro'],
                ['de' => 'app-vistoria', 'para' => 'app-desfecho'],
                ['de' => 'app-desfecho', 'para' => 'app-conclusao', 'rotulo' => 'sem documento'],
                ['de' => 'app-desfecho', 'para' => 'app-documento', 'rotulo' => 'Notificação ou Auto'],
                ['de' => 'app-documento', 'para' => 'app-via'],
                ['de' => 'app-documento', 'para' => 'app-conclusao'],
                ['de' => 'app-registros', 'para' => 'app-conclusao', 'rotulo' => 'reabrir'],
                ['de' => 'app-conclusao', 'para' => 'app-envio'],
                ['de' => 'app-envio', 'para' => 'fim-lider', 'rotulo' => 'demais desfechos'],
                ['de' => 'app-envio', 'para' => 'app-aguardando', 'rotulo' => 'Notificação Preliminar'],
                ['de' => 'app-aguardando', 'para' => 'app-retorno', 'rotulo' => 'fim do prazo'],
                ['de' => 'app-aguardando', 'para' => 'app-retorno-vencido', 'rotulo' => 'prazo passou'],
                ['de' => 'app-retorno-vencido', 'para' => 'app-retorno', 'rotulo' => 'retorno atrasado'],
                ['de' => 'app-retorno', 'para' => 'app-envio', 'rotulo' => 'despachar o retorno'],
                ['de' => 'app-perfil', 'para' => 'fim-plantao', 'rotulo' => 'encerrar'],
            ],
            'etapas' => [
                ['setor' => 'APP do fiscal', 'titulo' => 'Entrar no plantão', 'nos' => ['app-entrar', 'app-kit', 'app-inicio'], 'itens' => [
                    'O fiscal entra com a **conta da Retaguarda**; quem não está em equipe é recusado com o motivo. O aplicativo precisa de **sinal para abrir** (não há versão instalável).',
                    'Ao entrar, recebe o **kit do turno**: a fila da equipe, o mapa, o calor, as operações, os **números reservados** de Notificação e de Auto e a **redação dos impressos**.',
                    'O **Início** mostra a equipe e a área, os contadores e os atalhos de cada frente de trabalho.',
                ]],
                ['setor' => 'APP do fiscal', 'titulo' => 'A fila da equipe', 'nos' => ['entrada-direcionada', 'entrada-volta', 'app-demandas', 'app-detalhe', 'app-sgci'], 'itens' => [
                    'Chega à fila o que o **líder direcionou** à equipe e o que ele **mandou refazer**, com a justificativa.',
                    'A fila é dividida pelo **ato devido**: a vistoriar, retorno da notificação, com o líder, encerradas. O cartão diz por **quantas denúncias** o caso responde.',
                    'O detalhe mostra o relato, como o caso chegou, as denúncias juntadas e o pedido do líder. A **ficha do SGCI** está prevista: sem integração, não aparece.',
                ]],
                ['setor' => 'APP do fiscal', 'titulo' => 'Mapa, calor, operação e ronda', 'nos' => ['app-mapa', 'app-pino', 'app-calor', 'app-operacao', 'app-ronda'], 'itens' => [
                    'O **mapa** mostra os ambulantes na última posição e a área da equipe; registrar a partir do **pino** leva o ambulante identificado.',
                    'O **calor** mostra onde houve irregularidade nos últimos 7, 30 ou 90 dias e leva ao mapa da região.',
                    'Na **operação**, o fiscal vê as denúncias anexadas e registra a avulsa ligada a ela. O **registro avulso** (ronda) é o que ele encontra andando a rua, sem demanda.',
                ]],
                ['setor' => 'APP do fiscal', 'titulo' => 'A vistoria no ponto', 'nos' => ['app-vistoria', 'app-cadastro-campo', 'app-desfecho', 'app-documento', 'app-via', 'app-conclusao', 'app-envio', 'fim-lider'], 'itens' => [
                    'O registro leva a **coordenada medida pelo GPS** (ou o motivo de não haver), fotos, ocorrências e o relato, digitado ou **ditado**.',
                    'O ambulante é vinculado a quem já está no cadastro. **Cadastrar no ponto** quem não está (a quarentena "Cadastrado em campo") está previsto.',
                    'O **desfecho** é de uma lista fechada; Notificação Preliminar e Auto de Apreensão são **lavrados** com o número reservado, e a **via sai na térmica** pelo diálogo de impressão do Android.',
                    'Na **conclusão**, as considerações e o atalho de recomendação. O registro **sobe ao servidor** ou espera no aparelho até o sinal voltar, e vai ao **líder**.',
                ]],
                ['setor' => 'APP do fiscal', 'titulo' => 'O retorno da Notificação Preliminar', 'nos' => ['app-aguardando', 'app-retorno', 'app-retorno-vencido'], 'itens' => [
                    'Com a Notificação Preliminar, o caso fica **aguardando regularização** até o fim do prazo, sem passar pelo líder.',
                    'No fim do prazo, a equipe **registra o retorno**: regularizado ou situação mantida. O retorno vai ao líder.',
                    'Prazo passado sem retorno vira **retorno vencido**: topo da fila e alerta ao líder.',
                ]],
                ['setor' => 'APP do fiscal', 'titulo' => 'O levantamento de rua', 'nos' => ['app-levantamento', 'app-linhas', 'app-concluir-levantamento', 'fim-levantamento'], 'itens' => [
                    'O **censo por rua**: o cabeçalho (rua, bairro, equipes) e uma linha por ambulante, com **foto e GPS**.',
                    'Concluído, sobe ao servidor — ou espera no aparelho sem sinal — e fica para **consulta e exportação** na Retaguarda. Não gera demanda nem fiscalização.',
                ]],
                ['setor' => 'APP do fiscal', 'titulo' => 'Registros e fim do plantão', 'nos' => ['app-registros', 'app-perfil', 'fim-plantao'], 'itens' => [
                    'Em **Meus registros**, o fiscal reabre a conclusão e reimprime a via.',
                    'Ao **encerrar o plantão**, o aplicativo avisa o que ficou só no aparelho e oferece enviar antes de sair.',
                ]],
            ],
            'conclusoes' => [
                ['conclusao' => 'Registro com o líder', 'quem' => 'Fiscal', 'caminho' => 'Vistoria (dirigida, avulsa, no pino ou na operação) concluída e enviada; o retorno da notificação também chega aqui.'],
                ['conclusao' => 'Aguardando regularização', 'quem' => 'Notificado', 'caminho' => 'Vistoria com Notificação Preliminar: o caso espera o fim do prazo para o retorno.'],
                ['conclusao' => 'Levantamento no servidor', 'quem' => 'Fiscal', 'caminho' => 'Censo por rua concluído, para consulta e exportação na Retaguarda.'],
                ['conclusao' => 'Plantão encerrado', 'quem' => 'Fiscal', 'caminho' => 'Saída pelo Perfil, com o aviso do que ficou no aparelho.'],
            ],
        ];
    }
}

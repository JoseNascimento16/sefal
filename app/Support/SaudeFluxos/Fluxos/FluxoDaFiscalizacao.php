<?php

namespace App\Support\SaudeFluxos\Fluxos;

use App\Support\SaudeFluxos\CatalogoFluxos;
use App\Support\SaudeFluxos\Fluxo;

/**
 * O fluxo de trabalho do SEFAL de ponta a ponta (pedido do dono, 27/10/2026): de cada canal por onde
 * a demanda chega — e da ronda e do levantamento que o fiscal faz por conta própria — até cada
 * destino final, passando pela mesa do Chefe de Setor, pela Fiscalização do líder e pelo aplicativo.
 *
 * Cada quadradinho diz se o trecho é ATENDIDO hoje ou só PREVISTO. Previsto, aqui, é o que tem lugar
 * no fluxo mas o sistema ainda não percorre: a leitura automática do e-Salvador, o envio da resposta
 * pela API dele e o retorno da Notificação Preliminar (a demanda principal hoje não chega a
 * "Aguardando regularização", e nada grava "Retorno vencido").
 *
 * FONTE ÚNICA deste conhecimento: alterou o fluxo? Atualize ESTE arquivo no mesmo commit — a tela, o
 * texto exportado e o passo a passo saem daqui.
 */
final class FluxoDaFiscalizacao implements Fluxo
{
    public static function chave(): string
    {
        return 'fiscalizacao';
    }

    public static function definicao(): array
    {
        $a = CatalogoFluxos::ATENDIDO;
        $p = CatalogoFluxos::PREVISTO;

        return [
            'chave' => self::chave(),
            'titulo' => 'Fiscalização de ambulantes',
            'subtitulo' => 'Da chegada da demanda ao destino final',
            'resumo' => 'Como a demanda chega — e-Salvador, e-Protocolo, Avulsa, Fala Salvador —, passa pela mesa do Chefe de Setor, vira Fiscalização com o líder, '
                .'vai à rua pelo aplicativo do fiscal (ou por uma operação), volta ao líder e ao Chefe e termina respondida ao canal, com processo aberto, '
                .'encerrada, devolvida ou arquivada. A ronda e o levantamento de ambulantes nascem no próprio aplicativo.',
            'nos' => [
                // ── Entradas
                ['id' => 'entrada-esalvador-api', 'rotulo' => "e-Salvador\nleitura automática", 'tipo' => 'entrada', 'setor' => 'Canal externo', 'col' => 0, 'lin' => 0, 'situacao' => $p,
                    'tela' => 'Integração com a API do e-Salvador',
                    'descricao' => 'A leitura automática dos processos do e-Salvador ainda não existe: a integração só consulta, e a caixa da credencial não é a da SEFAL. Quando existir, o que chegar por ela passa pela Pré-triagem.'],
                ['id' => 'entrada-esalvador', 'rotulo' => "e-Salvador\ncadastro pelo Chefe", 'tipo' => 'entrada', 'setor' => 'Chefe de Setor', 'col' => 1, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › e-Salvador (abas Denúncias e Licenças)',
                    'descricao' => 'O Chefe de Setor digita o processo do e-Salvador — denúncia ou pedido de nova licença — com o número de origem. A demanda nasce "Recebida", com prazo de 10 dias.'],
                ['id' => 'entrada-eprotocolo', 'rotulo' => "e-Protocolo\ncadastro pelo Chefe", 'tipo' => 'entrada', 'setor' => 'Chefe de Setor', 'col' => 2, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › e-Protocolo',
                    'descricao' => 'O atendimento presencial registrado no e-Protocolo é digitado pelo Chefe de Setor. A demanda nasce "Recebida".'],
                ['id' => 'entrada-avulsa', 'rotulo' => "Avulsa\npedido de superior ou ofício", 'tipo' => 'entrada', 'setor' => 'Chefe de Setor', 'col' => 3, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › Avulsas',
                    'descricao' => 'Pedido de superior (por ligação ou e-mail) ou ofício de órgão ou do Ministério Público, digitado pelo Chefe de Setor. A demanda nasce "Recebida".'],
                ['id' => 'entrada-fala', 'rotulo' => "Fala Salvador (156)\nregistro do Chefe ou do líder", 'tipo' => 'entrada', 'setor' => 'Chefe de Setor / Líder', 'col' => 4, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › Fala Salvador',
                    'descricao' => 'A ocorrência do 156 é digitada — não há integração. Registrada pelo Chefe, nasce "Recebida"; registrada pelo líder, já nasce "Encaminhada ao líder", com a Fiscalização da equipe dele aberta.'],
                ['id' => 'entrada-operacao', 'rotulo' => "Operação\nCadastro de Operação", 'tipo' => 'entrada', 'setor' => 'Chefe de Setor / Líder', 'col' => 5, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Operações › Cadastro de Operação',
                    'descricao' => 'A operação planejada, com período, bairros e fiscais de qualquer área. Recebe demandas abertas anexadas, que passam a "Em operação". A situação segue o período: planejada, em andamento, encerrada.'],
                ['id' => 'entrada-ronda', 'rotulo' => "Ronda do fiscal\nfiscalização avulsa", 'tipo' => 'entrada', 'setor' => 'APP do fiscal', 'col' => 6, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Aplicativo › Início › Registrar fiscalização avulsa (ou pelo pino do mapa)',
                    'descricao' => 'O fiscal registra o que encontra andando a rua, sem demanda. A vistoria abre uma Fiscalização própria na equipe dele.'],
                ['id' => 'entrada-levantamento', 'rotulo' => "Levantamento\ncenso de ambulantes por rua", 'tipo' => 'entrada', 'setor' => 'APP do fiscal', 'col' => 7, 'lin' => 0, 'situacao' => $a,
                    'tela' => 'Aplicativo › Início › Levantamento de ambulantes',
                    'descricao' => 'O censo de quem trabalha em cada ponto de uma rua, com foto e GPS por linha. Não gera demanda nem fiscalização.'],

                // ── Mesa do Chefe de Setor
                ['id' => 'pre-triagem', 'rotulo' => "Pré-triagem\njuntar demandas repetidas", 'tipo' => 'etapa', 'setor' => 'Chefe de Setor', 'col' => 0, 'lin' => 1, 'situacao' => $a,
                    'tela' => 'Mesa do Chefe de Setor › Pré-triagem',
                    'descricao' => 'O Chefe procura repetições e junta num caso só as demandas que falam do mesmo fato; as agregadas acompanham a principal. Só o que chega pela integração passa por aqui — hoje, nada chega.'],
                ['id' => 'recebida', 'rotulo' => "Recebida\nmesa do Chefe de Setor", 'tipo' => 'etapa', 'setor' => 'Chefe de Setor', 'col' => 2, 'lin' => 2, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › (o canal)',
                    'descricao' => 'A demanda espera a decisão do Chefe de Setor, com a equipe sugerida pelo bairro do endereço.'],
                ['id' => 'triagem', 'rotulo' => "Triagem do Chefe\nencaminhar, devolver ou arquivar", 'tipo' => 'decisao', 'setor' => 'Chefe de Setor', 'col' => 2, 'lin' => 3, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › Encaminhar à equipe / Devolver ou arquivar',
                    'descricao' => 'Uma a uma ou em lote: encaminha à equipe (a sugerida pelo bairro, ou outra) ou retira do fluxo, devolvendo ou arquivando com motivo e justificativa.'],

                // ── Líder de Equipe
                ['id' => 'encaminhada-lider', 'rotulo' => "Encaminhada ao líder\nFiscalização aberta", 'tipo' => 'etapa', 'setor' => 'Líder de Equipe', 'col' => 3, 'lin' => 4, 'situacao' => $a,
                    'tela' => 'Fiscalização › Fiscalizações › Em andamento',
                    'descricao' => 'Encaminhar abre a Fiscalização (o ciclo da demanda) com a equipe, aguardando o líder enviá-la à rua.'],
                ['id' => 'decisao-lider', 'rotulo' => "Como vai à rua?\nlíder de equipe", 'tipo' => 'decisao', 'setor' => 'Líder de Equipe', 'col' => 3, 'lin' => 5, 'situacao' => $a,
                    'tela' => 'Fiscalizações › Encaminhar à equipe / Incluir em operação / Encaminhar ao Chefe de Setor',
                    'descricao' => 'O líder envia aos fiscais da equipe, inclui numa operação aberta ou devolve o caso ao Chefe sem vistoria.'],
                ['id' => 'direcionada', 'rotulo' => 'Direcionada aos fiscais', 'tipo' => 'etapa', 'setor' => 'Líder de Equipe', 'col' => 2, 'lin' => 6, 'situacao' => $a,
                    'tela' => 'Fiscalizações (com orientação opcional do líder)',
                    'descricao' => 'A demanda entra na fila do aplicativo dos fiscais da equipe.'],
                ['id' => 'em-operacao', 'rotulo' => "Em operação\ndemandas anexadas", 'tipo' => 'etapa', 'setor' => 'Líder de Equipe', 'col' => 4, 'lin' => 6, 'situacao' => $a,
                    'tela' => 'Operações › Cadastro de Operação; Aplicativo › Operação',
                    'descricao' => 'A demanda vai a campo com a operação: entra na fila dos fiscais da operação e aparece na tela da operação no aplicativo. Na tela da operação, o fiscal também registra a avulsa ligada a ela.'],

                // ── Aplicativo do fiscal
                ['id' => 'app-entrada', 'rotulo' => "APP · entrar no plantão\nkit do turno", 'tipo' => 'entrada', 'setor' => 'APP do fiscal', 'col' => 1, 'lin' => 7, 'situacao' => $a,
                    'tela' => 'Aplicativo › porta (matrícula e senha da Retaguarda)',
                    'descricao' => 'O fiscal entra com a conta da Retaguarda (quem não está em equipe é recusado com o motivo) e recebe o kit do turno: a fila da equipe, os registros dele e os números reservados de Notificação e de Auto.'],
                ['id' => 'app-fila', 'rotulo' => "APP · fila da equipe\nA vistoriar", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 7, 'situacao' => $a,
                    'tela' => 'Aplicativo › Demandas',
                    'descricao' => 'As demandas na mão da equipe, divididas pelo ato devido: a vistoriar, aguardando regularização, com o líder, já encerradas.'],
                ['id' => 'app-vistoria', 'rotulo' => "APP · vistoria no ponto\nGPS, fotos, relato", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 8, 'situacao' => $a,
                    'tela' => 'Aplicativo › Fiscalizar',
                    'descricao' => 'O fiscal registra o ponto: coordenada medida pelo GPS (ou o motivo de não haver), fotos, ocorrências, relato (digitado ou ditado) e, se quiser, o retorno combinado.'],
                ['id' => 'app-desfecho', 'rotulo' => "Desfecho\nlista fechada", 'tipo' => 'decisao', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 9, 'situacao' => $a,
                    'tela' => 'Aplicativo › Fiscalizar › O que aconteceu',
                    'descricao' => 'Regularizado no local, nada encontrado, Notificação Preliminar emitida ou Auto de Apreensão lavrado. Os dois últimos exigem o documento.'],
                ['id' => 'app-documento', 'rotulo' => "APP · Notificação ou Auto\nnúmero reservado, via impressa", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 4, 'lin' => 10, 'situacao' => $a,
                    'tela' => 'Aplicativo › Lavrar notificação / Auto de Apreensão › Imprimir a via',
                    'descricao' => 'O documento sai com um número reservado ao fiscal e a via é impressa na térmica pelo diálogo de impressão do aparelho. A conclusão do registro fica barrada até o documento ser lavrado.'],
                ['id' => 'app-concluir', 'rotulo' => "APP · concluir o registro\nfila no aparelho sem sinal", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 3, 'lin' => 11, 'situacao' => $a,
                    'tela' => 'Aplicativo › Conclusão do registro',
                    'descricao' => 'Com as considerações e as recomendações, o registro sobe ao servidor — ou fica guardado no aparelho, com as fotos, e sobe quando o sinal volta. O servidor recusa, com o motivo, o que não pode gravar.'],

                // ── Retorno da notificação (previsto)
                ['id' => 'aguardando-regularizacao', 'rotulo' => "Aguardando regularização\nprazo da notificação", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 5, 'lin' => 11, 'situacao' => $p,
                    'tela' => 'Aplicativo › Demandas › Aguardando regularização',
                    'descricao' => 'A demanda com Notificação Preliminar correndo prazo. O aplicativo já tem o bloco, mas hoje a demanda principal não chega a esta situação — só as agregadas, pelo desfecho da principal.'],
                ['id' => 'retorno-app', 'rotulo' => "APP · registrar retorno\nregularizado ou mantido", 'tipo' => 'etapa', 'setor' => 'APP do fiscal', 'col' => 5, 'lin' => 12, 'situacao' => $p,
                    'tela' => 'Aplicativo › Registrar retorno',
                    'descricao' => 'No fim do prazo, o fiscal confere se o notificado cumpriu: "Regularizado após notificação" ou "Retorno com a situação mantida". Pronto no aplicativo, sem caminho que leve até ele.'],
                ['id' => 'retorno-vencido', 'rotulo' => "Retorno vencido\npróxima medida do Chefe", 'tipo' => 'etapa', 'setor' => 'Chefe de Setor', 'col' => 5, 'lin' => 13, 'situacao' => $p,
                    'tela' => '—',
                    'descricao' => 'Notificação não cumprida sobe ao Chefe de Setor para a próxima medida. A situação existe no catálogo, mas nada a grava ainda.'],

                // ── Volta da rua
                ['id' => 'vistoria-gravada', 'rotulo' => "Vistoria gravada\naguardando leitura do líder", 'tipo' => 'etapa', 'setor' => 'Sistema', 'col' => 3, 'lin' => 12, 'situacao' => $a,
                    'tela' => 'Fiscalizações (contador "a decidir"); Aplicativo › Demandas › Com o líder',
                    'descricao' => 'A vistoria entra na Fiscalização aguardando a leitura do líder, e a demanda passa a "Em campo". O reenvio do mesmo registro não duplica.'],
                ['id' => 'lider-le', 'rotulo' => "Líder lê o retorno\nvoltar ou subir ao Chefe?", 'tipo' => 'decisao', 'setor' => 'Líder de Equipe', 'col' => 3, 'lin' => 13, 'situacao' => $a,
                    'tela' => 'Fiscalizações › Mandar a equipe voltar / Encaminhar ao Chefe de Setor',
                    'descricao' => 'O líder lê relato, fotos, ocorrências e documento. Manda a equipe voltar (nova vistoria) ou encaminha ao Chefe de Setor, com justificativa.'],
                ['id' => 'encaminhada-chefe', 'rotulo' => "Encaminhada ao Chefe\nFiscalizações › Encaminhadas", 'tipo' => 'etapa', 'setor' => 'Chefe de Setor', 'col' => 3, 'lin' => 14, 'situacao' => $a,
                    'tela' => 'Fiscalizações › Encaminhadas',
                    'descricao' => 'A Fiscalização vai para Encaminhadas e a demanda volta à mesa do Chefe ("Em fiscalização"). As demandas agregadas recebem o efeito do desfecho.'],

                // ── Deliberação e destinos
                ['id' => 'chefe-delibera', 'rotulo' => "Deliberação do Chefe\nna Caixa de Entrada", 'tipo' => 'decisao', 'setor' => 'Chefe de Setor', 'col' => 3, 'lin' => 15, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › (o canal) › detalhe da demanda',
                    'descricao' => 'Com a vistoria feita, o Chefe responde à origem, encaminha de novo à equipe (abre outra Fiscalização) ou devolve/arquiva. Sem vistoria, só reencaminha, devolve ou arquiva.'],
                ['id' => 'envio-api', 'rotulo' => "Resposta pela API\ndo e-Salvador", 'tipo' => 'etapa', 'setor' => 'Sistema', 'col' => 5, 'lin' => 15, 'situacao' => $p,
                    'tela' => 'Integração com a API do e-Salvador',
                    'descricao' => 'Enviar a resposta, ou a abertura do processo, direto pela API do e-Salvador. Desligado: hoje o registro é local e o ato é feito à mão no sistema de origem.'],
                ['id' => 'fim-devolvida', 'rotulo' => "Devolvida\nao remetente", 'tipo' => 'fim', 'setor' => 'Chefe de Setor', 'col' => 0, 'lin' => 16, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › Devolver ou arquivar',
                    'descricao' => 'Retirada do fluxo com motivo, justificativa e destino da devolução. Fica registrada; nada é enviado ao canal.'],
                ['id' => 'fim-arquivada', 'rotulo' => 'Arquivada', 'tipo' => 'fim', 'setor' => 'Chefe de Setor', 'col' => 1, 'lin' => 16, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › Devolver ou arquivar',
                    'descricao' => 'Retirada do fluxo com motivo e justificativa.'],
                ['id' => 'fim-respondida', 'rotulo' => "Respondida ao canal\nConcluída · Arquivo", 'tipo' => 'fim', 'setor' => 'Chefe de Setor', 'col' => 2, 'lin' => 16, 'situacao' => $a,
                    'tela' => 'Caixa de Entrada › Registrar resposta; aba Respondidas',
                    'descricao' => 'e-Salvador, nova licença, Fala Salvador e e-Protocolo: o Chefe registra a resposta dada à origem. A demanda fica "Concluída" e as Fiscalizações dela vão para o Arquivo.'],
                ['id' => 'fim-processo', 'rotulo' => "Processo aberto\navulsa", 'tipo' => 'fim', 'setor' => 'Chefe de Setor', 'col' => 3, 'lin' => 16, 'situacao' => $a,
                    'tela' => 'Avulsas › detalhe › Registrar abertura do processo',
                    'descricao' => 'Na avulsa, o Chefe registra o número do processo aberto no e-Salvador (aberto à mão). A demanda fica "Concluída" e as Fiscalizações vão para o Arquivo.'],
                ['id' => 'fim-sem-processo', 'rotulo' => "Encerrada\nsem processo", 'tipo' => 'fim', 'setor' => 'Chefe de Setor', 'col' => 4, 'lin' => 16, 'situacao' => $a,
                    'tela' => 'Avulsas › detalhe › Encerrar sem processo',
                    'descricao' => 'Na avulsa, o Chefe encerra sem abrir processo. A demanda fica "Concluída" e as Fiscalizações vão para o Arquivo.'],
                ['id' => 'fim-ronda-arquivada', 'rotulo' => "Ronda arquivada\nFiscalização sem demanda", 'tipo' => 'fim', 'setor' => 'Chefe de Setor', 'col' => 5, 'lin' => 16, 'situacao' => $a,
                    'tela' => 'Fiscalizações › Encaminhadas › Arquivar',
                    'descricao' => 'A Fiscalização que nasceu da ronda, sem demanda, é encerrada pelo Chefe com "Arquivar".'],
                ['id' => 'fim-levantamentos', 'rotulo' => "Levantamento registrado\nconsulta e exportação", 'tipo' => 'fim', 'setor' => 'Chefe de Setor / Líder', 'col' => 7, 'lin' => 1, 'situacao' => $a,
                    'tela' => 'Fiscalização › Levantamentos',
                    'descricao' => 'O censo concluído (protocolo LEV) fica para consulta e exportação na Retaguarda, com o recorte do líder.'],
            ],
            'arestas' => [
                ['de' => 'entrada-esalvador-api', 'para' => 'pre-triagem'],
                ['de' => 'pre-triagem', 'para' => 'recebida', 'rotulo' => 'liberar para a Caixa'],
                ['de' => 'entrada-esalvador', 'para' => 'recebida'],
                ['de' => 'entrada-eprotocolo', 'para' => 'recebida'],
                ['de' => 'entrada-avulsa', 'para' => 'recebida'],
                ['de' => 'entrada-fala', 'para' => 'recebida', 'rotulo' => 'pelo Chefe'],
                ['de' => 'entrada-fala', 'para' => 'encaminhada-lider', 'rotulo' => 'pelo líder'],
                ['de' => 'recebida', 'para' => 'triagem'],
                ['de' => 'triagem', 'para' => 'encaminhada-lider', 'rotulo' => 'encaminhar à equipe'],
                ['de' => 'triagem', 'para' => 'fim-devolvida', 'rotulo' => 'devolver'],
                ['de' => 'triagem', 'para' => 'fim-arquivada', 'rotulo' => 'arquivar'],
                ['de' => 'encaminhada-lider', 'para' => 'decisao-lider'],
                ['de' => 'decisao-lider', 'para' => 'direcionada', 'rotulo' => 'à equipe'],
                ['de' => 'decisao-lider', 'para' => 'em-operacao', 'rotulo' => 'incluir em operação'],
                ['de' => 'decisao-lider', 'para' => 'encaminhada-chefe', 'rotulo' => 'ao Chefe, sem vistoria'],
                ['de' => 'entrada-operacao', 'para' => 'em-operacao', 'rotulo' => 'demandas anexadas'],
                ['de' => 'direcionada', 'para' => 'app-fila'],
                ['de' => 'em-operacao', 'para' => 'app-fila'],
                ['de' => 'app-entrada', 'para' => 'app-fila'],
                ['de' => 'app-fila', 'para' => 'app-vistoria', 'rotulo' => 'Fiscalizar'],
                ['de' => 'entrada-ronda', 'para' => 'app-vistoria', 'rotulo' => 'avulsa'],
                ['de' => 'em-operacao', 'para' => 'app-vistoria', 'rotulo' => 'avulsa na operação'],
                ['de' => 'app-vistoria', 'para' => 'app-desfecho'],
                ['de' => 'app-desfecho', 'para' => 'app-concluir', 'rotulo' => 'sem documento'],
                ['de' => 'app-desfecho', 'para' => 'app-documento', 'rotulo' => 'Notificação ou Auto'],
                ['de' => 'app-documento', 'para' => 'app-concluir'],
                ['de' => 'app-documento', 'para' => 'aguardando-regularizacao', 'rotulo' => 'prazo da notificação'],
                ['de' => 'aguardando-regularizacao', 'para' => 'retorno-app', 'rotulo' => 'fim do prazo'],
                ['de' => 'retorno-app', 'para' => 'app-concluir', 'rotulo' => 'despachar retorno'],
                ['de' => 'retorno-app', 'para' => 'retorno-vencido', 'rotulo' => 'situação mantida'],
                ['de' => 'retorno-vencido', 'para' => 'chefe-delibera', 'rotulo' => 'próxima medida'],
                ['de' => 'app-concluir', 'para' => 'vistoria-gravada', 'rotulo' => 'envio ou fila sem sinal'],
                ['de' => 'vistoria-gravada', 'para' => 'lider-le'],
                ['de' => 'lider-le', 'para' => 'direcionada', 'rotulo' => 'mandar a equipe voltar'],
                ['de' => 'lider-le', 'para' => 'encaminhada-chefe', 'rotulo' => 'encaminhar ao Chefe'],
                ['de' => 'encaminhada-chefe', 'para' => 'chefe-delibera', 'rotulo' => 'com demanda'],
                ['de' => 'encaminhada-chefe', 'para' => 'fim-ronda-arquivada', 'rotulo' => 'ronda: arquivar'],
                ['de' => 'chefe-delibera', 'para' => 'fim-respondida', 'rotulo' => 'registrar resposta'],
                ['de' => 'chefe-delibera', 'para' => 'fim-processo', 'rotulo' => 'abrir processo'],
                ['de' => 'chefe-delibera', 'para' => 'fim-sem-processo', 'rotulo' => 'encerrar sem processo'],
                ['de' => 'chefe-delibera', 'para' => 'encaminhada-lider', 'rotulo' => 'encaminhar de novo'],
                ['de' => 'chefe-delibera', 'para' => 'fim-devolvida', 'rotulo' => 'devolver'],
                ['de' => 'chefe-delibera', 'para' => 'fim-arquivada', 'rotulo' => 'arquivar'],
                ['de' => 'chefe-delibera', 'para' => 'envio-api', 'rotulo' => 'pela API'],
                ['de' => 'envio-api', 'para' => 'fim-respondida'],
                ['de' => 'entrada-levantamento', 'para' => 'fim-levantamentos', 'rotulo' => 'concluir levantamento'],
            ],
            'etapas' => [
                ['setor' => 'Entrada', 'titulo' => 'Por onde a demanda chega', 'nos' => ['entrada-esalvador-api', 'entrada-esalvador', 'entrada-eprotocolo', 'entrada-avulsa', 'entrada-fala', 'pre-triagem'], 'itens' => [
                    'Toda demanda hoje entra **digitada**: o Chefe de Setor cadastra e-Salvador (denúncia ou nova licença), e-Protocolo e Avulsa (pedido de superior ou ofício); o Fala Salvador é registrado pelo Chefe ou pelo líder.',
                    'A demanda nasce **"Recebida"**, na mesa do Chefe — salvo o Fala Salvador registrado pelo líder, que já nasce encaminhada à equipe dele.',
                    'A **leitura automática do e-Salvador** está prevista; quando existir, o que chegar por ela passa pela **Pré-triagem**, onde as demandas repetidas viram um caso só.',
                ]],
                ['setor' => 'Chefe de Setor', 'titulo' => 'Triagem', 'nos' => ['recebida', 'triagem', 'fim-devolvida', 'fim-arquivada'], 'itens' => [
                    'O Chefe **encaminha à equipe** — a sugerida pelo bairro, ou outra — e isso abre a **Fiscalização** da demanda.',
                    'Ou retira do fluxo: **devolve** ao remetente ou **arquiva**, com motivo e justificativa. A devolução fica registrada; nada é enviado ao canal.',
                ]],
                ['setor' => 'Líder de Equipe', 'titulo' => 'A Fiscalização vai à rua', 'nos' => ['encaminhada-lider', 'decisao-lider', 'direcionada', 'em-operacao', 'entrada-operacao'], 'itens' => [
                    'Na tela **Fiscalizações**, o líder **envia à equipe**, **inclui numa operação** aberta ou **devolve ao Chefe** sem vistoria.',
                    'A **operação** é cadastrada com período, bairros e fiscais de qualquer área, e recebe demandas anexadas.',
                ]],
                ['setor' => 'Aplicativo do fiscal', 'titulo' => 'A vistoria no ponto', 'nos' => ['app-entrada', 'app-fila', 'entrada-ronda', 'app-vistoria', 'app-desfecho', 'app-documento', 'app-concluir'], 'itens' => [
                    'O fiscal entra com a conta da Retaguarda e recebe o **kit do turno**: a fila da equipe e os **números reservados** de Notificação e de Auto.',
                    'Na vistoria, a coordenada é a **medida pelo GPS** (sem GPS, o registro diz o motivo), com fotos, ocorrências e relato. A **ronda** registra o que o fiscal encontra sem demanda; feita na tela de uma operação, a avulsa fica ligada a ela.',
                    'O **desfecho** é de uma lista fechada; Notificação Preliminar e Auto de Apreensão exigem o documento, com a **via impressa** na térmica.',
                    'Concluído, o registro **sobe ao servidor** — ou fica no aparelho, com as fotos, até o sinal voltar.',
                ]],
                ['setor' => 'Previsto', 'titulo' => 'O retorno da notificação', 'nos' => ['aguardando-regularizacao', 'retorno-app', 'retorno-vencido'], 'itens' => [
                    'Com a Notificação correndo prazo, a demanda ficaria **aguardando regularização**; no fim do prazo, o fiscal **registraria o retorno** (regularizado ou situação mantida).',
                    'A notificação não cumprida subiria ao Chefe como **retorno vencido**, para a próxima medida.',
                    'O aplicativo já tem essas telas, mas hoje **nenhum caminho leva a demanda principal até elas**.',
                ]],
                ['setor' => 'Líder de Equipe', 'titulo' => 'A volta da rua', 'nos' => ['vistoria-gravada', 'lider-le', 'encaminhada-chefe'], 'itens' => [
                    'A vistoria entra na Fiscalização **aguardando a leitura do líder**; no aplicativo, a demanda aparece "Com o líder".',
                    'O líder **manda a equipe voltar** (nova vistoria) ou **encaminha ao Chefe de Setor**, com justificativa. As demandas agregadas recebem o efeito do desfecho.',
                ]],
                ['setor' => 'Chefe de Setor', 'titulo' => 'Deliberação e destino', 'nos' => ['chefe-delibera', 'envio-api', 'fim-respondida', 'fim-processo', 'fim-sem-processo', 'fim-ronda-arquivada'], 'itens' => [
                    'No detalhe da demanda, o Chefe **responde à origem**: registra a resposta (e-Salvador, nova licença, Fala Salvador, e-Protocolo) ou, na avulsa, registra a **abertura do processo** ou **encerra sem processo**.',
                    'A demanda fica **"Concluída"** e as Fiscalizações dela vão para o **Arquivo**. O ato no sistema de origem é feito à mão — o envio **pela API do e-Salvador** está previsto.',
                    'O Chefe também pode **encaminhar de novo** à equipe (abre outra Fiscalização), **devolver** ou **arquivar**.',
                    'A Fiscalização da **ronda**, sem demanda, é encerrada com **"Arquivar"**.',
                ]],
                ['setor' => 'Aplicativo do fiscal', 'titulo' => 'O levantamento de ambulantes', 'nos' => ['entrada-levantamento', 'fim-levantamentos'], 'itens' => [
                    'O **censo por rua** — quem trabalha em cada ponto, com foto e GPS — corre à parte: não gera demanda nem fiscalização.',
                    'Concluído, fica para **consulta e exportação** na tela Levantamentos.',
                ]],
            ],
            'conclusoes' => [
                ['conclusao' => 'Devolvida ao remetente', 'quem' => 'Chefe de Setor', 'caminho' => 'Na triagem ou depois da fiscalização, com motivo, justificativa e destino.'],
                ['conclusao' => 'Arquivada', 'quem' => 'Chefe de Setor', 'caminho' => 'Na triagem ou depois da fiscalização, com motivo e justificativa.'],
                ['conclusao' => 'Respondida ao canal (Concluída)', 'quem' => 'Chefe de Setor', 'caminho' => 'e-Salvador, nova licença, Fala Salvador ou e-Protocolo: resposta registrada depois da vistoria lida pelo líder.'],
                ['conclusao' => 'Processo aberto (avulsa)', 'quem' => 'Chefe de Setor', 'caminho' => 'Avulsa com a abertura do processo registrada depois da vistoria.'],
                ['conclusao' => 'Encerrada sem processo (avulsa)', 'quem' => 'Chefe de Setor', 'caminho' => 'Avulsa encerrada sem processo depois da vistoria.'],
                ['conclusao' => 'Ronda arquivada', 'quem' => 'Chefe de Setor', 'caminho' => 'Fiscalização da ronda, sem demanda, arquivada depois de encaminhada pelo líder.'],
                ['conclusao' => 'Levantamento registrado', 'quem' => 'Fiscal', 'caminho' => 'Censo por rua concluído no aplicativo, para consulta na Retaguarda.'],
            ],
        ];
    }
}

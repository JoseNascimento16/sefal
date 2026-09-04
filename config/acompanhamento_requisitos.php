<?php

/*
|--------------------------------------------------------------------------
| Acompanhamento de Requisitos — cada funcionalidade × o requisito escrito
|--------------------------------------------------------------------------
|
| FONTE ÚNICA da tela "Acompanhamento de Requisitos" (Retaguarda → Sistema).
| Ela responde uma pergunta só, e não é "está construída?" (isso se vê usando o
| sistema): é "o que está construído BATE com o que foi escrito?".
|
| `hu_status`:
|   'sim'           = existe requisito escrito (HU) e o comportamento está alinhado a ele;
|   'desatualizada' = existe requisito escrito, mas o comportamento DIVERGIU — a nota diz o quê;
|   'nao'           = não há requisito escrito; a nota diz de ONDE a funcionalidade nasceu.
|
| Campos de cada linha:
|   modulo      — a seção a que a tela pertence (agrupa o resumo);
|   tela        — o nome pelo qual as pessoas a chamam;
|   origem      — 'Retaguarda' ou 'PWA' (o aplicativo do fiscal, quando chegar);
|   rota        — nome da rota. É a CHAVE DE LIGAÇÃO com `config/retaguarda_menu.php`:
|                 é por ela que `AcompanhamentoRequisitosTest` prova que nenhuma
|                 tela do menu ficou de fora deste mapa. Nome de tela muda; rota não;
|   breadcrumb  — onde a pessoa acha a tela;
|   hus         — os códigos das HUs que a especificam (vazio quando não há);
|   nota        — o que o requisito diz, o que divergiu, ou de onde a tela veio.
|
| ⛔ LEI DO PROJETO: funcionalidade NOVA nasce com a linha aqui, no MESMO commit.
| Alteração de tela existente REAVALIA o `hu_status` — se o comportamento passou a
| divergir do requisito escrito, a linha vira 'desatualizada' com a divergência na
| nota. Divergência silenciosa é o que faz um requisito virar ficção.
|
| Hoje o projeto não tem NENHUMA HU escrita: a régua é a spec de design aprovada
| com o dono, e por isso toda linha nasce 'nao' declarando essa origem. Quando as
| HUs forem redigidas, cada linha ganha os códigos e vira 'sim'.
|
*/

/*
 * A origem comum de tudo que existe hoje. Fica numa variável para que a data e a
 * fonte sejam as MESMAS em todas as linhas — repetidas à mão, um dia divergiriam,
 * e aí ninguém saberia mais qual era a régua de qual tela.
 */
$origemSpec = 'Sem requisito escrito — origem: spec de design 2026-08-24 + decisões do dono.';

return [

    'telas' => [

        [
            'modulo' => 'Sistema',
            'tela' => 'Login por matrícula',
            'origem' => 'Retaguarda',
            'rota' => 'login',
            'breadcrumb' => 'Retaguarda › Login',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Porta de entrada da Retaguarda pela matrícula funcional e senha, '
                .'com definição de senha no primeiro acesso pelo link enviado por e-mail. Todo bloqueio '
                .'é explicado em tela — credencial inválida, senha ainda não definida e conta desativada '
                .'têm cada um a sua mensagem.',
        ],

        [
            'modulo' => 'Painel',
            'tela' => 'Início',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.inicio',
            'breadcrumb' => 'Painel › Início',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Tela para onde o login leva e para onde volta, com o motivo, quem é '
                .'barrado em outra tela. Por isso ela é a única fora do controle de acesso: barrá-la '
                .'fecharia um laço de redirecionamento. Os atalhos são decididos pelo servidor: levam à '
                .'tela quando ela existe, ficam "em construção" quando ela é de entrega futura e não '
                .'aparecem para quem não pode abri-la.',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Meu Perfil',
            'origem' => 'Retaguarda',
            'rota' => 'profile.edit',
            'breadcrumb' => 'Sistema › Meu Perfil',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Área da própria conta: dados pessoais, troca de senha (com '
                .'confirmação da senha atual) e aparência. Fica fora do controle de acesso por decisão '
                .'de projeto — trancar alguém fora da própria conta não é decisão de gestor.',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Modo Gerente',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.modo-gerente.index',
            'breadcrumb' => 'Sistema › Modo Gerente',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Matriz de quem entra onde: setor × tela × ação, com rastro de cada '
                .'concessão e revogação — o rastro mostra em tela O QUE mudou, por setor. É a fonte '
                .'única do acesso: o menu, a abertura da tela e as ações obedecem a ela. Cada tela é '
                .'salva por si, e a seção com alteração pendente é sinalizada (sair sem salvar pergunta '
                .'antes).',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Relatórios',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.relatorios.index',
            'breadcrumb' => 'Sistema › Relatórios',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Emissão de documento oficial com período, totais e identificação de '
                .'quem emitiu, em PDF, Excel e Word. Não se confunde com a exportação de listagem, que '
                .'entrega o recorte visível de uma grade.',
        ],

        /*
         * Não tem item de menu porque não é uma tela: é o botão que TODA
         * listagem carrega. Fica no mapa mesmo assim — o acompanhamento é de
         * funcionalidade entregue, não de linha do menu, e uma regra que vale em
         * todas as telas é justamente a que ninguém lembra de conferir depois.
         */
        [
            'modulo' => 'Sistema',
            'tela' => 'Exportação de listagens',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.exportar-listagem',
            'breadcrumb' => 'Presente em toda listagem da Retaguarda',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Toda listagem entrega em PDF, Excel e Word exatamente o que está à '
                .'vista — o que a busca, o filtro e a aba deixaram na tela —, nunca o universo inteiro '
                .'nem apenas a página aberta, e sempre com o recorte declarado no documento para quem o '
                .'receber saber do que ele fala.',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Logs de Erros',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.logs.index',
            'breadcrumb' => 'Sistema › Logs',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Consulta às falhas que o sistema capturou, achadas pelo mesmo código '
                .'que apareceu na tela de quem estava usando o sistema. É só leitura: apagar linha daqui '
                .'apagaria a única trilha de um defeito.',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Monitoramento de Parametrizações',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.monitoramento.index',
            'breadcrumb' => 'Sistema › Monitoramento',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Painel das condições mínimas para o sistema funcionar: o que está '
                .'vermelho diz o que parou e leva para onde se corrige. Vigia o ambiente (conta de '
                .'administrador ativa, armazenamento gravável) e as listas de escolha OBRIGATÓRIAS — '
                .'atividade do ambulante e tipo de infração. As verificações que escrevem em disco ou '
                .'falam com serviço externo só rodam pelo botão.',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Acompanhamento de Requisitos',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.acompanhamento-de-requisitos.index',
            'breadcrumb' => 'Sistema › Acompanhamento de Requisitos',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Esta própria tela: cruza cada funcionalidade entregue com o requisito '
                .'escrito que a especifica, apontando o que não tem requisito e o que divergiu do que foi '
                .'escrito.',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Cadastro de Permissionário',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.permissionarios.index',
            'breadcrumb' => 'Fiscalização › Permissionários',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Identidade de quem é fiscalizado, com documento OPCIONAL: em rua a '
                .'pessoa é reconhecida pela foto e pelo apelido, e exigir CPF faria o cadastro de campo '
                .'não acontecer. Quando informado, o documento é validado (CPF ou CNPJ, inclusive o '
                .'alfanumérico) e não se repete. O cadastro nascido em campo fica marcado como '
                .'"Cadastrado em campo" até alguém conferir — a tela de validação dessa fila é de '
                .'entrega futura, e por ora o gestor troca a situação à mão. Nome e apelido aceitam nome '
                .'de gente, não marcação nem símbolo. O fiscal CONSULTA o cadastro pela Retaguarda: '
                .'incluir e excluir por lá são da gestão.',
        ],

        /*
         * Parametrização — as seis listas de escolha. São a MESMA tela seis
         * vezes (listar, incluir, alterar, inativar, excluir), então a nota de
         * cada uma diz o que muda: para que serve a lista e quem a consome.
         */
        [
            'modulo' => 'Parametrização',
            'tela' => 'Tipos de Infração',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.parametrizacao.tipos-de-infracao.index',
            'breadcrumb' => 'Parametrização › Tipos de Infração',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Lista do que o fiscal enquadra ao autuar, com descrição de apoio '
                .'à escolha em rua. Valor em uso é inativado, não excluído — registro antigo continua '
                .'legível.',
        ],

        [
            'modulo' => 'Parametrização',
            'tela' => 'Atividades do Ambulante',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.parametrizacao.atividades-do-ambulante.index',
            'breadcrumb' => 'Parametrização › Atividades do Ambulante',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Ramo autorizado na permissão — o que a pessoa vende ou faz no '
                .'ponto. Será a primeira lista apontada por cadastro de permissionário, e a exclusão '
                .'passa a ser barrada quando esse vínculo existir.',
        ],

        [
            'modulo' => 'Parametrização',
            'tela' => 'Unidades de Medida',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.parametrizacao.unidades-de-medida.index',
            'breadcrumb' => 'Parametrização › Unidades de Medida',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Como se conta a mercadoria em apreensão ou vistoria. A sigla é '
                .'obrigatória: é ela que sai no documento impresso em rua.',
        ],

        [
            'modulo' => 'Parametrização',
            'tela' => 'Tipos de Operação',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.parametrizacao.tipos-de-operacao.index',
            'breadcrumb' => 'Parametrização › Tipos de Operação',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' O feitio do trabalho em campo (rotina, mutirão, operação '
                .'conjunta) — é o que agrupa as fiscalizações quando se olha o período inteiro.',
        ],

        [
            'modulo' => 'Parametrização',
            'tela' => 'Origens de Operação',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.parametrizacao.origens-de-operacao.index',
            'breadcrumb' => 'Parametrização › Origens de Operação',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Por que a equipe foi até lá (denúncia, cobrança de outro órgão, '
                .'planejamento) — é o que permite responder ao demandante depois.',
        ],

        [
            'modulo' => 'Parametrização',
            'tela' => 'Motivos de Recusa',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.parametrizacao.motivos-de-recusa.index',
            'breadcrumb' => 'Parametrização › Motivos de Recusa',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' O que o gestor responde ao devolver um cadastro feito em campo. '
                .'O fiscal lê esse texto no aparelho, então ele precisa dizer o que corrigir.',
        ],

        /*
         * O aplicativo do fiscal. Entra aqui como as demais: o mapa é de
         * funcionalidade ENTREGUE, e não de linha do menu da Retaguarda — o que
         * não tem item de menu entra igual, senão nunca é cobrado.
         */
        [
            'modulo' => 'Aplicativo do Fiscal',
            'tela' => 'Fila de denúncias dirigidas e registro de vistoria (protótipo)',
            'origem' => 'PWA',
            'rota' => 'pwa',
            'breadcrumb' => 'Aplicativo do Fiscal › /app',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: cenário da reunião com o cliente de 02/09/2026 '
                .'e decisões do dono de 03 e 04/09/2026; regras em docs/regras-de-negocio/fiscalizacao/'
                .'aplicativo-do-fiscal.md. PROTÓTIPO, sem servidor: os dados vivem em '
                .'resources/js/pwa/ e o que o fiscal registra fica na memória da aba. A fila do '
                .'aplicativo é a da EQUIPE de quem entrou e fala o mesmo vocabulário do módulo de '
                .'Denúncias — mesmas situações, mesmo protocolo DEN-NNNN e a mesma lista fechada de '
                .'seis desfechos, que é o que fecha o passo do trâmite na Retaguarda. Denúncia em '
                .'triagem não chega ao fiscal; denúncia com Notificação em prazo oferece o registro '
                .'do RETORNO. O registro é DESPACHADO à caixa de entrada do Chefe de Setor da área '
                .'da equipe, com as CONSIDERAÇÕES FINAIS do fiscal (texto livre e atalhos de '
                .'recomendação, os MESMOS 11 do catálogo da Retaguarda, aqui na redação CURTA — a '
                .'chave é o que viaja, e a redação explícita mora do lado de quem decide), e não '
                .'conclui sem o documento quando o desfecho lavra documento — o '
                .'impedimento diz o motivo e abre o formulário que falta. Vocabulário novo do '
                .'domínio: Chefe de Setor (antes "gestor") e Coordenador (antes "administrativo"). '
                .'Falta a sincronização de verdade (endpoint, banco offline, fila de '
                .'envio): enquanto isso, os dados são segunda cópia dos do servidor.',
        ],

    ],

];

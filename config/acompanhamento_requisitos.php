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

/*
 * A origem das telas que entraram no menu como STUB: elas nasceram da decisão do
 * dono de deixar o caminho do trabalho visível antes de o conteúdo existir. O texto
 * declara também O QUE FALTA, que é a informação que a linha tem de carregar
 * enquanto o conteúdo não chega.
 */
$origemStub = 'Sem requisito escrito — origem: decisão do dono 2026-08-27 (o caminho do trabalho '
    .'aparece no menu antes do conteúdo).';

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
                .'aparecem para quem não pode abri-la. A primeira tela depois do login recebe o splash '
                .'de boas-vindas, que aparece uma vez por entrada, não captura clique e sai sozinho; a '
                .'saudação pelo horário tem um dono só, com a madrugada tratada como noite.',
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
                .'salva por si, e a seção com alteração pendente é sinalizada (fechar sem salvar '
                .'pergunta antes). Não é uma página: abre como PAINEL sobre a tela em que a pessoa '
                .'está, pelo item do menu Sistema — quem distribui acesso está no meio de uma '
                .'conferência, e ir para outra página fazia perder o lugar. Quem chega pelo endereço '
                .'antigo é levado à tela inicial com o painel abrindo lá.',
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
         * AS QUATRO TELAS EM PREPARAÇÃO — Fases 2 e 3.
         *
         * Entram no mapa porque estão ENTREGUES como stub: têm endereço, permissão
         * e uma tela que abre dizendo o que vai ser. O mapa é de funcionalidade
         * entregue, e o que existe pela metade é justamente o que precisa aparecer
         * com o estado escrito — senão passa por pronto na leitura de cima.
         */
        [
            'modulo' => 'Fiscalização',
            'tela' => 'Cadastro de Operação',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.operacoes.index',
            'breadcrumb' => 'Fiscalização › Cadastro de Operação',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemStub.' Stub aguardando a Fase 2. A tela abre e anuncia o que vai fazer — abrir '
                .'operação com data, área e equipe, acompanhar o que ela produziu em campo e '
                .'encerrá-la com o resultado. Rota, permissão e item de menu já valem; o conteúdo '
                .'chega com a fase.',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Fiscalizações',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.fiscalizacoes.index',
            'breadcrumb' => 'Fiscalização › Fiscalizações',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemStub.' Stub aguardando a Fase 2. Vai receber o que o aplicativo do fiscal registra '
                .'na rua: consulta por permissionário, área e período, com foto, ponto de GPS, o '
                .'documento emitido na hora e o prazo de retorno de quem foi notificado. Nada se '
                .'perde à espera dela — o aplicativo guarda o registro.',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Mapa ao Vivo',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.mapa.index',
            'breadcrumb' => 'Fiscalização › Mapa ao Vivo',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemStub.' Stub aguardando a Fase 3. Vai mostrar a cidade agora: fiscais em campo com o '
                .'último ponto conhecido, o que foi registrado nas últimas horas e as áreas de '
                .'atuação desenhadas sobre o mapa. O desenho da tela de mapa já está decidido '
                .'(padrão imersivo, em docs/regras-de-negocio/design-retaguarda.md).',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Mapa de Calor',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.mapa-de-calor.index',
            'breadcrumb' => 'Fiscalização › Mapa de Calor',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemStub.' Stub aguardando a Fase 3. Vai mostrar onde a ocorrência se concentra, por '
                .'região e por período, para a operação ir aonde precisa — inclusive o foco do dia '
                .'sugerido a partir dos últimos trinta dias.',
        ],

        /*
         * A CASCA — sem item de menu, e presente em toda tela autenticada. Entra
         * aqui pelo mesmo motivo da exportação: o mapa é de funcionalidade
         * entregue, e o que vale em todas as telas é justamente o que ninguém
         * lembra de conferir depois.
         */
        [
            'modulo' => 'Sistema',
            'tela' => 'Casca da Retaguarda (menu, doca e cabeçalho editorial)',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.inicio',
            'breadcrumb' => 'Presente em toda tela da Retaguarda',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Menu lateral navy de canto curvado, com número vivo ao lado do '
                .'item que declara um (neutro = tamanho, laranja = fila, e fila em zero não aparece) e '
                .'o cartão de quem entrou no pé, com a saída. O menu tem duas formas — painel e doca '
                .'flutuante —, a escolha é da pessoa e fica guardada no navegador dela; abaixo de '
                .'1100px a doca vale sozinha e abaixo de 620px vira barra no pé da tela. Não há barra '
                .'superior nem menu escondido atrás de botão: o topo de cada tela é o cabeçalho dela '
                .'(seção, título, subtítulo) e o menu está sempre à vista. Desenho e decisões em '
                .'docs/regras-de-negocio/design-retaguarda.md.',
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

    ],

];

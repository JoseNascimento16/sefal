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
                .'fecharia um laço de redirecionamento.',
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
                .'concessão e revogação. É a fonte única do acesso — o menu, a abertura da tela e as '
                .'ações obedecem a ela.',
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
                .'quem emitiu, em PDF, XLSX e DOCX. Não se confunde com a exportação de listagem, que '
                .'entrega o recorte visível de uma grade.',
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
                .'vermelho diz o que parou e leva para onde se corrige. As verificações que escrevem em '
                .'disco ou falam com serviço externo só rodam pelo botão.',
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

    ],

];

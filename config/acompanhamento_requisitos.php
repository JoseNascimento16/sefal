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

/*
 * A origem dos dois módulos que nasceram da reunião com o cliente de 02/09/2026 e
 * foram entregues como PROTÓTIPO — tela navegável com dados fictícios, para o
 * dono aprovar a forma antes de existir tabela, migration e regra.
 *
 * O aviso de que é protótipo fica na nota de cada linha, e não só aqui: quem lê a
 * tela de acompanhamento tem de saber que aquelas duas linhas não são sistema
 * pronto — senão a cobertura passa a contar como entregue o que ainda não grava
 * nada.
 */
$origemPrototipo = 'Sem requisito escrito — origem: reunião com o cliente 2026-09-02 '
    .'(docs/cenario-2026-09-02-reuniao-cliente.md) + documentos do cliente. Entregue como PROTÓTIPO.';

/*
 * A origem do módulo de Denúncias, e o fluxo que as DUAS telas dele compartilham.
 *
 * O fluxo fica numa variável separada porque é literalmente o mesmo nas duas
 * linhas: repetido à mão, um dia só uma delas ganharia a regra nova, e o
 * acompanhamento passaria a descrever dois módulos onde existe um.
 */
$origemDenuncias = 'Sem requisito escrito — origem: pedido do dono 2026-09-02, a partir do cenário '
    .'da reunião com o cliente (docs/cenario-2026-09-02-reuniao-cliente.md). Entregue como PROTÓTIPO.';

$fluxoDenuncias = 'O fluxo tem DUAS etapas com DOIS donos: (1) TRIAGEM — o COORDENADOR '
    .'analisa a denúncia recebida e a encaminha à ÁREA do bairro, com a área sugerida pela estrutura '
    .'Área › Equipe e editável na própria linha (bairro pertencente a duas áreas tem duas respostas '
    .'certas), vendo o NOME DO CHEFE DE SETOR que vai receber, ou a retira do fluxo devolvendo ao canal / '
    .'arquivando, com motivo de lista MAIS justificativa por escrito — denúncia improcedente ou '
    .'duplicada não deve chegar ao Chefe de Setor; (2) DIRECIONAMENTO — o CHEFE DE SETOR DA ÁREA escolhe entre mandar à '
    .'EQUIPE (a da área, ou outra, e aí a justificativa passa a ser obrigatória) ou incluir numa '
    .'OPERAÇÃO já planejada, podendo abrir uma nova dali. As duas etapas operam em LOTE e uma a uma, e '
    .'a etapa de quem entrou vem do SETOR (coordenador tria, chefe de setor direciona, quem administra o '
    .'sistema exerce as duas), com selo visível na tela dizendo a etapa E a área. O CHEFE DE SETOR É DE UMA '
    .'ÁREA: a listagem dele traz só o que foi encaminhado à área que ele responde, e a ação sobre '
    .'denúncia de outra área é recusada no servidor com o motivo escrito — esconder sem barrar deixaria '
    .'a fronteira valendo só para quem não sabe montar a requisição. Estados: Recebida › Encaminhada à '
    .'área › Direcionada à equipe | Em operação › Em campo › Concluída, com Devolvida e Arquivada como '
    .'saídas da triagem. Depois do direcionamento a denúncia vira TRABALHO DE CAMPO, e o que volta é o '
    .'DESFECHO, de lista fechada: regularizado no local (sem documento, o caminho comum — a '
    .'fiscalização é educativa antes de punitiva), nada encontrado no local, Notificação Preliminar '
    .'emitida (e aí a situação passa a ser "Aguardando regularização", com o prazo do documento '
    .'correndo), regularizado após notificação, retorno com a situação mantida (situação "Retorno '
    .'vencido": o prazo venceu, o ponto continua igual e cabe ao Chefe de Setor decidir a próxima medida) e '
    .'Auto de Apreensão lavrado, com os bens sob guarda no SEGUB. O TRÂMITE é NAVEGÁVEL: linha do '
    .'tempo em abas verticais (clique ou setas do teclado, uma parada de tabulação só), abrindo no '
    .'último passo, e o painel do passo mostra o que ele produziu — a decisão tomada e por quê, o que '
    .'o fiscal registrou em campo (relato, situação encontrada, fotos, coordenada com precisão) e o '
    .'DOCUMENTO lavrado em LEITURA, na forma do papel (número do bloco, campos na ordem do impresso, '
    .'caixas assinaladas, penalidades previstas, prazo, e as assinaturas com o estado de cada uma — '
    .'assinou, recusou assinar ou não colhida). A Retaguarda NÃO EMITE documento de campo: quem lavra '
    .'Notificação e Auto de Apreensão é o fiscal, em rua, pelo aplicativo. Cada mudança acrescenta '
    .'linha ao trâmite (quem, quando, por quê). A permissão '
    .'é UMA para o módulo (as duas telas dividem o caminho /retaguarda/denuncias e aparecem no menu '
    .'como uma PASTA que expande), concedida a coordenador, administrador e chefe de setor — o fiscal não '
    .'entra, senão escolheria o próprio trabalho. ⚠️ É PROTÓTIPO: a integração NÃO existe, não há tabela '
    .'nem gravação — as denúncias de partida vêm de config/prototipo_denuncias.php, o vínculo chefia↔área '
    .'vem de config/prototipo_estrutura.php, a redação dos impressos vem de '
    .'config/prototipo_documentos_campo.php e as decisões vivem na sessão de quem navega. Os estágios '
    .'avançados (vistoria, desfecho, documento) são SEMEADOS: quando o aplicativo do fiscal receber a '
    .'denúncia dirigida de verdade, é ele que acrescenta esses passos, e a leitura da tela continua a '
    .'mesma. Pendências que '
    .'isto abre: contrato das APIs do e-Salvador e do 156, prazo real de cada canal, canal de devolução, '
    .'a MODELAGEM DEFINITIVA do vínculo chefia↔área (em produção é tabela usuário↔área, não arquivo de '
    .'configuração), a numeração definitiva do protocolo, a numeração dos blocos de documento (hoje as '
    .'faixas do papel do cliente, escritas à mão; no sistema saem do estoque reservado por aparelho), a '
    .'ação de tela que autoriza a próxima medida num retorno vencido (nasce junto do módulo de '
    .'fiscalização) e a UNIFICAÇÃO da redação dos impressos, que hoje tem uma segunda cópia no protótipo '
    .'do aplicativo do fiscal.';

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
                .'de projeto — trancar alguém fora da própria conta não é decisão de chefia.',
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
         * AS DUAS TELAS EM PREPARAÇÃO — Fase 2.
         *
         * Entram no mapa porque estão ENTREGUES como stub: têm endereço, permissão
         * e uma tela que abre dizendo o que vai ser. O mapa é de funcionalidade
         * entregue, e o que existe pela metade é justamente o que precisa aparecer
         * com o estado escrito — senão passa por pronto na leitura de cima.
         *
         * Eram QUATRO: o Mapa ao Vivo e o Mapa de Calor saíram desta vizinhança em
         * 02/09/2026, quando passaram a existir como protótipo — as linhas deles
         * estão junto das outras telas de protótipo, mais abaixo.
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
                .'na rua: consulta por ambulante, área e período, com foto, ponto de GPS, o '
                .'documento emitido na hora e o prazo de retorno de quem foi notificado. Nada se '
                .'perde à espera dela — o aplicativo guarda o registro.',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Retorno de Campo',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.retorno-de-campo.index',
            'breadcrumb' => 'Fiscalização › Retorno de Campo',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: decisão do dono 04/09/2026 ("todo registro de '
                .'fiscalização concluído volta para a caixa de entrada do Chefe de Setor, e as '
                .'considerações finais do fiscal aparecem no histórico do trâmite"). Entregue como '
                .'PROTÓTIPO. A FILA do Chefe de Setor: tudo que a equipe da área dele concluiu em rua '
                .'volta para cá, com quando, equipe e fiscal, o ponto, o desfecho, o documento lavrado '
                .'(quando houve) e — em coluna própria, porque é o que decide — a RECOMENDAÇÃO DO '
                .'FISCAL, ao lado das considerações que ele escreveu. A recomendação chega como CHAVE '
                .'(é o que o aplicativo do fiscal grava, e é o que o relatório soma) e é mostrada aqui '
                .'na redação EXPLÍCITA do catálogo do servidor — a curta é a pílula do celular, e quem '
                .'decide precisa da frase inteira; chave que o catálogo não conhece aparece CRUA, em '
                .'vez de desaparecer da tela. Duas decisões da chefia, em lote '
                .'e uma a uma: dar CIÊNCIA (o retorno sai da fila; observação opcional, porque o ato '
                .'de ler já é a informação) ou determinar NOVA VISTORIA (o ponto volta à equipe, com '
                .'justificativa obrigatória de 15 caracteres no servidor — "voltar lá" não diz à '
                .'equipe o que procurar). RECORTE POR ÁREA: o Chefe de Setor vê só o que as equipes da '
                .'área dele concluíram; o Coordenador e o administrador veem o universo, porque quem '
                .'tria precisa saber o que aconteceu com o que encaminhou. O recorte é feito no '
                .'SERVIDOR, e há DUAS recusas explicadas ali: quem apenas acompanha (Coordenador) não '
                .'decide, e decisão sobre registro de outra área é recusada nominalmente — esconder da '
                .'lista não é fronteira, e o lote é o caminho fácil para alcançar o que não se vê. '
                .'Busca inteligente com facetas do domínio (com/sem documento, de denúncia, avulsa, '
                .'com recomendação), aba que troca a FONTE (a fila × o histórico da área) e exportação '
                .'do recorte visível em PDF/XLSX/DOCX. NÃO é a Caixa de Entrada: lá o Coordenador '
                .'digita o que chegou em PAPEL, no começo da cadeia; aqui a chefia lê o que voltou do '
                .'CAMPO, no fim dela — e a tela diz isso em cima. Não há inclusão: registro de '
                .'fiscalização nasce em rua, no aplicativo do fiscal. ⚠️ É PROTÓTIPO: não há tabela nem '
                .'gravação. Os registros que vieram de DENÚNCIA são DERIVADOS do trâmite dela (a mesma '
                .'vistoria descrita duas vezes divergiria), e as fiscalizações AVULSAS — operação, '
                .'ronda, pedido de outro órgão — vêm de config/prototipo_registros_de_campo.php; as '
                .'decisões vivem na sessão de quem navega. Pendências que isto abre: a fiscalização '
                .'como TABELA (hoje ela só existe dentro do trâmite da denúncia e do arquivo de '
                .'avulsas), a MODELAGEM DEFINITIVA do vínculo chefia↔área, o efeito real de "nova '
                .'vistoria" no aplicativo do fiscal (hoje só muda o estado da fila) e o prazo de '
                .'leitura que torna um retorno atrasado — a tela já conta os dias parados, mas ninguém '
                .'definiu a partir de quantos ele cobra.',
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
            'tela' => 'Cadastro de Ambulante',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.ambulantes.index',
            'breadcrumb' => 'Fiscalização › Ambulantes',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemSpec.' Identidade de quem é fiscalizado, com documento OPCIONAL: em rua a '
                .'pessoa é reconhecida pela foto e pelo apelido, e exigir CPF faria o cadastro de campo '
                .'não acontecer. Quando informado, o documento é validado (CPF ou CNPJ, inclusive o '
                .'alfanumérico) e não se repete. O cadastro nascido em campo fica marcado como '
                .'"Cadastrado em campo" até alguém conferir — a tela de validação dessa fila é de '
                .'entrega futura, e por ora o Chefe de Setor troca a situação à mão. Nome e apelido aceitam nome '
                .'de gente, não marcação nem símbolo. O fiscal CONSULTA o cadastro pela Retaguarda: '
                .'incluir e excluir por lá são da gestão. A entidade é o AMBULANTE, e ser '
                .'PERMISSIONÁRIO é atributo dela (tem permissão da SEMOP, sim ou não): quem é marcado '
                .'precisa informar o nº da permissão, a validade segue opcional (em rua o papel '
                .'costuma não estar legível), desmarcar limpa os dois, e a situação continua sendo '
                .'outra pergunta — sem permissão pode estar regular, e permissionário pode estar '
                .'irregular.',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Caixa de Entrada do Coordenador',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.caixa-de-entrada.index',
            'breadcrumb' => 'Fiscalização › Caixa de Entrada',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemPrototipo.' Porta por onde a demanda de FORA entra: e-Salvador, Fala '
                .'Salvador 156, pedido de nova licença e ofício chegam em papel e são digitados aqui. '
                .'Denúncia pode ser ANÔNIMA. O bairro sugere a equipe responsável (a estrutura Área › '
                .'Equipe), e quem confirma é o coordenador — bairro pertencente a duas áreas tem duas '
                .'respostas certas. Duas saídas: registrar e encaminhar (vira trabalho dirigido da '
                .'equipe) ou registrar e devolver/arquivar, com motivo de lista MAIS justificativa por '
                .'escrito, porque é ato administrativo. Cada decisão acrescenta uma linha ao trâmite da '
                .'demanda (quem, quando, o quê). A tela é do COORDENADOR (concessão '
                .'acrescentada em 02/09/2026, quando o setor nasceu): registrar o que chega em papel é '
                .'função dele; o Chefe de Setor acompanha e o administrador cobre. ⚠️ É PROTÓTIPO: não há '
                .'tabela nem gravação — as '
                .'demandas de partida vêm de config/prototipo_caixa_entrada.php e as decisões vivem na '
                .'sessão de quem navega. Pendências que isto abre: prazo de cada canal, canal de retorno '
                .'ao e-Salvador/156 e a numeração definitiva do protocolo.',
        ],

        [
            'modulo' => 'Denúncias',
            'tela' => 'Denúncias do e-Salvador',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.denuncias.e-salvador.index',
            'breadcrumb' => 'Denúncias › e-Salvador',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemDenuncias.' As denúncias que o portal e-Salvador (Ouvidoria Geral do '
                .'Município) entrega ao SEFAL por INTEGRAÇÃO — ninguém as digita: a tela não tem botão de '
                .'cadastrar, e cada denúncia carrega o número que o canal lhe deu e a hora em que a '
                .'integração a entregou. O que chega em papel ao balcão continua sendo assunto da Caixa '
                .'de Entrada. Como o cidadão abre a denúncia autenticado, o requerente vem SEMPRE '
                .'identificado (nome, CPF, e-mail, telefone), o endereço vem estruturado e ele pode '
                .'anexar foto e documento. '.$fluxoDenuncias,
        ],

        [
            'modulo' => 'Denúncias',
            'tela' => 'Denúncias do Fala Salvador',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.denuncias.fala-salvador.index',
            'breadcrumb' => 'Denúncias › Fala Salvador',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemDenuncias.' As denúncias que a central telefônica Fala Salvador (Disque '
                .'156) entrega ao SEFAL por INTEGRAÇÃO — mesma mecânica da tela do e-Salvador, com o que '
                .'o telefone muda no dado: a denúncia pode ser ANÔNIMA, o relato é a transcrição do que '
                .'o atendente ouviu (às vezes sem número nem ponto de referência, e a tela marca isso '
                .'porque é o que decide se dá para mandar equipe), a categoria é a que o atendente '
                .'escolheu, e não há anexo. '.$fluxoDenuncias,
        ],

        [
            'modulo' => 'Estrutura',
            'tela' => 'Áreas e Equipes',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.areas-e-equipes.index',
            'breadcrumb' => 'Estrutura › Áreas e Equipes',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemPrototipo.' A estrutura PERMANENTE da fiscalização — Área › Equipe › '
                .'encarregado › fiscais › bloco de bairros —, transcrita do documento do cliente "ÁREAS '
                .'DAS EQUIPES ATUALIZADA - 17/04/2026": 8 áreas, 8 equipes, 151 bairros distintos e os 3 '
                .'corredores da Itinerante. Três '
                .'recortes, e não um: seis áreas cobrem BLOCOS DE BAIRROS, a Itinerante cobre CORREDORES '
                .'(Avenida Sete, Comércio, Joana Angélica) e a Noturna cobre a CIDADE INTEIRA, com '
                .'recorte por TURNO. Bairro em mais de uma área é caso NORMAL (Mussurunga, Patamares e '
                .'Jardim das Margaridas), mostrado como aviso informativo: o vínculo bairro↔equipe não é '
                .'1:1, a Caixa de Entrada sugere e o coordenador confirma. ⚠️ É PROTÓTIPO: a lista de '
                .'fiscais de cada equipe é fictícia (o documento nomeia só o encarregado), não há tabela '
                .'nem gravação, e o que a pessoa mexe vive na sessão dela.',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Mapa ao Vivo',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.mapa.index',
            'breadcrumb' => 'Fiscalização › Mapa ao Vivo',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemPrototipo.' Deixou de ser stub em 02/09/2026. A cidade agora, para o CHEFE DE SETOR — '
                .'não é a tela do fiscal: a pergunta que ela responde é "para onde eu mando gente hoje?". '
                .'Primeira tela no padrão IMERSIVO (RN-07 do desenho da Retaguarda): o mapa é o fundo, '
                .'sangrando de borda a borda, e a leitura flutua sobre a cidade em painéis de vidro; o menu '
                .'permanece. Mostra os pontos conhecidos por situação, o que entrou no período, quem está na '
                .'rua e os RETORNOS VENCIDOS, que pulsam com o "há N dias" colado no pino. Filtros da chefia '
                .'por equipe, situação e período — e filtrar pela equipe Noturna seleciona por TURNO, não por '
                .'bairro, porque é esse o recorte dela. Os painéis são agregações da mesma lista que o mapa '
                .'desenha (RN-06) e o recorte vai dito em palavras, para ninguém ler o número da equipe como '
                .'se fosse o da cidade. ⚠️ É PROTÓTIPO: pessoas, horários e situações são inventados; as '
                .'coordenadas de Salvador e a área/equipe de cada bairro, não (a derivação sai do mesmo '
                .'cadastro de Áreas e Equipes). Não há tempo real nem tabela: a tela declara o instante que '
                .'mostra. Pendências que isto abre: de onde virá a posição do fiscal em campo e com que '
                .'frequência, e qual é o prazo oficial de retorno de uma notificação.',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Mapa de Calor',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.mapa-de-calor.index',
            'breadcrumb' => 'Fiscalização › Mapa de Calor',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemPrototipo.' Deixou de ser stub em 02/09/2026. O registro de campo virando decisão '
                .'de operação, no mesmo padrão IMERSIVO (RN-07). A tela abre com a LEITURA EM UMA FRASE '
                .'("o Centro Histórico concentra 42% das ocorrências dos últimos 30 dias — 3,1× a média da '
                .'cidade"), porque quem tem trinta segundos não interpreta gradiente; a mancha serve para '
                .'conferir e achar o recorte. Janela de 7, 30 ou 90 dias e recorte por equipe, com ranking '
                .'das regiões trazendo ocorrências, fatia do período, a VARIAÇÃO contra o período anterior de '
                .'igual tamanho e a equipe responsável. A recomendação de operação diz o MOTIVO e não aponta '
                .'sempre o primeiro do ranking: bairro em subida forte na segunda posição costuma ser a '
                .'melhor aposta, porque o líder já tem rotina. O ranking exporta em PDF/XLSX/DOCX pelo ponto '
                .'único, com o recorte impresso. ⚠️ É PROTÓTIPO: a incidência é inventada (coordenadas e '
                .'estrutura de equipes, não), não há tabela, e criar operação é da tela de Cadastro de '
                .'Operação — esta apenas leva até lá.',
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
                .'ponto. Será a primeira lista apontada por cadastro de ambulante, e a exclusão '
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
            'nota' => $origemSpec.' O que o Chefe de Setor responde ao devolver um cadastro feito em campo. '
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

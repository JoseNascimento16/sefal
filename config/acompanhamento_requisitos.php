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
 * ⚠️ Havia aqui um `$origemStub` — a origem das telas que entraram no MENU antes
 * de o conteúdo existir (decisão do dono, 27/08/2026: "o caminho do trabalho
 * aparece no menu antes do conteúdo").
 *
 * Ele saiu em 09/09/2026 porque não há mais stub: as quatro telas que o usavam
 * passaram a existir — as duas de mapa em 02/09, o Cadastro de Operação e as
 * Fiscalizações em 09/09 —, e com elas o andaime foi removido. Variável de origem
 * sem nenhuma linha que a use é convite para a próxima tela pela metade nascer
 * declarando "stub aguardando a Fase 2" quando a fase já passou.
 */

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

$fluxoDenuncias = 'PAPÉIS DESDE 22/09/2026 (decisão do dono): não há coordenador no sistema — os '
    .'coordenadores trabalham no e-Salvador e mandam para a caixa do setor; o CHEFE DE SETOR é UM SÓ; o '
    .'LÍDER DE EQUIPE é o encarregado do documento, com conta. O fluxo tem DUAS etapas com DOIS donos: '
    .'(1) ENCAMINHAMENTO — o CHEFE DE SETOR analisa a denúncia recebida e a encaminha à EQUIPE, sugerida '
    .'pelo bairro pela estrutura Área › Equipe e editável na própria linha (bairro pertencente a duas '
    .'áreas tem duas respostas certas), vendo o NOME DO LÍDER que vai receber, ou a retira do fluxo '
    .'devolvendo ao canal / arquivando, com motivo de lista MAIS justificativa por escrito — denúncia '
    .'improcedente ou duplicada não deve chegar ao líder; (2) DIRECIONAMENTO — o LÍDER DA EQUIPE manda os '
    .'fiscais ao ponto com a orientação, ou inclui numa OPERAÇÃO já planejada, podendo abrir uma nova '
    .'dali. As duas etapas operam em LOTE e uma a uma, e a etapa de quem entrou vem do SETOR (chefe de '
    .'setor encaminha, líder direciona, quem administra o sistema exerce as duas), com selo visível na '
    .'tela dizendo a etapa E a equipe. RETORNO AO CANAL (estrutura, 23/09/2026): concluída a denúncia do '
    .'e-Salvador, o Chefe de Setor registra a RESPOSTA ao processo de origem no detalhe (texto + nº do '
    .'processo); a avulsa concluída registra a ABERTURA do processo (nº obrigatório). Um retorno por '
    .'demanda; a API do e-Salvador é de produção e a escrita está proibida — o ato é registrado aqui, '
    .'feito à mão lá, e a chamada fica atrás de ESALVADOR_LIGADA. O LÍDER É DE UMA EQUIPE: a listagem dele traz só o que foi '
    .'encaminhado às equipes que ele lidera, e a ação sobre denúncia de outra equipe é recusada no '
    .'servidor com o motivo escrito — esconder sem barrar deixaria a fronteira valendo só para quem não '
    .'sabe montar a requisição. Estados: Recebida › Encaminhada ao '
    .'líder › Direcionada aos fiscais | Em operação › Em campo › Concluída, com Devolvida e Arquivada como '
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
    .'como uma PASTA que expande), concedida a chefe de setor, líder de equipe e administrador — o fiscal não '
    .'entra, senão escolheria o próprio trabalho. ⚠️ É PROTÓTIPO: a integração NÃO existe, não há tabela '
    .'nem gravação — as denúncias de partida vêm de config/prototipo_denuncias.php, o vínculo chefia↔área '
    .'vem de config/prototipo_estrutura.php, a redação dos impressos vem de '
    .'config/prototipo_documentos_campo.php e as decisões vivem na sessão de quem navega. Os estágios '
    .'avançados (vistoria, desfecho, documento) são SEMEADOS: quando o aplicativo do fiscal receber a '
    .'denúncia dirigida de verdade, é ele que acrescenta esses passos, e a leitura da tela continua a '
    .'mesma. Pendências que '
    .'isto abre: contrato das APIs do e-Salvador e do Fala Salvador, prazo real de cada canal, canal de devolução, '
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
                .'antigo é levado à tela inicial com o painel abrindo lá.'.' Desde 25/09/2026, como no Codecon: um botão no pé do menu liga o modo (estado na sessão) e cada item e pasta do menu ganha uma CHAVE que abre as permissões só daquela tela; a matriz inteira abre por "Ver todas as telas". O item saiu do menu. Quem liga: o administrador ou a conta com a marca "Pode ativar o Modo Gerente".',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Usuários',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.usuarios.index',
            'breadcrumb' => 'Sistema › Usuários',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: tela de Usuários do Codecon, trazida por pedido do dono '
                .'em 25/09/2026. Quem tem conta e em que setor: inclusão com convite de primeiro acesso por '
                .'e-mail (a conta nasce sem senha conhecida), alteração de setores e situação, reenvio do '
                .'convite, exclusão para a lixeira com restauração e remoção definitiva em três dias — menos '
                .'a conta com histórico, que fica guardada. O Chefe de Setor é um só (marcar outro tira o '
                .'setor de quem tinha, com aviso antes de salvar); administrador só se dá entre '
                .'administradores; ninguém tira o próprio acesso de administrador, se desativa ou se exclui. '
                .'Só o administrador abre a tela. O atalho fica no menu da CONTA, no canto superior direito '
                .'(com Meu Perfil e Sair), como no Codecon — não no menu lateral.'.' Desde 25/09/2026 o que era "Setores" se chama CARGO na tela, e a conta ganhou as duas marcas do Codecon: pode ativar o Modo Gerente e Administrador de usuários (o poder desta tela sem as outras de administração) — só o administrador as dá ou tira.',
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
         * O CAMINHO DA FISCALIZAÇÃO — as duas telas que fecharam a cadeia.
         *
         * As duas eram STUB ("em preparação", Fase 2) e passaram a existir como
         * PROTÓTIPO em 09/09/2026. Com elas, o andaime das telas em preparação
         * ficou sem morador e foi removido: item de menu que promete e não entrega
         * é o defeito que ele existia para evitar, e andaime vazio é o defeito
         * seguinte.
         *
         * A linha "Retorno de Campo" SAIU deste mapa, e não porque a funcionalidade
         * acabou: ela virou a aba "A decidir" de Fiscalizações. Duas linhas para a
         * mesma entrega dariam dois donos à mesma informação — a pergunta "bate com
         * o requisito?" passaria a ter duas respostas, e um dia elas discordariam.
         * O conteúdo dela está incorporado abaixo.
         */
        [
            // Passou de Fiscalização para Sistema em 10/09/2026 (ordem do dono):
            // montar operação é ato de gestão sobre a estrutura, não trabalho de
            // campo.
            'modulo' => 'Sistema',
            'tela' => 'Cadastro de Operação',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.operacoes.index',
            'breadcrumb' => 'Sistema › Cadastro de Operação',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: decisão do dono 09/09/2026 ("faça também já o '
                .'cadastro de operação"), sobre a spec de design e a estrutura de áreas e equipes. '
                .'Entregue como PROTÓTIPO. "A operação é evento; a equipe é organização": o trabalho '
                .'de rua com começo, fim e foco que a gestão monta em cima da estrutura permanente. '
                .'Listagem com busca inteligente (facetas em andamento / planejadas / encerradas / '
                .'permanentes) e formulário com nome, ÁREA, região, bairros alcançados, período '
                .'(início e fim), foco, EQUIPES envolvidas (lista — operação grande junta equipe de '
                .'outra área como reforço), situação e observação. FONTE ÚNICA: esta tela lê e '
                .'escreve o MESMO catálogo que o direcionamento das Denúncias consome (as operações '
                .'saíram de config/prototipo_denuncias.php para config/prototipo_operacoes.php, e '
                .'DenunciasFicticias::operacoes() passou a DELEGAR) — com duas listas, o '
                .'direcionamento ofereceria amanhã uma operação que o cadastro não conhece. REGRAS, '
                .'todas com o motivo escrito na recusa: período com FIM antes do início não passa (a '
                .'operação apareceria encerrada antes de começar); ÁREA é obrigatória, porque é ela '
                .'que decide quem vê e quem executa; NOME é único, porque é por ele que a equipe '
                .'reconhece a operação em rua e que a denúncia a registra ao ser anexada; operação '
                .'ENCERRADA não recebe denúncia nova — sai da escolha do direcionamento e é recusada '
                .'no servidor, continuando consultável aqui. RECORTE POR ÁREA: o líder de equipe '
                .'cadastra e vê as da área das equipes dele; o Chefe de Setor e o administrador veem '
                .'o universo e cadastram em qualquer área (o chefe recebe a operação pedida de cima). '
                .'O recorte é do SERVIDOR e a recusa é NOMINAL, dizendo por quais áreas a pessoa '
                .'responde — na alteração as DUAS áreas são conferidas (a de origem e a de destino), '
                .'senão bastaria mover a operação alheia para a própria área para poder alterá-la. '
                .'Exportação do recorte visível em PDF/XLSX/DOCX pelo ponto único. É PROTÓTIPO: '
                .'não há tabela nem gravação — a lista de partida é config/prototipo_operacoes.php e '
                .'o que se cria ou altera vive na sessão de quem navega. Pendências que isto abre: a '
                .'operação como TABELA, o vínculo dela com os registros de campo que ela produziu '
                .'(hoje a fiscalização avulsa aponta para a operação por TEXTO, no campo referência) '
                .'e o encerramento com resultado consolidado, que a spec previa e ninguém definiu.'.' Desde 25/09/2026 é menu pai próprio, entre os mapas e o Sistema; a operação recebe DENÚNCIAS no próprio cadastro (entram Em operação; desmarcadas, voltam à mesa de onde vieram) e FISCAIS de qualquer área, que recebem a fiscalização junto com os fiscais das equipes; escolher a área ou uma equipe marca os bairros dela, desmarcáveis um a um.',
        ],

        [
            'modulo' => 'Fiscalização',
            'tela' => 'Fiscalizações (A decidir + Acervo)',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.fiscalizacoes.index',
            'breadcrumb' => 'Fiscalização › Fiscalizações',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: decisão do dono 04/09/2026 ("todo registro de '
                .'fiscalização concluído volta para a caixa de entrada do Chefe de Setor, e as '
                .'considerações finais do fiscal aparecem no histórico do trâmite") e 09/09/2026 '
                .'("pode unificar em Fiscalizações com aba a decidir, acervo e outras se '
                .'necessário"). Entregue como PROTÓTIPO. UMA TELA, DUAS ABAS sobre o MESMO registro — '
                .'antes eram duas telas ("Retorno de Campo", construída, e "Fiscalizações", um stub '
                .'que prometia a consulta), o que obrigava o gestor a pular de menu para juntar as '
                .'duas metades da mesma informação e faria as duas divergirem. (a) A DECIDIR, padrão, '
                .'é a FILA do Chefe de Setor: tudo que a equipe da área dele concluiu em rua, com '
                .'quando, equipe e fiscal, o ponto, o desfecho, o documento lavrado (quando houve) e '
                .'— em coluna própria, porque é o que decide — a RECOMENDAÇÃO DO FISCAL, ao lado das '
                .'considerações que ele escreveu. A recomendação chega como CHAVE (é o que o '
                .'aplicativo do fiscal grava, e é o que o relatório soma) e é mostrada na redação '
                .'EXPLÍCITA do catálogo do servidor; chave que o catálogo não conhece aparece CRUA, '
                .'em vez de desaparecer da tela. Duas decisões da chefia, em lote e uma a uma, por '
                .'comando FLUTUANTE que abre uma JANELA: dar CIÊNCIA (o retorno sai da fila e fica no '
                .'acervo; observação opcional, porque o ato de ler já é a informação) ou determinar '
                .'NOVA VISTORIA (o ponto volta à equipe, com justificativa obrigatória de 15 '
                .'caracteres no servidor — "voltar lá" não diz à equipe o que procurar). (b) ACERVO é '
                .'a CONSULTA, sem ação, do que a tela antiga só prometia: tudo o que passou por aqui, '
                .'inclusive o já lido, com QUEM foi encontrado no ponto (nulo é caso previsto — "nada '
                .'encontrado" é desfecho legítimo), o equipamento, as FOTOS, o ponto de GPS com a '
                .'precisão, o documento que saiu na hora e o PRAZO DE RETORNO de quem foi notificado '
                .'— a única informação da fila que continua correndo depois da ciência, contada no '
                .'servidor a partir do prazo do documento. Consulta por ambulante, área e período pela '
                .'BUSCA (facetas: com/sem documento, de denúncia, avulsa, com recomendação, não '
                .'identificado, com foto, prazo vencido, prazo correndo, últimos 7 e 30 dias) — e não '
                .'por filtros segmentados, que é o padrão de busca do projeto. NÃO há terceira aba: '
                .'"por operação" e "por prazo vencido" não são conjuntos diferentes, são recortes que '
                .'a busca entrega. RECORTE POR EQUIPE: o líder vê só o que as equipes dele '
                .'concluíram; o Chefe de Setor e o administrador veem o universo e também decidem, '
                .'porque o retorno volta para a mesa do chefe. O recorte é feito no SERVIDOR, e '
                .'há DUAS recusas explicadas ali: quem apenas consulta não decide, e decisão sobre '
                .'registro de outra área é recusada nominalmente — esconder da lista não é fronteira, '
                .'e o lote é o caminho fácil para alcançar o que não se vê. O item de menu traz o '
                .'CONTADOR da fila, recortado pela mesma regra e apenas para quem decide. O FISCAL '
                .'entra em APENAS LEITURA (decisão do dono, 09/09/2026), com a ressalva registrada de '
                .'que ele é usuário do APLICATIVO — o acesso dele à Retaguarda é improvável e existe '
                .'por completude, não por fluxo; dar ciência do próprio retorno continua recusado. '
                .'Exportação do recorte visível em PDF/XLSX/DOCX, com colunas próprias de cada aba. '
                .'Não há inclusão: registro de fiscalização nasce em rua, no aplicativo do fiscal. O '
                .'endereço aposentado /retaguarda/retorno-de-campo REDIRECIONA para cá, e a concessão '
                .'do slug antigo foi migrada por migration (só UPDATE/DELETE de texto, sem DDL). É '
                .'PROTÓTIPO: não há tabela nem gravação. Os registros que vieram de DENÚNCIA são '
                .'DERIVADOS do trâmite dela (a mesma vistoria descrita duas vezes divergiria), e as '
                .'fiscalizações AVULSAS — operação, ronda, pedido de outro órgão — vêm de '
                .'config/prototipo_registros_de_campo.php; as decisões vivem na sessão de quem '
                .'navega. Pendências que isto abre: a fiscalização como TABELA (hoje ela só existe '
                .'dentro do trâmite da denúncia e do arquivo de avulsas), a MODELAGEM DEFINITIVA do '
                .'vínculo chefia-área, as FOTOS de verdade (o acervo mostra o nome do arquivo e a '
                .'contagem, porque o protótipo não guarda imagem), o efeito real de "nova vistoria" '
                .'no aplicativo do fiscal (hoje só muda o estado da fila) e o prazo de leitura que '
                .'torna um retorno atrasado — a tela já conta os dias parados, mas ninguém definiu a '
                .'partir de quantos ele cobra.',
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
         * Também sem item de menu, e pelo mesmo motivo da casca e da exportação: é
         * a régua que TODA listagem segue. Ela entra no mapa porque é exatamente o
         * tipo de regra transversal que ninguém lembra de conferir depois — e
         * porque foi ordem direta do dono, não decisão de desenho nossa.
         */
        [
            'modulo' => 'Sistema',
            'tela' => 'Padrão de listagem (grade enxuta)',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.fiscalizacoes.index',
            'breadcrumb' => 'Presente em toda listagem da Retaguarda',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: ordem do dono de 09/09/2026 ("as listagens estão '
                .'muito poluídas, muita informação quebrando linha de forma irregular; deixe a informação '
                .'detalhada para quando o usuário clicar — adote como padrão no sistema"). Toda listagem '
                .'mostra uma linha por registro, de altura fixa, com no máximo cinco colunas e nada de '
                .'texto escrito em frase dentro da célula: o que não couber é cortado com reticências e o '
                .'texto inteiro aparece ao passar o mouse. O restante da informação abre no clique na '
                .'linha, e o arquivo exportado continua trazendo as colunas detalhadas. Coluna que mostraria '
                .'o mesmo valor em toda linha não aparece: a de área só existe para quem responde por mais '
                .'de uma, e as de origem e de HU do acompanhamento de requisitos só entram quando têm o que '
                .'informar. Vale para TODAS as listagens da Retaguarda, inclusive as duas de diagnóstico '
                .'(Logs e Acompanhamento de Requisitos), onde a escolha das colunas segue outra pergunta — '
                .'"o que quebrou" e "o que está fora do requisito". Régua e justificativa em '
                .'docs/padroes/listagem-clean.md; colunas declaradas em config/listagens_da_retaguarda.php.',
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
                .'receber saber do que ele fala. As colunas do arquivo são declaradas ao lado das colunas '
                .'da tela (config/listagens_da_retaguarda.php): a tela ficou enxuta por ordem do dono, e o '
                .'arquivo NÃO — ele segue trazendo o que desceu para o detalhe, e isso é conferido por '
                .'teste, para a limpeza da tela não virar perda de dado no documento em silêncio.',
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
                .'apagaria a única trilha de um defeito. A lista mostra quando a falha aconteceu, o código, '
                .'o tipo do erro, em que tela e quem estava usando o sistema; a mensagem completa e o rastro '
                .'abrem ao clicar na linha, e o arquivo exportado continua trazendo tudo. A data desta tela '
                .'leva a HORA porque um mesmo dia costuma ter várias ocorrências.',
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
                .'falam com serviço externo só rodam pelo botão. ⚠️ Desde 10/09/2026 a tela é SÓ DO '
                .'ADMINISTRADOR (ordem do dono): o que ela mostra quando algo está vermelho conta como '
                .'o sistema é montado por dentro, e isso não é decisão de operação — o Chefe de Setor '
                .'saiu da concessão, com migration removendo a linha já gravada. E o check da atividade '
                .'do ambulante baixou de falha para atenção, porque a justificativa do vermelho era o '
                .'cadastro de ambulante, que deixou de existir no mesmo dia.',
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
                .'escrito. A lista mostra o módulo, a funcionalidade e a situação do requisito; a observação '
                .'que descreve a divergência, o caminho no menu e os códigos de HU abrem ao clicar na linha, '
                .'e o arquivo exportado continua trazendo tudo. As colunas de origem e de HU só aparecem '
                .'quando têm o que informar — enquanto tudo é da Retaguarda e nenhuma HU está escrita, elas '
                .'repetiriam o mesmo valor em toda linha.',
        ],

        [
            'modulo' => 'Fiscalização',
            // NÃO é mais "Cadastro de Ambulante": a tela deixou de cadastrar em
            // 10/09/2026. Rótulo que promete o que a tela não faz é o que gera o
            // card "não consigo editar o ambulante".
            'tela' => 'Ambulantes (consulta)',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.ambulantes.index',
            'breadcrumb' => 'Fiscalização › Ambulantes',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: spec de design 2026-08-24, REVISADA pela decisão do '
                .'dono de 10/09/2026 ("a tela de Ambulantes não será CRUD, só irá receber os registros '
                .'do SGCI via integração"). ⚠️ A tela é CONSULTA: o cadastro-mestre dos ambulantes é do '
                .'SGCI (o sistema do comércio informal) e chega ao SEFAL por INTEGRAÇÃO — incluir, '
                .'alterar e excluir saíram da tela E do servidor (as rotas deixaram de existir, junto '
                .'com a validação). Deixar a rota viva com a tela sem botão seria pior: o servidor '
                .'aceitaria escrita de quem montasse a requisição, e a carga seguinte do SGCI desfaria '
                .'em silêncio. A tela DIZ isso a quem abre — de onde vem o dado, que aqui é espelho de '
                .'leitura e que a correção se faz na origem —, no selo do topo e outra vez na ficha, '
                .'onde a dúvida nasce; sem isso a pessoa procura o botão de editar e conclui que o '
                .'sistema está pela metade. O que ficou: listagem enxuta com busca inteligente '
                .'(facetas de permissão, situação, com/sem documento, permissão vencida e o nome de '
                .'cada ramo da parametrização), a FICHA do ambulante em leitura, a foto servida por '
                .'rota autenticada (é retrato de cidadão fiscalizado; disco privado, guarda de leitura '
                .'antes da imagem) e a exportação do recorte visível em PDF/XLSX/DOCX. A identidade '
                .'continua sendo a de campo — foto + apelido, com as iniciais quando não há foto —, e '
                .'ser PERMISSIONÁRIO segue sendo atributo (tem permissão da SEMOP, sim ou não), '
                .'independente da situação: sem permissão pode estar regular, e permissionário pode '
                .'estar irregular. ⚠️ A INTEGRAÇÃO NÃO EXISTE (PEND-001): o que a tela mostra é dado '
                .'de exemplo, para aprovar a forma da consulta — nenhum contrato de API, cliente HTTP '
                .'ou tabela nova foi inventado. Ficou de fora, junto com o cadastro: a validação da '
                .'fila de quarentena (a situação "Cadastrado em campo" segue sendo mostrada, mas '
                .'ninguém a troca por aqui) e a busca no servidor, que é pré-requisito da carga real '
                .'(PEND-012).',
        ],

        [
            'modulo' => 'Caixa de Entrada',
            'tela' => 'Mesa antiga do Chefe de Setor (fora do menu)',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.caixa-de-entrada.index',
            'breadcrumb' => 'fora do menu desde 24/09/2026',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemPrototipo.' ⚠️ FORA DO MENU desde 24/09/2026: as quatro caixas de canal '
                .'substituíram esta tela. Ela continua existindo, só para o chefe, porque guarda a '
                .'PRÉ-TRIAGEM escondida (dono incerto sobre a necessidade) — sai quando isso for decidido. Porta por onde a demanda entra FORA DA INTEGRAÇÃO: papel do '
                .'e-Salvador, pedido de nova licença, ofício e a AVULSA (ligação ou e-mail de superior ao '
                .'chefe, canal criado em 22/09/2026) são digitados aqui pelo Chefe de Setor. O Fala Salvador '
                .'NÃO entra por aqui — é digitado pelo líder, na tela do canal. '
                .'Denúncia pode ser ANÔNIMA. O bairro sugere a equipe responsável (a estrutura Área › '
                .'Equipe), e quem confirma é o Chefe de Setor — bairro pertencente a duas áreas tem duas '
                .'respostas certas. Duas saídas: registrar e encaminhar (vira trabalho dirigido da '
                .'equipe) ou registrar e devolver/arquivar, com motivo de lista MAIS justificativa por '
                .'escrito, porque é ato administrativo. Cada decisão acrescenta uma linha ao trâmite da '
                .'demanda (quem, quando, o quê). A tela é do CHEFE DE SETOR (um só, desde 22/09/2026 — '
                .'o setor coordenador foi removido): receber o que chega e encaminhar à EQUIPE é função '
                .'dele; o líder recebe na tela de Denúncias e o administrador cobre. ⚠️ É PROTÓTIPO: não há '
                .'tabela nem gravação — as '
                .'demandas de partida vêm de config/prototipo_caixa_entrada.php e as decisões vivem na '
                .'sessão de quem navega. Pendências que isto abre: prazo de cada canal, canal de retorno '
                .'ao e-Salvador/156 e a numeração definitiva do protocolo.',
        ],

        [
            'modulo' => 'Caixa de Entrada',
            'tela' => 'e-Salvador',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.denuncias.e-salvador.index',
            'breadcrumb' => 'Caixa de Entrada › e-Salvador',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemDenuncias.' Caixa do e-Salvador (reorganizada em 24/09/2026), com TRÊS abas: '
                .'DENÚNCIAS (assunto 215), LICENÇAS (a licença chega pelo e-Salvador, assunto 216) e '
                .'RESPONDIDAS (o que o chefe já respondeu ao processo, ou saiu do fluxo). Grade única: '
                .'seleção múltipla, protocolo, recebida, bairro, situação em três palavras (Recebida, '
                .'Encaminhada ao líder, Em fiscalização) e prazo. Enquanto a integração não lê, o chefe '
                .'cadastra aqui o que chega em papel (denúncia ou licença). Como o cidadão abre a denúncia '
                .'autenticado, o requerente vem SEMPRE identificado. '.$fluxoDenuncias.' Desde 25/09/2026, nas quatro caixas, quem encaminha tem a coluna "Área (sugerida)": um seletor de equipe (com área e líder) na própria linha, para encaminhar em lote já escolhendo cada destino.',
        ],

        [
            'modulo' => 'Caixa de Entrada',
            'tela' => 'Fala Salvador',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.denuncias.fala-salvador.index',
            'breadcrumb' => 'Caixa de Entrada › Fala Salvador',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemDenuncias.' O canal telefônico da Prefeitura (156) — era "Salvador Digital" até '
                .'22/09/2026. NÃO TEM INTEGRAÇÃO e SÓ OS LÍDERES DE EQUIPE o acessam: o SEFAL é intermediário '
                .'de registro. O LÍDER digita aqui o que recebeu por telefone (nº do atendimento, data, '
                .'anônima ou quem ligou, endereço, bairro, assunto, relato; a equipe fica implícita para '
                .'quem lidera uma só e é escolhida por quem lidera várias — só entre as suas), e o caso '
                .'nasce JÁ NA MESA DELE (Encaminhada ao líder), sem passar pelo chefe, para ele direcionar '
                .'aos fiscais; a resposta ao cidadão continua no Fala Salvador. Desde 25/09/2026 o CHEFE DE '
                .'SETOR também registra (sem integração, o que chega a ele precisa entrar): o caso dele nasce '
                .'Recebida, sem equipe, para ele encaminhar. O formulário é o MÍNIMO para o caso existir no '
                .'fluxo; o específico do canal vem depois (PEND-023). O que o telefone muda no dado: a '
                .'denúncia pode ser ANÔNIMA, o relato é a transcrição do que o atendente ouviu, e não há '
                .'anexo. '.$fluxoDenuncias,
        ],

        [
            'modulo' => 'Caixa de Entrada',
            'tela' => 'e-Protocolo',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.denuncias.e-protocolo.index',
            'breadcrumb' => 'Caixa de Entrada › e-Protocolo',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemDenuncias.' A quarta frente (24/09/2026): o atendimento PRESENCIAL na sede da '
                .'SEFAL, protocolado no sistema e-Protocolo. Sem integração: o Chefe de Setor cadastra. Ainda '
                .'não se sabe se o protocolo passa pelo e-Salvador antes de chegar ao chefe — por isso a caixa '
                .'é própria, e a resposta fica registrada aqui. Abas DENÚNCIAS e RESPONDIDAS. '.$fluxoDenuncias,
        ],

        [
            'modulo' => 'Caixa de Entrada',
            'tela' => 'Avulsas',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.denuncias.avulsas.index',
            'breadcrumb' => 'Caixa de Entrada › Avulsas',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => $origemDenuncias.' O pedido que chega ao Chefe de Setor por fora dos canais, em dois '
                .'tipos: pedido de superior (ligação ou e-mail) e OFÍCIO de órgão ou do Ministério Público — o '
                .'ofício deixou de ser canal em 25/09/2026 e virou tipo de avulsa, com selo na grade. Lista única, '
                .'sem abas. O chefe cadastra dizendo como chegou (o número de origem é opcional), encaminha à equipe e, concluída a fiscalização, DELIBERA: abre processo no '
                .'e-Salvador com o resultado (registrado aqui, feito à mão lá — a escrita na API está '
                .'proibida) ou encerra só com a fiscalização, sem processo. '.$fluxoDenuncias,
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Áreas',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.areas.index',
            'breadcrumb' => 'Sistema › Áreas',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: pedido do dono em 25/09/2026 ("cadastro de Área com '
                .'possibilidade de definição dos bairros de cada área, selecionáveis via chips"). Nome, região, o '
                .'que a área cobre (bairros, corredores ou a cidade inteira), turno e os BAIRROS em chips, com '
                .'busca e inclusão de bairro novo. Dos bairros sai a sugestão de equipe da demanda, os bairros que a '
                .'operação marca sozinha e o recorte do líder nos mapas. Área com equipe, demanda ou operação não se '
                .'exclui — inativa-se. Do administrador e do Chefe de Setor.',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Bairros',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.bairros.index',
            'breadcrumb' => 'Sistema › Bairros',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: pedido do dono em 25/09/2026 ("crie também a tela Bairros '
                .'para cadastro de bairros, para termos um controle melhor. Bairro com Área não se exclui"). O '
                .'catálogo de bairros: nome (único sem acento — "Imbuí" e "Imbui" são o mesmo) e a coordenada que o '
                .'mapa usa. Renomear renomeia também nas áreas e operações que citam o bairro; a demanda antiga guarda '
                .'o nome com que chegou. O cadastro de Áreas oferece os bairros daqui, e o bairro acrescentado lá '
                .'entra no catálogo. Nasceu com os bairros que as áreas já citavam.',
        ],

        [
            'modulo' => 'Sistema',
            'tela' => 'Equipes',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.equipes.index',
            'breadcrumb' => 'Sistema › Equipes',
            'hu_status' => 'nao',
            'hus' => [],
            'nota' => 'Sem requisito escrito — origem: pedido do dono em 25/09/2026 ("preciso de um cadastro de '
                .'equipes"). Quem está em cada equipe: código, área, turno, o LÍDER (a conta que recebe o '
                .'trabalho encaminhado à equipe — só contas ativas do setor Líder de Equipe) e os FISCAIS (só '
                .'contas ativas do setor Fiscal). É daqui que sai o recorte do líder nas telas. Equipe com '
                .'histórico (demanda, vistoria, Fiscalização, operação) não se exclui — inativa-se. Do '
                .'administrador e do Chefe de Setor.',
        ],

        [
            // A seção foi removida em 10/09/2026 e a tela passou para Sistema
            // (ordem do dono); o módulo acompanha, senão o resumo agruparia por
            // uma seção que ninguém acha mais no menu.
            'modulo' => 'Sistema',
            'tela' => 'Áreas e Equipes',
            'origem' => 'Retaguarda',
            'rota' => 'retaguarda.areas-e-equipes.index',
            'breadcrumb' => 'Sistema › Áreas e Equipes',
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
                .'1:1, a Caixa de Entrada sugere e o Chefe de Setor confirma. Cada equipe mostra o LÍDER '
                .'(o encarregado do documento, com conta lider-<código>). ⚠️ É PROTÓTIPO: a lista de '
                .'fiscais de cada equipe é fictícia (o documento nomeia só o encarregado), não há tabela '
                .'nem gravação, e o que a pessoa mexe vive na sessão dela.'.' Fora do menu desde 25/09/2026: a área e os bairros passaram ao cadastro de Áreas, e quem está em cada equipe, ao de Equipes. Segue pelo endereço.',
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
                .'frequência, e qual é o prazo oficial de retorno de uma notificação.'.' Desde 25/09/2026 o líder de equipe também abre o mapa, vendo só as fiscalizações das equipes dele e dos bairros das áreas delas.',
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
                .'Operação — esta apenas leva até lá.'.' Desde 25/09/2026 o líder de equipe também abre o mapa, com o mesmo recorte do Mapa ao Vivo.',
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

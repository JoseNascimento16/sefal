<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Menu da Retaguarda
    |--------------------------------------------------------------------------
    |
    | FONTE ÚNICA do menu lateral. Quem monta a barra é o
    | `HandleInertiaRequests`, que resolve a rota, descarta o item cuja rota
    | ainda não existe e entrega ao front só o que aquele usuário pode ver.
    |
    | Cada seção tem `rotulo`, `itens` e, opcionalmente, `vazio` — o texto que
    | aparece quando a seção existe no plano mas ainda não tem tela pronta. É
    | melhor dizer "chega nas próximas entregas" do que esconder a seção: quem
    | usa o sistema enxerga o caminho que está sendo construído.
    |
    | Cada item tem:
    |   rotulo  — o nome que aparece na tela;
    |   rota    — nome da rota (não a URL: o endereço muda, o nome não);
    |   icone   — chave do ícone, traduzida em `resources/js/lib/icones-menu.ts`;
    |   slug    — identidade da tela no CONTROLE DE ACESSO (ver abaixo);
    |   setores — a SEMENTE da matriz de permissões (ver abaixo);
    |   modal   — opcional: o item ABRE UM PAINEL sobre a tela atual em vez de
    |             navegar. O valor é a chave do painel, que o menu lateral conhece
    |             (`resources/js/components/retaguarda/sidebar.tsx`). Mesmo assim o
    |             item declara `rota`: é dela que a permissão é deduzida, e é ela
    |             que o painel consulta para receber os dados.
    |   curto   — opcional: o rótulo de UMA PALAVRA usado quando o menu está
    |             retraído (a doca), onde cabem ~9 caracteres. Sem ele, a doca usa
    |             a primeira palavra do `rotulo` — o que basta para "Relatórios" e
    |             não basta para as seis telas que começam em "Tipos de…".
    |   oculto  — opcional: `true` esconde o item do MENU sem desligar nada. A rota
    |             segue viva (acessível por endereço), a permissão segue no Modo
    |             Gerente e a tela segue funcionando: o que se tira é o atalho. Serve
    |             para tela pronta que ainda não vai ao ar para o usuário final.
    |   filhos  — opcional: o item é uma PASTA. Ele não leva a lugar nenhum: ele
|             ABRE, mostrando os itens de dentro. Cada filho é um item completo
|             (`rotulo`, `rota`, `icone`, `slug`, `setores`, `curto`…), e a pasta
|             declara só `rotulo`, `icone` e `curto`.
|
|             A pasta NÃO declara `rota`, `slug` nem `setores`: quem tem tela,
|             permissão e concessão são os filhos. Ela aparece quando sobra ao
|             menos um filho visível para aquela pessoa, e desaparece quando não
|             sobra nenhum — assim a decisão de acesso continua tendo um dono só.
|
|             É estrutura GENÉRICA, e não um caso especial: qualquer conjunto de
|             telas irmãs pode virar pasta sem mexer em código. Um nível só de
|             aninhamento, de propósito — menu que abre menu que abre menu é
|             índice, não caminho de trabalho.
|   contador— opcional: o NÚMERO VIVO ao lado do item. O valor é uma chave do
    |             catálogo em `App\Support\ContadoresDoMenu`, que decide como
    |             apurar e com que tom (neutro = tamanho, alerta = fila). Só
    |             declare onde o número muda a decisão de quem olha: cada contador
    |             custa uma contagem por requisição.
    |
    | ── `slug` e `setores`: quem decide o acesso ────────────────────────────
    |
    | Declarar `slug` coloca a tela sob o Modo Gerente: o slug é a chave dela na
    | matriz `setor × tela × ação`, e daí em diante quem manda é a MATRIZ — tanto
    | para o menu quanto para as guardas de leitura e de ação. (Enquanto o
    | bloqueio não está ligado — ver `retaguarda.permissao_enforce` —, o item
    | continua à vista para quem ainda não tem a concessão: sumir do menu sem
    | recado nem registro é justamente o que o rollout evita.) O `slug` tem de
    | ser o primeiro trecho do caminho da rota (`/retaguarda/<slug>/...`), porque
    | é assim que a guarda de leitura descobre a que tela um endereço pertence;
    | `ModoGerenteTest` reprova se os dois discordarem.
    |
    | `setores` é a SEMENTE dessa matriz — a concessão inicial, aplicada uma vez
    | pelo `PermissoesSetorSeeder`. Depois disso, mudar esta lista não muda mais
    | nada: quem concede e quem tira é a tela do Modo Gerente. (O administrador
    | não é semeado: ele é desvio no código, não linha de matriz.)
    |
    | Cada entrada de `setores` aceita duas formas:
    |
    |   'chefe-de-setor'                  → o pacote da tela: vê, opera, inclui e exclui;
    |   'fiscal' => ['excluir' => false]  → o mesmo pacote, com o ajuste declarado.
    |
    | A forma longa existe para o caso em que "este setor usa esta tela" NÃO quer
    | dizer o pacote inteiro — o fiscal, que CONSULTA o cadastro de ambulante
    | pela Retaguarda e cadastra em rua pelo aplicativo. O ajuste fica aqui, junto
    | do resto da declaração da tela: quem lê "quem entra onde" acha tudo num só
    | lugar (ver `CatalogoFuncionalidades::acoesSemente`).
    |
    | Item SEM `slug` fica fora do controle de acesso, e isso é deliberado em dois
    | casos — a tela inicial (barrá-la fecharia um loop de redirecionamento, já
    | que é para lá que a própria negativa manda o usuário) e a área da própria
    | conta (senha e dados pessoais não são decisão de chefia). Fora desses, item
    | restrito a setor SEM slug escaparia da matriz e daria dois donos à mesma
    | decisão: `ModoGerenteTest` reprova.
    |
    */

    'secoes' => [

        [
            'rotulo' => 'Painel',
            'itens' => [
                [
                    'rotulo' => 'Início',
                    'rota' => 'retaguarda.inicio',
                    'icone' => 'inicio',
                    'setores' => [],
                ],
            ],
        ],

        /*
         * Denúncias — o que as ouvidorias da Prefeitura entregam por INTEGRAÇÃO.
         *
         * Vem ANTES de Fiscalização porque é o começo da cadeia: a denúncia chega
         * de fora, é triada pelo coordenador, encaminhada à área e direcionada
         * pelo Chefe de Setor — e só então vira trabalho de rua. O menu desenha a ordem
         * do trabalho.
         *
         * Seção PRÓPRIA, e não itens dentro de Fiscalização, porque estas telas
         * não são trabalho de campo nem cadastro: são o ciclo ADMINISTRATIVO que
         * antecede o campo, com dois papéis decidindo em sequência. E são seção
         * separada da Caixa de Entrada de propósito — lá o coordenador DIGITA
         * o papel que chegou ao balcão; aqui ninguém digita nada, a denúncia
         * chega sozinha pela integração.
         *
         * ── As duas declaram o MESMO slug, e isso é deliberado ───────────────
         *
         * Elas moram sob o mesmo primeiro trecho do caminho
         * (`/retaguarda/denuncias/…`), que é de onde as guardas deduzem a tela: a
         * permissão é UMA, para o módulo, e aparece no Modo Gerente com o nome da
         * seção. Separar a permissão do e-Salvador da do Fala Salvador seria uma
         * decisão que ninguém precisa tomar — quem cuida de denúncia cuida das
         * duas origens.
         *
         * Concessão inicial: administrador, coordenador e Chefe de Setor, que são
         * justamente os papéis do fluxo (o coordenador tria; o Chefe de Setor
         * direciona). O FISCAL
         * não entra — deixá-lo aqui permitiria escolher o próprio trabalho e
         * arquivar o que não quisesse atender; a denúncia chega a ele pelo
         * aplicativo, já dirigida.
         */
        [
            'rotulo' => 'Denúncias',
            'vazio' => 'As denúncias recebidas das ouvidorias aparecem aqui quando você tiver acesso a elas.',
            'itens' => [
                /*
                 * Item de PASTA: ele não leva a lugar nenhum, ele ABRE — os dois
                 * canais são os filhos (decisão do dono, 02/09/2026, depois de ver
                 * os dois soltos no mesmo nível dos demais itens).
                 *
                 * Uma pasta não declara `rota`, `slug` nem `setores`: quem tem
                 * tela, permissão e concessão são os filhos, e a pasta aparece
                 * quando SOBRA ao menos um filho visível. Declarar `setores` aqui
                 * criaria um segundo dono para "quem entra em denúncia" — o filho
                 * diria uma coisa e a pasta outra.
                 */
                [
                    'rotulo' => 'Denúncias',
                    'icone' => 'denuncias',
                    'curto' => 'DENÚNCIA',
                    'filhos' => [
                        [
                            'rotulo' => 'e-Salvador',
                            'rota' => 'retaguarda.denuncias.e-salvador.index',
                            'icone' => 'denuncias',
                            'slug' => 'denuncias',
                            'curto' => 'E-SALV',
                            'setores' => ['administrador', 'coordenador', 'chefe-de-setor'],
                        ],
                        [
                            'rotulo' => 'Fala Salvador',
                            'rota' => 'retaguarda.denuncias.fala-salvador.index',
                            'icone' => 'denuncias',
                            'slug' => 'denuncias',
                            'curto' => 'FALA',
                            'setores' => ['administrador', 'coordenador', 'chefe-de-setor'],
                        ],
                    ],
                ],
            ],
        ],

        /*
         * Fiscalização — o trabalho em si.
         *
         * O fiscal entra aqui, e é o único lugar do menu em que ele entra: saber
         * quem está cadastrado é o que evita chegar na calçada às cegas.
         *
         * ⚠️ NENHUMA tela desta seção grava cadastro de ambulante — a base é do
         * SGCI, e aqui ela é CONSULTA (ver o item "Ambulantes"). Por isso a
         * concessão de todos os setores nesta tela é `apenas_leitura`.
         */
        [
            'rotulo' => 'Fiscalização',
            'vazio' => 'As telas da fiscalização aparecem aqui quando você tiver acesso a elas.',
            'itens' => [
                /*
                 * Ambulantes — CONSULTA da base do SGCI (decisão do dono,
                 * 10/09/2026: "a tela de Ambulantes não será CRUD, só irá receber
                 * os registros do SGCI via integração").
                 *
                 * Era um cadastro completo (incluir, alterar, excluir) desenhado
                 * para o pior caso: o SEFAL sendo o mestre da base. O cliente
                 * decidiu o contrário — o cadastro-mestre é o **SGCI**, o sistema
                 * do comércio informal, e o SEFAL o recebe por INTEGRAÇÃO. Duas
                 * bases da mesma pessoa divergiriam no primeiro ajuste, e a
                 * correção feita aqui seria desfeita na próxima carga.
                 *
                 * Consequência na CONCESSÃO: `apenas_leitura` para TODOS os
                 * setores, e não só para o fiscal. Não é aperto de acesso — é a
                 * matriz parando de prometer o que a tela não faz mais: as rotas
                 * de gravação deixaram de existir, então "inclui" e "exclui"
                 * marcados no Modo Gerente ofereceriam a quem administra uma
                 * decisão sem efeito nenhum.
                 *
                 * ⚠️ `apenas_leitura`, e NÃO "incluir e excluir desligados": é ele
                 * que derruba OPERAR junto — e operar, aqui, era alterar o
                 * cadastro. A distinção nasceu com o fiscal (que tirava da
                 * quarentena o registro que ele mesmo criara em rua) e agora vale
                 * para a tela inteira.
                 *
                 * A correção do dado se faz na ORIGEM, e a tela diz isso a quem
                 * abre — impedimento nunca em silêncio.
                 */
                [
                    'rotulo' => 'Ambulantes',
                    'rota' => 'retaguarda.ambulantes.index',
                    'icone' => 'ambulantes',
                    'slug' => 'ambulantes',
                    'curto' => 'AMBULANTES',
                    // O tamanho da base recebida, ao lado do item. A FILA de
                    // conferência (quem nasceu em rua e espera validação) ganha o
                    // seu contador quando a tela de quarentena existir — o
                    // catálogo já a tem.
                    'contador' => 'ambulantes',
                    'setores' => [
                        // O administrador vai SEM ajuste porque ele não é
                        // semeado (o acesso total dele é desvio no código, não
                        // linha de matriz): declarar `apenas_leitura` aqui seria
                        // config morta fingindo restringir alguém.
                        'administrador',
                        'chefe-de-setor' => ['apenas_leitura' => true],
                        'fiscal' => ['apenas_leitura' => true],
                    ],
                ],

                /*
                 * Caixa de Entrada do Administrativo — PROTÓTIPO (reunião com o
                 * cliente, 02/09/2026).
                 *
                 * Vem ANTES de "Fiscalizações" porque é o começo da cadeia: a
                 * demanda de fora (e-Salvador, Fala Salvador, pedido de nova
                 * licença, ofício) entra por aqui, é triada e só então vira
                 * trabalho dirigido de campo. O menu desenha a ordem do trabalho.
                 *
                 * Concessão inicial: coordenador, administrador e Chefe de Setor. O
                 * COORDENADOR é o dono do trabalho — registrar o que chega em
                 * papel é a função dele (decisão do dono, 02/09/2026); o Chefe de
                 * Setor acompanha o que foi encaminhado. O FISCAL não entra — triar o que
                 * chega, encaminhar e devolver com justificativa é ato
                 * de coordenação, e a demanda encaminhada chega a ele pelo
                 * aplicativo, já dirigida. Dar-lhe a caixa permitiria escolher o
                 * próprio trabalho e arquivar o que não quisesse atender.
                 */
                [
                    'rotulo' => 'Caixa de Entrada',
                    'rota' => 'retaguarda.caixa-de-entrada.index',
                    'icone' => 'caixa',
                    'slug' => 'caixa-de-entrada',
                    'curto' => 'ENTRADA',
                    'setores' => ['administrador', 'coordenador', 'chefe-de-setor'],
                ],
                /*
                 * Fiscalizações — TUDO o que a equipe concluiu em rua, numa tela
                 * só (PROTÓTIPO, decisão do dono 09/09/2026).
                 *
                 * Este item e "Retorno de Campo" eram DOIS, sobre o MESMO registro:
                 * aqui um stub que prometia a consulta por ambulante, área e
                 * período; lá a fila construída, com o desfecho e a recomendação do
                 * fiscal. Duas telas sobre o mesmo dado divergem — uma ganharia
                 * regra nova e a outra continuaria mostrando o mundo de antes — e
                 * obrigavam o gestor a pular de menu para juntar as duas metades da
                 * mesma informação. Viraram duas ABAS: "A decidir" (a fila) e
                 * "Acervo" (a consulta). O item "Retorno de Campo" saiu do menu, e
                 * o endereço dele redireciona para cá.
                 *
                 * Vem DEPOIS da Caixa de Entrada porque é o fim da cadeia: a
                 * demanda entra por lá ou pelas Denúncias, é dirigida, vira
                 * trabalho de rua — e volta para cá. O menu desenha a ordem do
                 * trabalho.
                 *
                 * Concessão inicial: Chefe de Setor (a fila é dele), Coordenador
                 * (acompanha o que aconteceu com o que encaminhou, SEM decidir — a
                 * recusa mora no controller) e administrador.
                 *
                 * ⚠️ O FISCAL entra, em apenas leitura, e isto é decisão do dono
                 * (09/09/2026) COM uma ressalva registrada: **o fiscal é usuário do
                 * aplicativo**, e o acesso dele à Retaguarda é improvável — existe
                 * por completude, não por fluxo. Dar ciência do próprio retorno
                 * continua recusado pelo servidor, o que preserva a conferência que
                 * a fila existe para provocar.
                 *
                 * ⚠️ E o que ele vê é o ACERVO INTEIRO, não "o que ele mesmo
                 * registrou": o recorte por área é do Chefe de Setor, e não existe
                 * hoje vínculo entre a CONTA do fiscal e os registros que ela
                 * assinou (o registro guarda o nome de quem assinou, e a estrutura
                 * guarda a matrícula do fiscal na equipe — nada liga os dois).
                 * Restringir por nome seria adivinhar. Está registrado como
                 * pendência no doc de regra; até lá, a frase honesta é esta.
                 */
                [
                    'rotulo' => 'Fiscalizações',
                    'rota' => 'retaguarda.fiscalizacoes.index',
                    'icone' => 'fiscalizacoes',
                    'slug' => 'fiscalizacoes',
                    'curto' => 'REGISTROS',
                    // A FILA, ao lado do item: é o gatilho de trabalho de quem
                    // decide — "tenho 7 retornos esperando, começo por ali". Zero
                    // não vira selo (ver `App\Support\ContadoresDoMenu`).
                    'contador' => 'fiscalizacoes-a-decidir',
                    'setores' => [
                        'administrador',
                        'coordenador',
                        'chefe-de-setor',
                        'fiscal' => ['apenas_leitura' => true],
                    ],
                ],
                [
                    'rotulo' => 'Mapa ao Vivo',
                    'rota' => 'retaguarda.mapa.index',
                    'icone' => 'mapa',
                    'slug' => 'mapa',
                    'curto' => 'MAPA',
                    'setores' => [
                        'administrador',
                        'chefe-de-setor',
                        // Saber onde a cidade está agora é do trabalho de rua.
                        'fiscal' => ['apenas_leitura' => true],
                    ],
                ],
                [
                    'rotulo' => 'Mapa de Calor',
                    'rota' => 'retaguarda.mapa-de-calor.index',
                    'icone' => 'calor',
                    'slug' => 'mapa-de-calor',
                    'curto' => 'CALOR',
                    // Concentração histórica serve para PLANEJAR: é leitura de
                    // gestão, não de quem está na calçada agora.
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],
            ],
        ],

        /*
         * ⚠️ A seção "Estrutura" NÃO existe mais, e a remoção é o ponto — não
         * sobra casca (decisão do dono, 10/09/2026: "crie no menu 'Sistemas' e
         * coloque como filhos: Áreas e Equipes; Cadastro de Operação").
         *
         * Ela existia para UMA tela — Áreas e Equipes —, que passou para o menu de
         * Sistema. Deixá-la declarada e vazia não teria dado em nada apagado: a
         * seção trazia `vazio`, e é justamente o `vazio` que faz a casca DESENHAR
         * o título com o recado "aparece aqui quando você tiver acesso" (ver
         * `HandleInertiaRequests::menu`). O resultado seria um título de seção
         * eternamente órfão anunciando uma tela que mudou de lugar — pior do que
         * não ter seção.
         */

        /*
         * Parametrização — as listas que o resto do sistema oferece para
         * escolher, e que a gestão mantém.
         *
         * As seis telas declaram o MESMO `slug`, e isso é deliberado: elas moram
         * sob o mesmo primeiro trecho do caminho (`/retaguarda/parametrizacao/…`),
         * que é de onde as guardas deduzem a tela — a permissão é uma só, para o
         * conjunto, e aparece no Modo Gerente com o nome da seção. Separar a
         * permissão de "motivos de recusa" da de "tipos de operação" seria uma
         * decisão que ninguém precisa tomar e seis linhas a mais na matriz.
         *
         * Chefe de Setor e administrador: manter estas listas é ato de gestão da
         * operação. O fiscal as CONSOME em rua, pelo aplicativo — não as edita.
         *
         * ⚠️ AS SEIS ESTÃO `oculto` (decisão do dono, 27/08/2026): saíram do MENU e
         * nada mais. As telas funcionam, as rotas respondem pelo endereço e a
         * permissão continua no Modo Gerente — a seção inteira desaparece da barra
         * porque não sobra item visível nela, e é isso que se queria. Para trazê-las
         * de volta, tire o `oculto`; nenhuma outra mudança é necessária.
         */
        [
            'rotulo' => 'Parametrização',
            'itens' => [
                [
                    'rotulo' => 'Tipos de Infração',
                    'rota' => 'retaguarda.parametrizacao.tipos-de-infracao.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'INFRAÇÕES',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],
                [
                    'rotulo' => 'Atividades do Ambulante',
                    'rota' => 'retaguarda.parametrizacao.atividades-do-ambulante.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'ATIVIDADES',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],
                [
                    'rotulo' => 'Unidades de Medida',
                    'rota' => 'retaguarda.parametrizacao.unidades-de-medida.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'UNIDADES',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],
                [
                    'rotulo' => 'Tipos de Operação',
                    'rota' => 'retaguarda.parametrizacao.tipos-de-operacao.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'OPERAÇÕES',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],
                [
                    'rotulo' => 'Origens de Operação',
                    'rota' => 'retaguarda.parametrizacao.origens-de-operacao.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'ORIGENS',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],
                [
                    'rotulo' => 'Motivos de Recusa',
                    'rota' => 'retaguarda.parametrizacao.motivos-de-recusa.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'RECUSAS',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],
            ],
        ],

        /*
         * Sistema — o que se CONFIGURA e o que se acompanha.
         *
         * Desde 10/09/2026 ela abriga também os dois CADASTROS que a gestão
         * mantém: Áreas e Equipes (que era uma seção "Estrutura" só para ela) e
         * Cadastro de Operação (que estava em Fiscalização). Ordem do dono —
         * "coloque como filhos: Áreas e Equipes; Cadastro de Operação".
         *
         * São itens DIRETOS, e não uma pasta "Cadastros" dentro de Sistema: a
         * pasta existe para o conjunto que só faz sentido junto (os dois canais de
         * Denúncia), e aqui seriam dois itens escondidos atrás de um clique a
         * mais, sem nada em comum além de "não é diagnóstico".
         *
         * Eles vêm ANTES do bloco de diagnóstico (Relatórios, Monitoramento, Logs,
         * Requisitos, Modo Gerente) porque a leitura desce do que se opera para o
         * que se audita.
         */
        [
            'rotulo' => 'Sistema',
            'itens' => [
                [
                    'rotulo' => 'Meu Perfil',
                    'rota' => 'profile.edit',
                    'icone' => 'perfil',
                    'curto' => 'PERFIL',
                    'setores' => [],
                ],

                /*
                 * Áreas e Equipes — a estrutura PERMANENTE da fiscalização (Área >
                 * Equipe > encarregado > bloco de bairros). É dela que sai a
                 * derivação bairro → equipe que a Caixa de Entrada usa para sugerir
                 * o destino de cada demanda. "A operação é evento; a equipe é
                 * organização."
                 *
                 * O fiscal não entra: quem desenha a divisão da cidade e nomeia
                 * encarregado é a gestão.
                 *
                 * ⚠️ O `slug` é o de sempre (`areas-e-equipes`), e mudar de seção
                 * NÃO o toca: slug é a identidade da tela na matriz de permissões,
                 * e renomeá-lo tiraria a tela do controle de acesso e mataria a
                 * concessão de quem já a tem.
                 */
                [
                    'rotulo' => 'Áreas e Equipes',
                    'rota' => 'retaguarda.areas-e-equipes.index',
                    'icone' => 'areas',
                    'slug' => 'areas-e-equipes',
                    'curto' => 'ÁREAS',
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],

                /*
                 * Cadastro de Operação — PROTÓTIPO (09/09/2026).
                 *
                 * "A operação é evento; a equipe é organização": o trabalho de rua
                 * com começo, fim e foco que a gestão monta em cima da estrutura de
                 * áreas e equipes.
                 *
                 * Concessão inicial: o Chefe de Setor CADASTRA (é ele que responde
                 * pelo trabalho de rua da área dele) e o COORDENADOR consulta — ele
                 * tria a entrada e precisa saber que operação existe para onde
                 * encaminhar a demanda, mas montar a operação não é dele. A recusa
                 * do ato dele mora no controller, dizendo o motivo.
                 *
                 * O FISCAL não entra: planejar operação é ato de gestão, e ele
                 * recebe o trabalho já dirigido, pelo aplicativo.
                 *
                 * ⚠️ Mesma ressalva do item acima: o `slug` (`operacoes`) não muda
                 * ao trocar de seção.
                 */
                [
                    'rotulo' => 'Cadastro de Operação',
                    'rota' => 'retaguarda.operacoes.index',
                    'icone' => 'operacoes',
                    'slug' => 'operacoes',
                    'curto' => 'OPERAÇÃO',
                    'setores' => [
                        'administrador',
                        'chefe-de-setor',
                        'coordenador' => ['apenas_leitura' => true],
                    ],
                ],

                [
                    'rotulo' => 'Relatórios',
                    'rota' => 'retaguarda.relatorios.index',
                    'icone' => 'relatorios',
                    'slug' => 'relatorios',
                    // Gestão da operação: o Chefe de Setor emite; o fiscal, que trabalha
                    // em rua pelo aplicativo, não tem o que fazer aqui.
                    'setores' => ['administrador', 'chefe-de-setor'],
                ],
                [
                    'rotulo' => 'Monitoramento',
                    'rota' => 'retaguarda.monitoramento.index',
                    'icone' => 'monitoramento',
                    'curto' => 'MONITOR',
                    'slug' => 'monitoramento',
                    /*
                     * SÓ O ADMINISTRADOR (ordem do dono, 10/09/2026: "somente
                     * admin pode ver Monitoramento").
                     *
                     * O Chefe de Setor tinha a concessão — a leitura era "quem
                     * responde por 'o sistema está de pé?' é quem administra e
                     * quem gerencia a operação". O dono decidiu o contrário, e a
                     * razão cabe no dado: a tela é diagnóstico do AMBIENTE
                     * (conta de administrador ativa, armazenamento gravável,
                     * listas obrigatórias vazias) e o que ela mostra quando algo
                     * está vermelho conta como o sistema é montado por dentro.
                     * Não é decisão de operação — é de quem administra.
                     *
                     * Declarar `['administrador']` é a forma que o projeto usa
                     * para "só admin", e não `[]`: o administrador NÃO é semeado
                     * (o seeder o pula, porque o acesso total dele é desvio no
                     * código), então as duas formas geram ZERO linha de matriz —
                     * mas esta diz em voz alta a quem a tela pertence, em vez de
                     * deixar quem lê concluir que alguém esqueceu de preencher.
                     * É o que Logs e Acompanhamento de Requisitos já fazem.
                     *
                     * ⚠️ Mudar esta lista NÃO tira concessão de banco já semeado
                     * (a semente se aplica uma vez). Quem tira a linha órfã do
                     * Chefe de Setor é a migration
                     * `2026_09_10_090000_monitoramento_passa_a_ser_so_do_administrador`.
                     */
                    'setores' => ['administrador'],
                ],
                [
                    'rotulo' => 'Logs',
                    'rota' => 'retaguarda.logs.index',
                    'icone' => 'logs',
                    'slug' => 'logs',
                    // Só o administrador: a ocorrência guarda o endereço e o verbo
                    // de uma requisição que deu errado, e isso conta bastante
                    // sobre o que existe do outro lado.
                    'setores' => ['administrador'],
                ],
                [
                    'rotulo' => 'Acompanhamento de Requisitos',
                    'rota' => 'retaguarda.acompanhamento-de-requisitos.index',
                    'icone' => 'requisitos',
                    'curto' => 'REQUISITOS',
                    'slug' => 'acompanhamento-de-requisitos',
                    // Só o administrador: a tela é o retrato da CONSTRUÇÃO do
                    // sistema (o que tem requisito escrito, o que divergiu), não
                    // da operação. Quem fiscaliza e quem gerencia a fiscalização
                    // não têm decisão a tomar a partir dela.
                    'setores' => ['administrador'],
                ],
                [
                    'rotulo' => 'Modo Gerente',
                    'rota' => 'retaguarda.modo-gerente.index',
                    'icone' => 'permissoes',
                    'curto' => 'ACESSOS',
                    'slug' => 'modo-gerente',
                    // Abre SOBRE a tela atual, em vez de navegar: quem distribui
                    // acesso está no meio de uma conferência, e ir para outra
                    // página fazia perder o lugar. A `rota` continua declarada —
                    // é dela que sai a permissão (e é ela que alimenta o painel),
                    // então tirá-la deixaria o item fora da matriz.
                    'modal' => 'modo-gerente',
                    // Só o administrador, e por desvio (não por linha semeada):
                    // quem distribui acesso não pode distribuir a si mesmo o
                    // poder de distribuir acesso.
                    'setores' => ['administrador'],
                ],
            ],
        ],

    ],

];

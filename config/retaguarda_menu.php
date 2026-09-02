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
    |   'gestor'                          → o pacote da tela: vê, opera, inclui e exclui;
    |   'fiscal' => ['excluir' => false]  → o mesmo pacote, com o ajuste declarado.
    |
    | A forma longa existe para o caso em que "este setor usa esta tela" NÃO quer
    | dizer o pacote inteiro — o fiscal, que CONSULTA o cadastro de permissionário
    | pela Retaguarda e cadastra em rua pelo aplicativo. O ajuste fica aqui, junto
    | do resto da declaração da tela: quem lê "quem entra onde" acha tudo num só
    | lugar (ver `CatalogoFuncionalidades::acoesSemente`).
    |
    | Item SEM `slug` fica fora do controle de acesso, e isso é deliberado em dois
    | casos — a tela inicial (barrá-la fecharia um loop de redirecionamento, já
    | que é para lá que a própria negativa manda o usuário) e a área da própria
    | conta (senha e dados pessoais não são decisão de gestor). Fora desses, item
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
         * Fiscalização — o trabalho em si.
         *
         * O fiscal entra aqui, e é o único lugar do menu em que ele entra: o
         * cadastro é a identidade de quem ele vai fiscalizar em rua, e chegar
         * na calçada sem saber quem está cadastrado é trabalhar às cegas.
         *
         * Mas ele entra para CONSULTAR, e só. Quem grava cadastro pela
         * Retaguarda é a gestão: o fiscal cadastra em RUA, pelo aplicativo, e o
         * que nasce em rua entra em quarentena até o gestor conferir — criar
         * direto de mesa passaria ao largo dessa conferência, e apagar cadastro
         * fiscalizado deixaria o histórico sem alvo. Daí o ajuste na semente
         * (ver `CatalogoFuncionalidades::acoesSemente`).
         *
         * ⚠️ O ajuste é `apenas_leitura`, e NÃO "incluir e excluir desligados".
         * A diferença é a que decide se a quarentena existe de verdade: com
         * `habilitado` ainda ligado, o fiscal ALTERAVA o cadastro — e a situação
         * é campo do mesmo formulário, então ele tirava da fila o registro que
         * ele mesmo tinha acabado de criar em rua, sem ninguém conferir nada.
         * "Só consulta" derruba operar, incluir e excluir de uma vez, que é o
         * que a frase acima sempre quis dizer.
         *
         * Isto é a CONCESSÃO INICIAL. Alargar ou apertar depois é ato do gestor
         * no Modo Gerente, e está registrado no doc de regra da tela.
         */
        [
            'rotulo' => 'Fiscalização',
            'vazio' => 'As telas da fiscalização aparecem aqui quando você tiver acesso a elas.',
            'itens' => [
                [
                    'rotulo' => 'Permissionários',
                    'rota' => 'retaguarda.permissionarios.index',
                    'icone' => 'permissionarios',
                    'slug' => 'permissionarios',
                    'curto' => 'AMBULANTES',
                    // O tamanho do cadastro, ao lado do item. A FILA de conferência
                    // (quem nasceu em rua e espera validação) ganha o seu contador
                    // quando a tela de quarentena existir — o catálogo já a tem.
                    'contador' => 'permissionarios',
                    'setores' => [
                        'administrador',
                        'gestor',
                        'fiscal' => ['apenas_leitura' => true],
                    ],
                ],

                /*
                 * AS QUATRO A SEGUIR SÃO STUBS — a tela existe, responde e mostra
                 * "em preparação"; o conteúdo chega nas Fases 2 e 3
                 * (`TelasEmPreparacaoController`).
                 *
                 * Entram no menu agora porque o caminho do trabalho tem de estar
                 * visível: quem abre o sistema precisa ver que fiscalização,
                 * operação e mapa fazem parte dele, e em que ordem chegam. O que
                 * NÃO se pode é prometer link e não ter endereço — daí o stub, com
                 * a espera dita dentro da tela em vez de num item morto na barra.
                 *
                 * A concessão inicial segue o mesmo critério das telas prontas: o
                 * fiscal consulta o que é do trabalho DELE (o que registrou em
                 * campo e onde a cidade está agora) e não entra no que é de gestão
                 * (planejar operação, analisar concentração). Ele não grava nada
                 * pela Retaguarda — grava em rua, pelo aplicativo.
                 */
                [
                    'rotulo' => 'Cadastro de Operação',
                    'rota' => 'retaguarda.operacoes.index',
                    'icone' => 'operacoes',
                    'slug' => 'operacoes',
                    'curto' => 'OPERAÇÃO',
                    // Planejar operação é ato de gestão: o fiscal executa em rua.
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Fiscalizações',
                    'rota' => 'retaguarda.fiscalizacoes.index',
                    'icone' => 'fiscalizacoes',
                    'slug' => 'fiscalizacoes',
                    'curto' => 'REGISTROS',
                    'setores' => [
                        'administrador',
                        'gestor',
                        // O fiscal CONSULTA o que ele mesmo registrou em campo.
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
                        'gestor',
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
                    'setores' => ['administrador', 'gestor'],
                ],
            ],
        ],

        /*
         * Parametrização — as listas que o resto do sistema oferece para
         * escolher, e que o gestor mantém.
         *
         * As seis telas declaram o MESMO `slug`, e isso é deliberado: elas moram
         * sob o mesmo primeiro trecho do caminho (`/retaguarda/parametrizacao/…`),
         * que é de onde as guardas deduzem a tela — a permissão é uma só, para o
         * conjunto, e aparece no Modo Gerente com o nome da seção. Separar a
         * permissão de "motivos de recusa" da de "tipos de operação" seria uma
         * decisão que ninguém precisa tomar e seis linhas a mais na matriz.
         *
         * Gestor e administrador: manter estas listas é ato de gestão da
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
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Atividades do Ambulante',
                    'rota' => 'retaguarda.parametrizacao.atividades-do-ambulante.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'ATIVIDADES',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Unidades de Medida',
                    'rota' => 'retaguarda.parametrizacao.unidades-de-medida.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'UNIDADES',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Tipos de Operação',
                    'rota' => 'retaguarda.parametrizacao.tipos-de-operacao.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'OPERAÇÕES',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Origens de Operação',
                    'rota' => 'retaguarda.parametrizacao.origens-de-operacao.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'ORIGENS',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Motivos de Recusa',
                    'rota' => 'retaguarda.parametrizacao.motivos-de-recusa.index',
                    'icone' => 'parametrizacao',
                    'curto' => 'RECUSAS',
                    'slug' => 'parametrizacao',
                    'oculto' => true,
                    'setores' => ['administrador', 'gestor'],
                ],
            ],
        ],

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
                [
                    'rotulo' => 'Relatórios',
                    'rota' => 'retaguarda.relatorios.index',
                    'icone' => 'relatorios',
                    'slug' => 'relatorios',
                    // Gestão da operação: o gestor emite; o fiscal, que trabalha
                    // em rua pelo aplicativo, não tem o que fazer aqui.
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Monitoramento',
                    'rota' => 'retaguarda.monitoramento.index',
                    'icone' => 'monitoramento',
                    'curto' => 'MONITOR',
                    'slug' => 'monitoramento',
                    // Diagnóstico do ambiente: quem responde por "o sistema está
                    // de pé?" é quem administra e quem gerencia a operação. O
                    // fiscal trabalha em rua, pelo aplicativo.
                    'setores' => ['administrador', 'gestor'],
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

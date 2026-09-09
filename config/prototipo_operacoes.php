<?php

/*
|--------------------------------------------------------------------------
| PROTÓTIPO — Operações de fiscalização
|--------------------------------------------------------------------------
|
| ⚠️ DADO DE PROTÓTIPO. Nada aqui é gravado: o Cadastro de Operação lê daqui e
| guarda o que a pessoa cria ou altera na SESSÃO do navegador (ver
| `App\Support\Prototipo\OperacoesFicticias`).
|
| ── Por que este arquivo existe (e por que ele NÃO nasceu junto das denúncias) ─
|
| A operação existia só como lista dentro de `config/prototipo_denuncias.php`,
| porque o primeiro lugar que precisou dela foi o direcionamento — o Chefe de
| Setor anexando denúncia a uma operação já planejada. Com o Cadastro de Operação,
| passaram a existir DOIS interessados no mesmo catálogo, e a lei do projeto é
| clara: a mesma informação com dois donos sempre diverge. Se o cadastro tivesse
| a lista dele, no dia seguinte o direcionamento ofereceria uma operação que o
| cadastro não conhece — e recusaria uma que ele acabou de criar.
|
| Então o catálogo é UM, mora aqui, e quem o lê passa por `OperacoesFicticias`.
| `DenunciasFicticias::operacoes()` delega para ela; não tem lista própria.
|
| ── As datas são RELATIVAS de propósito ─────────────────────────────────────
|
| `inicio_ha_dias` e `fim_em_dias` viram data na hora de servir a tela (negativo
| = passado, positivo = futuro, `null` no fim = sem prazo de encerramento). Data
| fixa envelhece: semanas depois da demonstração toda operação apareceria
| encerrada, e o dono leria isso como comportamento do sistema.
|
| ── O que cada operação declara ─────────────────────────────────────────────
|
| `area` é obrigatória e é a chave do trabalho: é ela que decide QUEM vê a
| operação (o Chefe de Setor vê as da área dele) e quem a executa. Precisa ser um
| nome de área existente em `config/prototipo_estrutura.php` — área inventada aqui
| deixaria a operação órfã, sem aparecer para chefe nenhum.
|
| `equipes` é LISTA de códigos de equipe: operação grande junta equipe de mais de
| uma área (a Noturna reforçando a orla no verão é o caso real). O campo nasceu
| singular no direcionamento, e ficar singular obrigaria a inventar uma segunda
| operação só para registrar a segunda equipe.
|
| `bairros` é o alcance dentro da área — o recorte que a equipe vai varrer. Vazio
| significa "a área inteira", e não "nenhum".
|
| `situacao` é uma de `situacoes`, abaixo. A operação ENCERRADA não recebe
| denúncia nova: o direcionamento não a oferece e o servidor a recusa dizendo o
| porquê (ver `DenunciasController::operacao`).
|
*/

return [

    /*
     * As situações de uma operação, na ordem em que ela anda. Lista fechada
     * porque é escolha de formulário e faceta de busca — texto livre aqui viraria
     * "em curso", "andando", "ativa" para a mesma coisa.
     */
    'situacoes' => [
        'Planejada',
        'Em andamento',
        'Encerrada',
    ],

    'operacoes' => [

        [
            'id' => 1,
            'nome' => 'Operação Verão — Orla',
            'area' => 'Área 5',
            'equipes' => ['C1', 'N1'],
            'regiao' => 'Orla de Itapuã a Boca do Rio',
            'bairros' => ['Boca do Rio', 'Costa Azul', 'Itapuã', 'Patamares'],
            'inicio_ha_dias' => 38,
            'fim_em_dias' => 22,
            'situacao' => 'Em andamento',
            'foco' => 'Orla de Itapuã a Boca do Rio, com ênfase em barracas de praia.',
            'observacao' => 'Reforço da equipe Noturna nas sextas e nos sábados, quando a ocupação '
                .'avança sobre a faixa de areia liberada.',
        ],

        [
            'id' => 2,
            'nome' => 'Rotina Centro',
            'area' => 'Área 1',
            'equipes' => ['C2'],
            'regiao' => 'Centro Histórico e Comércio',
            'bairros' => ['Barris', 'Centro Histórico', 'Comércio'],
            'inicio_ha_dias' => 210,
            // Sem data de encerramento: é rotina permanente, e inventar um fim
            // faria a tela mostrar prazo onde não há.
            'fim_em_dias' => null,
            'situacao' => 'Em andamento',
            'foco' => 'Varredura semanal do Centro Histórico, Comércio e Barris.',
            'observacao' => 'Toda quinta-feira, das 8h às 12h. É a operação que absorve a denúncia '
                .'isolada do Centro, em vez de gerar ida avulsa.',
        ],

        [
            'id' => 3,
            'nome' => 'Operação Feira de São Joaquim',
            'area' => 'Área 2',
            'equipes' => ['A1'],
            'regiao' => 'Entorno da feira e acesso da Calçada',
            'bairros' => ['Calçada', 'Ribeira'],
            'inicio_ha_dias' => 4,
            'fim_em_dias' => 10,
            'situacao' => 'Em andamento',
            'foco' => 'Entorno da feira e acesso da Calçada.',
            // A Área 2 não tem conta de Chefe de Setor na demonstração: esta é a
            // operação que só o Coordenador e o administrador enxergam — o recorte
            // por área em funcionamento, do lado do cadastro.
            'observacao' => 'Pedido da administração da feira, com apoio da Transalvador no acesso '
                .'da Calçada.',
        ],

        [
            'id' => 4,
            'nome' => 'Operação Volta às Aulas — Cajazeiras',
            'area' => 'Área 6',
            'equipes' => ['B1'],
            'regiao' => 'Entorno de escolas',
            'bairros' => ['Cajazeiras', 'Sussuarana', 'Tancredo Neves'],
            'inicio_ha_dias' => -3,
            'fim_em_dias' => 11,
            // Ainda não começou: existe planejada para a equipe se preparar, e é o
            // caso que prova que "planejada" não é o mesmo que "em andamento".
            'situacao' => 'Planejada',
            'foco' => 'Entorno de escolas em Cajazeiras, Sussuarana e Tancredo Neves.',
            'observacao' => 'Começa no primeiro dia de aula da rede municipal.',
        ],

        [
            'id' => 5,
            'nome' => 'Operação Noturna — Corredor da Vitória',
            'area' => 'Noturna',
            'equipes' => ['N1'],
            'regiao' => 'Corredor da Vitória',
            'bairros' => ['Vitória'],
            'inicio_ha_dias' => 60,
            'fim_em_dias' => null,
            'situacao' => 'Em andamento',
            'foco' => 'Som alto e mesas no logradouro depois das 22h.',
            'observacao' => 'Sextas e sábados, das 22h às 2h.',
        ],

        [
            'id' => 6,
            'nome' => 'Operação Largo de Brotas',
            'area' => 'Área 3',
            'equipes' => ['A2'],
            'regiao' => 'Largo de Brotas e entorno do mercado',
            'bairros' => ['Brotas', 'Engenho Velho de Brotas'],
            'inicio_ha_dias' => 12,
            'fim_em_dias' => 6,
            'situacao' => 'Em andamento',
            'foco' => 'Ocupação da calçada no entorno do mercado e das rampas de acesso.',
            'observacao' => 'Nasceu do mapa de calor: o largo concentrou o maior número de '
                .'reincidências do trimestre.',
        ],

        /*
         * ENCERRADA de propósito. É ela que prova, na demonstração, as duas coisas
         * que a regra exige: o direcionamento das denúncias NÃO a oferece, e o
         * servidor recusa a inclusão dizendo o porquê.
         */
        [
            'id' => 7,
            'nome' => 'Operação Réveillon — Boca do Rio',
            'area' => 'Área 5',
            'equipes' => ['C1', 'N1'],
            'regiao' => 'Arena e entorno do palco',
            'bairros' => ['Boca do Rio', 'Costa Azul'],
            'inicio_ha_dias' => 260,
            'fim_em_dias' => -244,
            'situacao' => 'Encerrada',
            'foco' => 'Comércio ambulante na arena do réveillon e no entorno do palco.',
            'observacao' => 'Encerrada com o desmonte da arena. Fica no acervo para consulta: o que '
                .'foi lavrado ali segue valendo, e o histórico é a régua da operação do ano que vem.',
        ],

    ],

];

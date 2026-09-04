<?php

/*
|--------------------------------------------------------------------------
| PROTÓTIPO — Estrutura de fiscalização: Área > Equipe > bloco de bairros
|--------------------------------------------------------------------------
|
| ⚠️ Este arquivo é DADO DE PROTÓTIPO. Ele existe para o dono olhar a tela e
| decidir a forma antes de a estrutura virar tabela, cadastro e migration.
| Nada aqui é gravado: a tela de Áreas e Equipes lê daqui e guarda o que a
| pessoa mexe na SESSÃO do navegador (ver `App\Support\Prototipo\EstruturaFicticia`).
|
| ── O que é REAL e o que é INVENTADO ────────────────────────────────────────
|
| REAL, transcrito do documento do cliente "ÁREAS DAS EQUIPES ATUALIZADA -
| 17/04/2026": as 8 áreas, os nomes delas, o código de cada equipe, o nome do
| encarregado e o bloco de bairros de cada uma (151 bairros distintos).
|
| INVENTADO: a lista de fiscais de cada equipe (nome e matrícula), o turno da
| equipe Noturna e o CHEFE DE SETOR de cada área. O documento nomeia só o
| encarregado; o time de campo o cliente ainda vai informar (PEND-022 trata da
| derivação bairro → equipe).
|
| ── `chefe_de_setor` é o vínculo CHEFE DE SETOR ↔ ÁREA, e não o encarregado ──
|
| São duas pessoas diferentes, e confundi-las é fácil: o `encarregado` chefia a
| equipe EM CAMPO (vem do documento do cliente); o `chefe_de_setor` é quem
| responde pela área DENTRO DO SISTEMA — recebe a denúncia encaminhada, decide se
| ela vai a uma equipe ou entra numa operação, e recebe de volta o que a equipe
| concluiu em campo (decisão do dono, 02/09/2026: "pra ele só
| interessa o que for direcionado para a área dele").
|
| O vínculo mora AQUI, junto da área, e a ligação com a conta é pela `matricula`.
| É o jeito honesto e barato para o protótipo: uma fonte só, a mesma que a tela de
| Áreas e Equipes já mostra. ⚠️ NÃO é a modelagem definitiva — em produção o
| vínculo é entre USUÁRIO e área (uma pessoa pode responder por mais de uma, e
| chefe de setor entra e sai), e isso é tabela, não arquivo de configuração.
| Registrado como pendência no doc de regra.
|
| Só três dos oito chefes de setor têm conta de demonstração (`gestor1`,
| `gestor2`, `gestor3` — a MATRÍCULA ficou como nasceu, porque matrícula
| identifica gente e não cargo): o nome dos outros cinco existe para quem tria
| sempre ver PARA QUEM está encaminhando, mesmo nas áreas em que ninguém entra no
| sistema ainda.
|
| ── Os dois casos que NÃO são "área com bloco de bairros" ────────────────────
|
| `Itinerante` cobre CORREDORES (Avenida Sete, Comércio, Avenida Joana Angélica)
| e não um bloco fechado de bairros; `Noturna` cobre "todos os bairros" e o
| recorte dela é o TURNO, não a geografia. Por isso cada área declara `recorte`
| — `bairros`, `corredores` ou `cidade` —, e a tela desenha cada uma pelo que ela
| é. Tratar as oito como iguais faria a Noturna aparecer com "0 bairros", que é
| a leitura errada: ela cobre todos.
|
| ── Bairro em mais de uma área NÃO é erro ───────────────────────────────────
|
| MUSSURUNGA, PATAMARES e JARDIM DAS MARGARIDAS aparecem em duas áreas. O
| vínculo bairro↔equipe não é 1:1: o sistema SUGERE a equipe e o coordenador
| CONFIRMA. A tela mostra isso como aviso informativo, nunca como pendência a
| corrigir.
|
| ⚠️ Divergência conhecida entre as fontes, para o dono decidir (ver o relatório
| do protótipo): no PDF esses três bairros aparecem DUAS VEZES dentro da própria
| Área 5, e é o doc de cenário da reunião que os coloca em Área 5 e Área 6. Aqui
| valeu o doc de cenário — é a régua aprovada, e é o caso que o dono pediu para
| ver na tela.
|
*/

return [

    /*
     * Os turnos, para o recorte da equipe Noturna. Lista curta e fechada: é
     * escolha de formulário, não texto livre.
     */
    'turnos' => [
        'Diurno',
        'Noturno',
        'Diurno e noturno',
    ],

    'areas' => [

        [
            'id' => 1,
            'nome' => 'Área 1',
            'regiao' => 'Centro',
            'equipe' => 'C2',
            'encarregado' => 'José Roberto',
            // Quem responde pela ÁREA dentro do sistema — não é o encarregado de
            // campo. `matricula` liga o Chefe de Setor à conta; null = área sem
            // conta de demonstração (ver o cabeçalho).
            'chefe_de_setor' => ['nome' => 'Marta Nogueira Prado', 'matricula' => 'gestor2'],
            'recorte' => 'bairros',
            'turno' => 'Diurno',
            'fiscais' => [
                ['matricula' => 'F-2041', 'nome' => 'Adriana Melo Torres'],
                ['matricula' => 'F-2088', 'nome' => 'Cláudio Ferreira Lima'],
                ['matricula' => 'F-2131', 'nome' => 'Rita de Cássia Andrade'],
                ['matricula' => 'F-2190', 'nome' => 'Wagner Souza Pinto'],
            ],
            'bairros' => [
                'Alto das Pombas', 'Barbalho', 'Barra', 'Barris', 'Calabar', 'Canela',
                'Centro Histórico', 'Chame-Chame', 'Comércio', 'Dois de Julho',
                'Engenho Velho da Federação', 'Federação', 'Garcia', 'Graça', 'Macaúbas',
                'Mares', 'Nazaré', 'Ondina', 'Rio Vermelho', 'Santo Agostinho', 'Saúde',
                'Tororó', 'Vasco da Gama', 'Vitória',
            ],
        ],

        [
            'id' => 2,
            'nome' => 'Área 2',
            'regiao' => 'Itapagipe',
            'equipe' => 'A1',
            'encarregado' => 'Marco Gonçalves',
            'chefe_de_setor' => ['nome' => 'Djalma Sousa Vieira', 'matricula' => null],
            'recorte' => 'bairros',
            'turno' => 'Diurno',
            'fiscais' => [
                ['matricula' => 'F-2205', 'nome' => 'Benedito Alves Rocha'],
                ['matricula' => 'F-2247', 'nome' => 'Jussara Nunes Barreto'],
                ['matricula' => 'F-2263', 'nome' => 'Everton Matos da Silva'],
            ],
            'bairros' => [
                'Alto da Terezinha', 'Alto do Cabrito', 'Boa Viagem', 'Bonfim', 'Calçada',
                'Caminho de Areia', 'Colinas de Periperi', 'Coutos', 'Fazenda Coutos',
                'Ilha Amarela', 'Itacaranha', 'Lobato', 'Mangueira', 'Massaranduba',
                'Mirantes', 'Monte Serrat', 'Paripe', 'Periperi', 'Plataforma',
                'Praia Grande', 'Ribeira', 'Roma', 'Santa Luzia', 'São João do Cabrito',
                'São Tomé', 'Uruguai', 'Vila Rui Barbosa', 'Vista Alegre',
            ],
        ],

        [
            'id' => 3,
            'nome' => 'Área 3',
            'regiao' => 'Brotas',
            'equipe' => 'A2',
            'encarregado' => 'Nonato Silva',
            'chefe_de_setor' => ['nome' => 'Verônica Lins Barreto', 'matricula' => 'gestor3'],
            'recorte' => 'bairros',
            'turno' => 'Diurno',
            'fiscais' => [
                ['matricula' => 'F-2301', 'nome' => 'Solange Ribeiro Prado'],
                ['matricula' => 'F-2318', 'nome' => 'Márcio Aurélio Campos'],
                ['matricula' => 'F-2344', 'nome' => 'Neide Carvalho Dias'],
                ['matricula' => 'F-2377', 'nome' => 'Ubiratan Gomes Neves'],
                ['matricula' => 'F-2390', 'nome' => 'Fabiana Leal Menezes'],
            ],
            'bairros' => [
                'Acupe', 'Amaralina', 'Arraial do Retiro', 'Barreiras', 'Boa Vista de Brotas',
                'Cabula', 'Caminho das Árvores', 'Candeal', 'Cosme de Farias', 'Daniel Lisboa',
                'Engenho Velho de Brotas', 'Engomadeira', 'Horto Florestal', 'Itaigara',
                'Luiz Anselmo', 'Matatu', 'Nordeste', 'Pernambués', 'Pituba', 'Resgate',
                'Saboeiro', 'Santa Cruz', 'São Gonçalo', 'Saramandaia', 'Vale das Pedrinhas',
                'Vila Laura',
            ],
        ],

        [
            'id' => 4,
            'nome' => 'Área 4',
            'regiao' => 'Liberdade',
            'equipe' => 'B2',
            'encarregado' => 'Andréa Rocha',
            'chefe_de_setor' => ['nome' => 'Ivanildo Costa Pinheiro', 'matricula' => null],
            'recorte' => 'bairros',
            'turno' => 'Diurno',
            'fiscais' => [
                ['matricula' => 'F-2402', 'nome' => 'Gilberto Passos Cerqueira'],
                ['matricula' => 'F-2436', 'nome' => 'Vera Lúcia Amorim'],
                ['matricula' => 'F-2461', 'nome' => 'Josenildo Braga Vieira'],
            ],
            'bairros' => [
                'Alto do Peru', 'Baixa de Quintas', 'Boa Vista de São Caetano', 'Bom Juá',
                'Caixa d\'Água', 'Calabatão', 'Campinas de Pirajá', 'Capelinha', 'Cidade Nova',
                'Curuzu', 'Fazenda Grande do Retiro', 'IAPI', 'Lapinha', 'Largo do Tanque',
                'Marechal Rondon', 'Palestina', 'Pau Miúdo', 'Pero Vaz', 'Pirajá', 'Retiro',
                'San Martin', 'Santa Mônica', 'São Caetano', 'Valéria',
            ],
        ],

        [
            'id' => 5,
            'nome' => 'Área 5',
            'regiao' => 'Boca do Rio',
            'equipe' => 'C1',
            'encarregado' => 'César Amaral',
            'chefe_de_setor' => ['nome' => 'Lourdes Figueiredo Sales', 'matricula' => 'gestor1'],
            'recorte' => 'bairros',
            'turno' => 'Diurno',
            'fiscais' => [
                ['matricula' => 'F-2504', 'nome' => 'Aline Barbosa Fontes'],
                ['matricula' => 'F-2529', 'nome' => 'Renato Queiroz Bastos'],
                ['matricula' => 'F-2558', 'nome' => 'Iracema Duarte Lopes'],
                ['matricula' => 'F-2571', 'nome' => 'Tiago Marinho Cardoso'],
            ],
            'bairros' => [
                'Aeroporto', 'Alto do Coqueirinho', 'Areia Branca', 'Bairro da Paz',
                'Cassange', 'Costa Azul', 'Imbuí', 'Itapuã', 'Itinga', 'Jardim Armação',
                'Jardim das Margaridas', 'Mussurunga', 'Patamares', 'Piatã', 'Pituaçu',
                'Praia do Flamengo', 'São Cristóvão', 'Stella Maris', 'Stiep', 'Vale dos Rios',
            ],
        ],

        [
            'id' => 6,
            'nome' => 'Área 6',
            'regiao' => 'Pau da Lima',
            'equipe' => 'B1',
            'encarregado' => 'José Antonio',
            'chefe_de_setor' => ['nome' => 'Otacílio Ramos Cunha', 'matricula' => null],
            'recorte' => 'bairros',
            'turno' => 'Diurno',
            'fiscais' => [
                ['matricula' => 'F-2603', 'nome' => 'Domingos Sávio Peixoto'],
                ['matricula' => 'F-2640', 'nome' => 'Márcia Regina Tavares'],
                ['matricula' => 'F-2677', 'nome' => 'Anderson Luz Sampaio'],
                ['matricula' => 'F-2695', 'nome' => 'Cristiane Moura Freitas'],
                ['matricula' => 'F-2712', 'nome' => 'Hélio Batista Nogueira'],
                ['matricula' => 'F-2748', 'nome' => 'Simone Rebouças Aguiar'],
            ],
            'bairros' => [
                'Águas Claras', 'Arenoso', 'Boca da Mata', 'CAB', 'Cabula VI',
                'Cajazeiras II a XI', 'Canabrava', 'Castelo Branco', 'Dom Avelar', 'Doron',
                'Fazenda Grande I a IV', 'Granjas Rurais', 'Jaguaripe I', 'Jardim Cajazeiras',
                'Jardim Nova Esperança', 'Mata Escura', 'Narandiba', 'Nova Brasília',
                'Nova Esperança', 'Porto Seco Pirajá', 'Santo Inácio', 'São Marcos',
                'São Rafael', 'Sete de Abril', 'Sussuarana', 'Tancredo Neves', 'Trobogy',
                'Vale dos Lagos', 'Vila Canária',
                // Os três COMPARTILHADOS com a Área 5 — o vínculo bairro↔equipe
                // não é 1:1, e é este o caso que a tela precisa mostrar.
                'Jardim das Margaridas', 'Mussurunga', 'Patamares',
            ],
        ],

        [
            'id' => 7,
            'nome' => 'Itinerante',
            'regiao' => 'Avenida Sete',
            'equipe' => 'I1',
            'encarregado' => 'Roberto Moraes',
            'chefe_de_setor' => ['nome' => 'Bruna Cavalcanti Reis', 'matricula' => null],
            // Corredor, não bairro: a equipe percorre eixos de grande circulação.
            'recorte' => 'corredores',
            'turno' => 'Diurno',
            'fiscais' => [
                ['matricula' => 'F-2801', 'nome' => 'Paulo Sérgio Macedo'],
                ['matricula' => 'F-2833', 'nome' => 'Luciana Prado Bittencourt'],
                ['matricula' => 'F-2864', 'nome' => 'Edmilson Rocha Teles'],
            ],
            'bairros' => [
                'Avenida Sete de Setembro', 'Comércio', 'Avenida Joana Angélica',
            ],
        ],

        [
            'id' => 8,
            'nome' => 'Noturna',
            'regiao' => 'Toda Salvador',
            'equipe' => 'N1',
            'encarregado' => 'Alcione Brandão',
            'chefe_de_setor' => ['nome' => 'Aristides Moreno Fagundes', 'matricula' => null],
            // Recorte por TURNO: a cobertura é a cidade inteira.
            'recorte' => 'cidade',
            'turno' => 'Noturno',
            'fiscais' => [
                ['matricula' => 'F-2902', 'nome' => 'Antônio Carlos Belém'],
                ['matricula' => 'F-2947', 'nome' => 'Roseane Silveira Coelho'],
                ['matricula' => 'F-2966', 'nome' => 'Jonas Almeida Pires'],
                ['matricula' => 'F-2988', 'nome' => 'Tatiane Correia Bispo'],
            ],
            // Vazio de propósito: a cobertura é "todos os bairros", e listá-los
            // aqui daria dois donos à mesma lista (a de cada área diurna).
            'bairros' => [],
        ],

    ],

];

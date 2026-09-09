<?php

/*
|------------------------------------------------------------------------------
| Catálogo das LISTAGENS da Retaguarda — as colunas da grade e as do arquivo
|------------------------------------------------------------------------------
|
| A régua está escrita em `docs/padroes/listagem-clean.md`. Este arquivo é a
| aplicação dela: para cada listagem do sistema, QUAIS colunas a tela mostra e
| QUAIS colunas a exportação leva.
|
| ── Por que as duas listas moram no MESMO lugar ─────────────────────────────
|
| Porque a ordem do dono (09/09/2026) foi enxugar a TELA, e o risco embutido
| nessa ordem é a limpeza escorregar para o arquivo sem ninguém notar: quem
| apaga uma coluna da grade apaga a linha vizinha do `BotaoExportar` no mesmo
| impulso, e o XLSX que a chefia lê para decidir perde o dado em silêncio.
| Declaradas lado a lado, e conferidas por `ListagemCleanTest`, a grade só pode
| encolher enquanto o arquivo continua inteiro — `detalhe` é exatamente a lista
| do que saiu da tela e TEM de continuar no arquivo.
|
| ── O que cada chave significa ──────────────────────────────────────────────
|
|  · `tela`      — o arquivo que desenha a listagem. Serve de endereço para
|                  quem for alterar, e o teste confere que a tela ainda consome
|                  este catálogo (grade montada à mão volta a poluir).
|  · `grade`     — as colunas visíveis, NA ORDEM. Máximo de 5 (a régua), e
|                  nenhuma pode ser campo de texto livre (lista `texto_livre`).
|                  `quando` marca a coluna CONDICIONAL: ela só entra quando o
|                  contexto daquele acesso a pede — hoje só `varias-areas`, o
|                  caso do Coordenador que varre cinco áreas e do Chefe de Setor
|                  que só tem a sua (para ele a coluna seria uma constante).
|  · `detalhe`   — o que DESCEU da grade para o detalhe do registro. Não é
|                  documentação: é a lista que o teste exige encontrar na
|                  exportação.
|  · `exportacao`— as colunas do PDF/XLSX/DOCX. Sempre um superconjunto de
|                  `grade` + `detalhe`.
|
| ⚠️ `chave` é a MESMA nos três lugares de propósito. Grade e arquivo com nomes
| diferentes para o mesmo dado ("recomendacao" na tela, "recomendacoes" no
| arquivo) fariam o teste passar sem cruzar nada.
*/

return [

    /*
    | Os campos de TEXTO LIVRE do sistema — o que o usuário escreve em frase
    | inteira. Nenhum deles pode ser coluna de grade: é o que quebra a linha em
    | duas, três e cinco alturas diferentes, e foi o diagnóstico do print que
    | gerou esta régua. Na grade cabe, no máximo, um selo com a CATEGORIA.
    |
    | A lista é global e não por listagem de propósito: se cada autor declarasse
    | os seus, bastaria esquecer de declarar para o campo passar.
    */
    'texto_livre' => [
        'assunto',
        'relato',
        'descricao',
        'consideracoes',
        'justificativa',
        'observacao',
        'orientacao',
        'foco',
        'ponto_de_referencia',
        'quem_encontrado',
    ],

    'listagens' => [

        /*
        |----------------------------------------------------------------------
        | Fiscalização › Fiscalizações — aba "A decidir"
        |----------------------------------------------------------------------
        |
        | Quem varre: o Chefe de Setor, procurando o que voltou da rua e o que
        | a equipe está PEDINDO. Por isso a recomendação do fiscal é coluna, e
        | não linha do detalhe: quem lê trinta retornos não abre trinta fichas.
        |
        | `estado` NÃO é coluna aqui: a aba já filtra por "aguardando leitura",
        | então a coluna seria a mesma palavra repetida em toda linha — e a
        | marca laranja na ponta da linha já diz que aquilo espera alguém.
        | Equipe, fiscal e documento descem: a decisão é sobre o PONTO.
        */
        'fiscalizacoes.a-decidir' => [
            'tela' => 'resources/js/pages/Retaguarda/Fiscalizacao/Fiscalizacoes.tsx',
            'grade' => [
                ['chave' => 'concluida_em', 'titulo' => 'Concluída', 'largura' => 104, 'alinhar' => 'center'],
                ['chave' => 'ponto', 'titulo' => 'Ponto', 'largura' => 226],
                // A ÁREA só para quem vê mais de uma. Para o Chefe de Setor a
                // coluna repetiria a área dele em toda linha; para o
                // Coordenador, que responde por cinco, ela é o que faz a fila
                // ser varrível.
                ['chave' => 'area', 'titulo' => 'Área', 'largura' => 116, 'quando' => 'varias-areas'],
                ['chave' => 'desfecho', 'titulo' => 'Desfecho', 'largura' => 224],
                ['chave' => 'recomendacoes', 'titulo' => 'Recomendação do fiscal', 'largura' => 296],
            ],
            'detalhe' => ['protocolo', 'equipe', 'fiscal', 'documento', 'consideracoes', 'origem', 'estado'],
            'exportacao' => [
                ['chave' => 'protocolo', 'titulo' => 'Registro'],
                ['chave' => 'concluida_em', 'titulo' => 'Concluída em', 'alinhar' => 'center'],
                ['chave' => 'area', 'titulo' => 'Área'],
                ['chave' => 'equipe', 'titulo' => 'Equipe'],
                ['chave' => 'fiscal', 'titulo' => 'Fiscal'],
                ['chave' => 'ponto', 'titulo' => 'Ponto'],
                ['chave' => 'desfecho', 'titulo' => 'Desfecho'],
                ['chave' => 'documento', 'titulo' => 'Documento'],
                ['chave' => 'recomendacoes', 'titulo' => 'Recomendação do fiscal'],
                ['chave' => 'consideracoes', 'titulo' => 'Considerações do fiscal'],
                ['chave' => 'origem', 'titulo' => 'Origem'],
                ['chave' => 'estado', 'titulo' => 'Estado'],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Fiscalização › Fiscalizações — aba "Acervo"
        |----------------------------------------------------------------------
        |
        | Quem varre: quem consulta o histórico de um ponto ou de uma pessoa.
        | A pergunta é "o que foi feito ali?", então o ALVO encontrado sobe para
        | a grade e o PRAZO fica — é o único sinal desta aba que ainda cobra
        | ação de alguém.
        |
        | A área desce: no acervo procura-se por endereço e por nome, e a barra
        | de busca entende "Área 5" quando a pergunta for essa.
        */
        'fiscalizacoes.acervo' => [
            'tela' => 'resources/js/pages/Retaguarda/Fiscalizacao/Fiscalizacoes.tsx',
            'grade' => [
                ['chave' => 'concluida_em', 'titulo' => 'Concluída', 'largura' => 104, 'alinhar' => 'center'],
                ['chave' => 'ponto', 'titulo' => 'Ponto', 'largura' => 250],
                ['chave' => 'alvo', 'titulo' => 'Quem foi encontrado', 'largura' => 200],
                ['chave' => 'desfecho', 'titulo' => 'Desfecho', 'largura' => 224],
                ['chave' => 'prazo', 'titulo' => 'Prazo de retorno', 'largura' => 196],
            ],
            'detalhe' => [
                'protocolo', 'area', 'equipe', 'fiscal', 'documento', 'provas',
                'recomendacoes', 'consideracoes', 'origem', 'estado',
            ],
            'exportacao' => [
                ['chave' => 'protocolo', 'titulo' => 'Registro'],
                ['chave' => 'concluida_em', 'titulo' => 'Concluída em', 'alinhar' => 'center'],
                ['chave' => 'area', 'titulo' => 'Área'],
                ['chave' => 'equipe', 'titulo' => 'Equipe'],
                ['chave' => 'fiscal', 'titulo' => 'Fiscal'],
                ['chave' => 'ponto', 'titulo' => 'Ponto'],
                ['chave' => 'alvo', 'titulo' => 'Quem foi encontrado'],
                ['chave' => 'desfecho', 'titulo' => 'Desfecho'],
                ['chave' => 'documento', 'titulo' => 'Documento'],
                ['chave' => 'prazo', 'titulo' => 'Prazo de retorno'],
                ['chave' => 'provas', 'titulo' => 'Provas'],
                ['chave' => 'recomendacoes', 'titulo' => 'Recomendação do fiscal'],
                ['chave' => 'consideracoes', 'titulo' => 'Considerações do fiscal'],
                ['chave' => 'origem', 'titulo' => 'Origem'],
                ['chave' => 'estado', 'titulo' => 'Estado'],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Denúncias › e-Salvador / Fala Salvador — aba "A triar"
        |----------------------------------------------------------------------
        |
        | Quem varre: o Coordenador, decidindo a ÁREA a partir do BAIRRO. Só
        | isso: bairro é o dado que decide, área é onde ele confirma (a célula é
        | um seletor, não texto), e o prazo é o que ordena a urgência.
        |
        | Requerente e assunto descem — o assunto é texto livre e era ele que
        | esticava a linha. Situação não entra: nesta aba é sempre "Recebida".
        */
        'denuncias.triagem' => [
            'tela' => 'resources/js/components/retaguarda/painel-de-denuncias.tsx',
            'grade' => [
                ['chave' => 'protocolo', 'titulo' => 'Protocolo', 'largura' => 132],
                ['chave' => 'recebida', 'titulo' => 'Recebida', 'largura' => 132, 'alinhar' => 'center'],
                ['chave' => 'bairro', 'titulo' => 'Bairro', 'largura' => 190],
                ['chave' => 'area', 'titulo' => 'Área (sugerida)', 'largura' => 250],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'largura' => 118, 'alinhar' => 'center'],
            ],
            'detalhe' => ['protocolo_origem', 'requerente', 'assunto', 'destino', 'situacao', 'desfecho'],
            'exportacao' => [
                ['chave' => 'protocolo', 'titulo' => 'Protocolo'],
                ['chave' => 'protocolo_origem', 'titulo' => 'Nº na origem'],
                ['chave' => 'recebida', 'titulo' => 'Recebida', 'alinhar' => 'center'],
                ['chave' => 'requerente', 'titulo' => 'Requerente'],
                ['chave' => 'assunto', 'titulo' => 'Assunto'],
                ['chave' => 'bairro', 'titulo' => 'Bairro'],
                ['chave' => 'area', 'titulo' => 'Área'],
                ['chave' => 'destino', 'titulo' => 'Destino'],
                ['chave' => 'situacao', 'titulo' => 'Situação'],
                ['chave' => 'desfecho', 'titulo' => 'Desfecho'],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'alinhar' => 'center'],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Denúncias — aba "A direcionar"
        |----------------------------------------------------------------------
        |
        | Quem varre: o Chefe de Setor, escolhendo equipe ou operação. A área já
        | está definida e ele pode responder por mais de uma, então ela fica —
        | curta, como texto. Situação, de novo, é constante na aba.
        */
        'denuncias.direcionamento' => [
            'tela' => 'resources/js/components/retaguarda/painel-de-denuncias.tsx',
            'grade' => [
                ['chave' => 'protocolo', 'titulo' => 'Protocolo', 'largura' => 132],
                ['chave' => 'recebida', 'titulo' => 'Recebida', 'largura' => 132, 'alinhar' => 'center'],
                ['chave' => 'bairro', 'titulo' => 'Bairro', 'largura' => 190],
                ['chave' => 'area', 'titulo' => 'Área', 'largura' => 150],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'largura' => 118, 'alinhar' => 'center'],
            ],
            'detalhe' => ['protocolo_origem', 'requerente', 'assunto', 'destino', 'situacao', 'desfecho'],
            'exportacao' => [
                ['chave' => 'protocolo', 'titulo' => 'Protocolo'],
                ['chave' => 'protocolo_origem', 'titulo' => 'Nº na origem'],
                ['chave' => 'recebida', 'titulo' => 'Recebida', 'alinhar' => 'center'],
                ['chave' => 'requerente', 'titulo' => 'Requerente'],
                ['chave' => 'assunto', 'titulo' => 'Assunto'],
                ['chave' => 'bairro', 'titulo' => 'Bairro'],
                ['chave' => 'area', 'titulo' => 'Área'],
                ['chave' => 'destino', 'titulo' => 'Destino'],
                ['chave' => 'situacao', 'titulo' => 'Situação'],
                ['chave' => 'desfecho', 'titulo' => 'Desfecho'],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'alinhar' => 'center'],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Denúncias — aba "Todas"
        |----------------------------------------------------------------------
        |
        | Quem varre: quem acompanha o canal e quer saber em que ESTADO cada
        | denúncia está. Aqui a situação varia, então ela é a coluna do selo; o
        | destino (equipe ou operação) desce, porque a pergunta desta aba é "em
        | que pé está", não "com quem está".
        */
        'denuncias.todas' => [
            'tela' => 'resources/js/components/retaguarda/painel-de-denuncias.tsx',
            'grade' => [
                ['chave' => 'protocolo', 'titulo' => 'Protocolo', 'largura' => 132],
                ['chave' => 'recebida', 'titulo' => 'Recebida', 'largura' => 132, 'alinhar' => 'center'],
                ['chave' => 'bairro', 'titulo' => 'Bairro', 'largura' => 190],
                ['chave' => 'situacao', 'titulo' => 'Situação', 'largura' => 200],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'largura' => 118, 'alinhar' => 'center'],
            ],
            'detalhe' => ['protocolo_origem', 'requerente', 'assunto', 'area', 'destino', 'desfecho'],
            'exportacao' => [
                ['chave' => 'protocolo', 'titulo' => 'Protocolo'],
                ['chave' => 'protocolo_origem', 'titulo' => 'Nº na origem'],
                ['chave' => 'recebida', 'titulo' => 'Recebida', 'alinhar' => 'center'],
                ['chave' => 'requerente', 'titulo' => 'Requerente'],
                ['chave' => 'assunto', 'titulo' => 'Assunto'],
                ['chave' => 'bairro', 'titulo' => 'Bairro'],
                ['chave' => 'area', 'titulo' => 'Área'],
                ['chave' => 'destino', 'titulo' => 'Destino'],
                ['chave' => 'situacao', 'titulo' => 'Situação'],
                ['chave' => 'desfecho', 'titulo' => 'Desfecho'],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'alinhar' => 'center'],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Fiscalização › Cadastro de Operação
        |----------------------------------------------------------------------
        |
        | Quem varre: quem precisa saber que operação existe e se ela está
        | aberta — em regra para anexar trabalho a ela. Nome, área, período,
        | equipes e situação respondem isso.
        |
        | O FOCO desce: é a frase que o print mostrava embaixo do nome, e é
        | texto livre. Região e bairros descem com ele: contam o alcance, que
        | interessa depois de escolher a operação, não durante a varredura.
        */
        'operacoes' => [
            'tela' => 'resources/js/pages/Retaguarda/Fiscalizacao/CadastroDeOperacao.tsx',
            'grade' => [
                ['chave' => 'nome', 'titulo' => 'Operação', 'largura' => 280],
                ['chave' => 'area', 'titulo' => 'Área', 'largura' => 130],
                ['chave' => 'periodo', 'titulo' => 'Período', 'largura' => 230],
                ['chave' => 'equipes', 'titulo' => 'Equipes', 'largura' => 150],
                ['chave' => 'situacao', 'titulo' => 'Situação', 'largura' => 150],
            ],
            'detalhe' => ['regiao', 'bairros', 'inicio', 'fim', 'foco', 'observacao'],
            'exportacao' => [
                ['chave' => 'nome', 'titulo' => 'Operação'],
                ['chave' => 'area', 'titulo' => 'Área'],
                ['chave' => 'regiao', 'titulo' => 'Região'],
                ['chave' => 'equipes', 'titulo' => 'Equipes'],
                ['chave' => 'periodo', 'titulo' => 'Período'],
                ['chave' => 'inicio', 'titulo' => 'Início', 'alinhar' => 'center'],
                ['chave' => 'fim', 'titulo' => 'Fim', 'alinhar' => 'center'],
                ['chave' => 'situacao', 'titulo' => 'Situação'],
                ['chave' => 'bairros', 'titulo' => 'Bairros alcançados'],
                ['chave' => 'foco', 'titulo' => 'Foco'],
                ['chave' => 'observacao', 'titulo' => 'Observação'],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Fiscalização › Ambulantes — aba "Localizar"
        |----------------------------------------------------------------------
        |
        | Quem varre: quem procura uma PESSOA. A identidade prática de campo é
        | foto + apelido (lei do domínio), então o apelido ganha coluna própria
        | em vez de virar sub-linha embaixo do nome — que era exatamente o
        | empilhamento que o dono mandou tirar.
        |
        | Documento e validade da permissão descem: são chaves de BUSCA (a barra
        | casa documento, e "permissão vencida" é faceta), não colunas de
        | varredura. A coluna "Permissão" continua porque sem ela não se sabe
        | qual dos dois públicos é a linha.
        */
        'ambulantes' => [
            'tela' => 'resources/js/pages/Retaguarda/Fiscalizacao/CadastroDeAmbulante.tsx',
            'grade' => [
                ['chave' => 'nome', 'titulo' => 'Ambulante', 'largura' => 280],
                ['chave' => 'apelido', 'titulo' => 'Apelido', 'largura' => 170],
                ['chave' => 'atividade', 'titulo' => 'Atividade', 'largura' => 200],
                ['chave' => 'permissionario', 'titulo' => 'Permissão', 'largura' => 150],
                ['chave' => 'situacao', 'titulo' => 'Situação', 'largura' => 180],
            ],
            'detalhe' => ['codigo', 'documento', 'numero_permissao', 'validade_permissao'],
            'exportacao' => [
                ['chave' => 'codigo', 'titulo' => 'Código'],
                ['chave' => 'nome', 'titulo' => 'Nome'],
                ['chave' => 'apelido', 'titulo' => 'Apelido'],
                ['chave' => 'documento', 'titulo' => 'Documento'],
                ['chave' => 'atividade', 'titulo' => 'Atividade'],
                ['chave' => 'situacao', 'titulo' => 'Situação'],
                ['chave' => 'permissionario', 'titulo' => 'Permissionário', 'alinhar' => 'center'],
                ['chave' => 'numero_permissao', 'titulo' => 'Nº da permissão'],
                ['chave' => 'validade_permissao', 'titulo' => 'Validade', 'alinhar' => 'center'],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Fiscalização › Caixa de Entrada
        |----------------------------------------------------------------------
        |
        | Quem varre: o Coordenador vendo o que chegou em papel e o que está
        | estourando prazo. Protocolo, data de recebimento, bairro (o dado que
        | define a equipe), situação e prazo.
        |
        | ⚠️ Aqui a mudança é SÓ de apresentação, por ordem anterior do dono: o
        | fluxo, os dados de `config/prototipo_caixa_entrada.php`, as ações e as
        | abas ficam como estão. Origem, requerente, assunto e equipe descem
        | para o detalhe, que já os mostrava por inteiro.
        */
        'caixa-de-entrada' => [
            'tela' => 'resources/js/pages/Retaguarda/Fiscalizacao/CaixaDeEntrada.tsx',
            'grade' => [
                ['chave' => 'protocolo', 'titulo' => 'Protocolo', 'largura' => 132],
                ['chave' => 'recebida_em', 'titulo' => 'Recebida', 'largura' => 120, 'alinhar' => 'center'],
                ['chave' => 'bairro', 'titulo' => 'Bairro', 'largura' => 190],
                ['chave' => 'situacao', 'titulo' => 'Situação', 'largura' => 200],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'largura' => 118, 'alinhar' => 'center'],
            ],
            'detalhe' => ['origem', 'documento_origem', 'requerente', 'assunto', 'equipe'],
            'exportacao' => [
                ['chave' => 'protocolo', 'titulo' => 'Protocolo'],
                ['chave' => 'origem', 'titulo' => 'Origem'],
                ['chave' => 'documento_origem', 'titulo' => 'Documento'],
                ['chave' => 'recebida_em', 'titulo' => 'Recebida em', 'alinhar' => 'center'],
                ['chave' => 'requerente', 'titulo' => 'Requerente'],
                ['chave' => 'assunto', 'titulo' => 'Assunto'],
                ['chave' => 'bairro', 'titulo' => 'Bairro'],
                ['chave' => 'equipe', 'titulo' => 'Equipe'],
                ['chave' => 'situacao', 'titulo' => 'Situação'],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'alinhar' => 'center'],
            ],
        ],
    ],
];

<?php

/*
|--------------------------------------------------------------------------
| Geografia de Salvador — o que o mapa precisa saber sobre a cidade
|--------------------------------------------------------------------------
|
| As coordenadas dos bairros são FATO sobre a cidade, não dado de demonstração:
| Centro Histórico, Calçada, Itapuã e Cajazeiras ficam onde ficam. Elas viviam
| escondidas num arquivo de protótipo, ao lado de gente inventada — e era a única
| coisa daquele arquivo que não era invenção.
|
| Daqui elas entram na coluna `area_bairros.latitude/longitude` pelo seeder da
| estrutura. Bairro cadastrado depois, pela tela, nasce SEM coordenada: aparece na
| lista e não aparece no mapa, o que é honesto — melhor do que plantá-lo no centro
| da cidade e fazer a chefia acreditar que há trabalho ali.
|
| O `peso` é outra coisa, e vale dizer: ele é a CONCENTRAÇÃO de comércio de rua
| que a SEMOP relata em reunião (Centro, Calçada, Barra e os corredores puxam a
| fila). Serve só para a SEMEADURA de demonstração distribuir os ambulantes de
| forma plausível — nenhuma tela lê o peso, e nenhum número do sistema sai dele.
| Quando houver cadastro de verdade, o relevo do mapa é o do próprio dado.
|
*/

return [

    /* O centro do mapa da cidade: a Praça Municipal, no Centro Histórico. */
    'centro' => ['lat' => -12.9730, 'lng' => -38.5140],

    /*
     * Bairro => coordenada aproximada do centroide, e o peso de concentração.
     * Os nomes casam com os blocos da estrutura — grafia diferente sairia sem
     * área no mapa.
     */
    'bairros' => [

        // Área 1 · Centro · Equipe C2
        'Centro Histórico' => ['lat' => -12.9718, 'lng' => -38.5089, 'peso' => 10],
        'Comércio' => ['lat' => -12.9740, 'lng' => -38.5137, 'peso' => 8],
        'Barra' => ['lat' => -13.0106, 'lng' => -38.5325, 'peso' => 7],
        'Barris' => ['lat' => -12.9820, 'lng' => -38.5140, 'peso' => 4],
        'Rio Vermelho' => ['lat' => -13.0104, 'lng' => -38.4906, 'peso' => 5],
        'Nazaré' => ['lat' => -12.9760, 'lng' => -38.5060, 'peso' => 3],
        'Ondina' => ['lat' => -13.0086, 'lng' => -38.5065, 'peso' => 3],
        'Federação' => ['lat' => -13.0000, 'lng' => -38.5100, 'peso' => 3],
        'Graça' => ['lat' => -13.0000, 'lng' => -38.5220, 'peso' => 2],
        'Vitória' => ['lat' => -12.9930, 'lng' => -38.5210, 'peso' => 2],

        // Área 2 · Itapagipe · Equipe A1
        'Calçada' => ['lat' => -12.9350, 'lng' => -38.5040, 'peso' => 6],
        'Bonfim' => ['lat' => -12.9200, 'lng' => -38.5070, 'peso' => 5],
        'Ribeira' => ['lat' => -12.9210, 'lng' => -38.4980, 'peso' => 4],
        'Uruguai' => ['lat' => -12.9280, 'lng' => -38.5010, 'peso' => 4],
        'Periperi' => ['lat' => -12.8600, 'lng' => -38.4830, 'peso' => 4],
        'Paripe' => ['lat' => -12.8330, 'lng' => -38.4880, 'peso' => 3],

        // Área 3 · Brotas · Equipe A2
        'Pituba' => ['lat' => -12.9930, 'lng' => -38.4560, 'peso' => 6],
        'Amaralina' => ['lat' => -13.0030, 'lng' => -38.4680, 'peso' => 5],
        'Cabula' => ['lat' => -12.9560, 'lng' => -38.4530, 'peso' => 4],
        'Pernambués' => ['lat' => -12.9670, 'lng' => -38.4600, 'peso' => 4],
        'Engenho Velho de Brotas' => ['lat' => -12.9800, 'lng' => -38.4930, 'peso' => 3],
        'Itaigara' => ['lat' => -12.9880, 'lng' => -38.4640, 'peso' => 2],

        // Área 4 · Liberdade · Equipe B2
        'São Caetano' => ['lat' => -12.9330, 'lng' => -38.4750, 'peso' => 6],
        'Curuzu' => ['lat' => -12.9450, 'lng' => -38.4900, 'peso' => 5],
        'IAPI' => ['lat' => -12.9490, 'lng' => -38.4830, 'peso' => 4],
        'Cidade Nova' => ['lat' => -12.9420, 'lng' => -38.4670, 'peso' => 3],
        'Pirajá' => ['lat' => -12.9130, 'lng' => -38.4560, 'peso' => 2],

        // Área 5 · Boca do Rio · Equipe C1
        'Itapuã' => ['lat' => -12.9469, 'lng' => -38.3628, 'peso' => 5],
        'Costa Azul' => ['lat' => -12.9880, 'lng' => -38.4400, 'peso' => 4],
        'Mussurunga' => ['lat' => -12.9300, 'lng' => -38.3820, 'peso' => 4],
        'Stiep' => ['lat' => -12.9830, 'lng' => -38.4340, 'peso' => 3],
        'Imbuí' => ['lat' => -12.9750, 'lng' => -38.4230, 'peso' => 3],
        'Jardim Armação' => ['lat' => -12.9880, 'lng' => -38.4270, 'peso' => 3],
        'Patamares' => ['lat' => -12.9660, 'lng' => -38.3970, 'peso' => 2],
        'Stella Maris' => ['lat' => -12.9370, 'lng' => -38.3480, 'peso' => 2],

        // Área 6 · Pau da Lima · Equipe B1
        'Tancredo Neves' => ['lat' => -12.9450, 'lng' => -38.4390, 'peso' => 5],
        'Sussuarana' => ['lat' => -12.9420, 'lng' => -38.4230, 'peso' => 4],
        'Cajazeiras II a XI' => ['lat' => -12.8900, 'lng' => -38.4130, 'peso' => 4],
        'Castelo Branco' => ['lat' => -12.9080, 'lng' => -38.4230, 'peso' => 3],
        'Sete de Abril' => ['lat' => -12.9130, 'lng' => -38.4310, 'peso' => 3],
        'Arenoso' => ['lat' => -12.9530, 'lng' => -38.4290, 'peso' => 3],
        'Águas Claras' => ['lat' => -12.8900, 'lng' => -38.4290, 'peso' => 2],

        // Itinerante · Equipe I1 — corredor, não bairro fechado.
        'Avenida Sete de Setembro' => ['lat' => -12.9790, 'lng' => -38.5140, 'peso' => 7],
        'Avenida Joana Angélica' => ['lat' => -12.9800, 'lng' => -38.5060, 'peso' => 4],
    ],
];

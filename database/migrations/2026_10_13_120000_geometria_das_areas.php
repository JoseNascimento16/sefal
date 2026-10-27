<?php

use App\Models\Area;
use Illuminate\Database\Migrations\Migration;

/**
 * A GEOMETRIA das áreas passa a morar no banco (dono, 13/10/2026: "a geometria
 * das áreas"). Até aqui o contorno de cada bloco e os corredores da Itinerante
 * existiam só desenhados à mão dentro do aplicativo do fiscal; a coluna
 * `areas.poligono` estava vazia.
 *
 * Formato: GeoJSON `FeatureCollection` — um `Polygon` para o contorno do bloco
 * de bairros, um `LineString` por corredor (com `properties.nome`). A Noturna
 * cobre a cidade inteira e não tem desenho. As coordenadas são as do protótipo:
 * APROXIMADAS, para o fiscal saber onde termina o trabalho dele, não limite
 * oficial de bairro — a tela de Áreas permite redesenhar.
 *
 * Só preenche área sem desenho: não sobrescreve o que alguém já redesenhou.
 */
return new class extends Migration
{
    private const GEOMETRIAS = <<<'JSON'
{
    "Área 1": {
        "type": "FeatureCollection",
        "features": [
            {
                "type": "Feature",
                "properties": {},
                "geometry": {
                    "type": "Polygon",
                    "coordinates": [
                        [
                            [
                                -38.5342,
                                -13.0108
                            ],
                            [
                                -38.5128,
                                -13.0062
                            ],
                            [
                                -38.4902,
                                -13.0088
                            ],
                            [
                                -38.4928,
                                -12.9996
                            ],
                            [
                                -38.5038,
                                -12.9878
                            ],
                            [
                                -38.5082,
                                -12.9798
                            ],
                            [
                                -38.5058,
                                -12.9718
                            ],
                            [
                                -38.5132,
                                -12.9698
                            ],
                            [
                                -38.5222,
                                -12.9792
                            ],
                            [
                                -38.5232,
                                -12.9932
                            ],
                            [
                                -38.5288,
                                -13.0042
                            ],
                            [
                                -38.5342,
                                -13.0108
                            ]
                        ]
                    ]
                }
            }
        ]
    },
    "Área 2": {
        "type": "FeatureCollection",
        "features": [
            {
                "type": "Feature",
                "properties": {},
                "geometry": {
                    "type": "Polygon",
                    "coordinates": [
                        [
                            [
                                -38.5112,
                                -12.9432
                            ],
                            [
                                -38.4962,
                                -12.9252
                            ],
                            [
                                -38.4812,
                                -12.9102
                            ],
                            [
                                -38.4722,
                                -12.8952
                            ],
                            [
                                -38.4602,
                                -12.8722
                            ],
                            [
                                -38.4532,
                                -12.8462
                            ],
                            [
                                -38.4652,
                                -12.8422
                            ],
                            [
                                -38.4742,
                                -12.8722
                            ],
                            [
                                -38.4882,
                                -12.8992
                            ],
                            [
                                -38.5072,
                                -12.9212
                            ],
                            [
                                -38.5162,
                                -12.9352
                            ],
                            [
                                -38.5112,
                                -12.9432
                            ]
                        ]
                    ]
                }
            }
        ]
    },
    "Área 3": {
        "type": "FeatureCollection",
        "features": [
            {
                "type": "Feature",
                "properties": {},
                "geometry": {
                    "type": "Polygon",
                    "coordinates": [
                        [
                            [
                                -38.4872,
                                -13.0002
                            ],
                            [
                                -38.4602,
                                -12.9928
                            ],
                            [
                                -38.4598,
                                -12.9802
                            ],
                            [
                                -38.4452,
                                -12.9648
                            ],
                            [
                                -38.4408,
                                -12.9562
                            ],
                            [
                                -38.4702,
                                -12.9598
                            ],
                            [
                                -38.4808,
                                -12.9712
                            ],
                            [
                                -38.4952,
                                -12.9802
                            ],
                            [
                                -38.4998,
                                -12.9948
                            ],
                            [
                                -38.4872,
                                -13.0002
                            ]
                        ]
                    ]
                }
            }
        ]
    },
    "Área 4": {
        "type": "FeatureCollection",
        "features": [
            {
                "type": "Feature",
                "properties": {},
                "geometry": {
                    "type": "Polygon",
                    "coordinates": [
                        [
                            [
                                -38.5102,
                                -12.9432
                            ],
                            [
                                -38.4962,
                                -12.9332
                            ],
                            [
                                -38.4722,
                                -12.9252
                            ],
                            [
                                -38.4552,
                                -12.9152
                            ],
                            [
                                -38.4402,
                                -12.9302
                            ],
                            [
                                -38.4562,
                                -12.9482
                            ],
                            [
                                -38.4782,
                                -12.9602
                            ],
                            [
                                -38.5002,
                                -12.9562
                            ],
                            [
                                -38.5102,
                                -12.9432
                            ]
                        ]
                    ]
                }
            }
        ]
    },
    "Área 5": {
        "type": "FeatureCollection",
        "features": [
            {
                "type": "Feature",
                "properties": {},
                "geometry": {
                    "type": "Polygon",
                    "coordinates": [
                        [
                            [
                                -38.4532,
                                -12.9917
                            ],
                            [
                                -38.4312,
                                -12.9827
                            ],
                            [
                                -38.4202,
                                -12.9782
                            ],
                            [
                                -38.4052,
                                -12.9721
                            ],
                            [
                                -38.3872,
                                -12.9648
                            ],
                            [
                                -38.3702,
                                -12.958
                            ],
                            [
                                -38.3592,
                                -12.9536
                            ],
                            [
                                -38.3442,
                                -12.9474
                            ],
                            [
                                -38.3312,
                                -12.9421
                            ],
                            [
                                -38.3427,
                                -12.9139
                            ],
                            [
                                -38.3557,
                                -12.9192
                            ],
                            [
                                -38.3706,
                                -12.9253
                            ],
                            [
                                -38.3816,
                                -12.9297
                            ],
                            [
                                -38.3986,
                                -12.9366
                            ],
                            [
                                -38.4167,
                                -12.9439
                            ],
                            [
                                -38.4317,
                                -12.95
                            ],
                            [
                                -38.4427,
                                -12.9545
                            ],
                            [
                                -38.4646,
                                -12.9634
                            ],
                            [
                                -38.4532,
                                -12.9917
                            ]
                        ]
                    ]
                }
            }
        ]
    },
    "Área 6": {
        "type": "FeatureCollection",
        "features": [
            {
                "type": "Feature",
                "properties": {},
                "geometry": {
                    "type": "Polygon",
                    "coordinates": [
                        [
                            [
                                -38.4302,
                                -12.9302
                            ],
                            [
                                -38.4202,
                                -12.9152
                            ],
                            [
                                -38.4102,
                                -12.8952
                            ],
                            [
                                -38.4302,
                                -12.8852
                            ],
                            [
                                -38.4502,
                                -12.9002
                            ],
                            [
                                -38.4552,
                                -12.9252
                            ],
                            [
                                -38.4402,
                                -12.9452
                            ],
                            [
                                -38.4202,
                                -12.9552
                            ],
                            [
                                -38.4302,
                                -12.9302
                            ]
                        ]
                    ]
                }
            }
        ]
    },
    "Itinerante": {
        "type": "FeatureCollection",
        "features": [
            {
                "type": "Feature",
                "properties": {
                    "nome": "Avenida Sete de Setembro"
                },
                "geometry": {
                    "type": "LineString",
                    "coordinates": [
                        [
                            -38.5188,
                            -12.9862
                        ],
                        [
                            -38.5168,
                            -12.9822
                        ],
                        [
                            -38.5152,
                            -12.9788
                        ],
                        [
                            -38.5132,
                            -12.9748
                        ]
                    ]
                }
            },
            {
                "type": "Feature",
                "properties": {
                    "nome": "Comércio"
                },
                "geometry": {
                    "type": "LineString",
                    "coordinates": [
                        [
                            -38.5138,
                            -12.9738
                        ],
                        [
                            -38.5122,
                            -12.9698
                        ],
                        [
                            -38.5092,
                            -12.9648
                        ]
                    ]
                }
            },
            {
                "type": "Feature",
                "properties": {
                    "nome": "Avenida Joana Angélica"
                },
                "geometry": {
                    "type": "LineString",
                    "coordinates": [
                        [
                            -38.5088,
                            -12.9808
                        ],
                        [
                            -38.5092,
                            -12.9762
                        ],
                        [
                            -38.5102,
                            -12.9718
                        ]
                    ]
                }
            }
        ]
    }
}
JSON;

    public function up(): void
    {
        // Objetos, e não arrays: `properties: {}` tem de continuar objeto no GeoJSON.
        foreach (get_object_vars(json_decode(self::GEOMETRIAS)) as $nome => $geometria) {
            // Só `null`: no Oracle a coluna é CLOB (não se compara com '') e a
            // string vazia já é nula.
            Area::where('nome', $nome)
                ->whereNull('poligono')
                ->update(['poligono' => json_encode($geometria, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        // O desenho pode ter sido refeito na tela depois: não há o que desfazer com segurança.
    }
};

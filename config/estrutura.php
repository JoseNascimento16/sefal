<?php

/*
|--------------------------------------------------------------------------
| Estrutura de trabalho — os catálogos que a área e a equipe escolhem
|--------------------------------------------------------------------------
|
| A ESTRUTURA em si (áreas, bairros, equipes, fiscais) vive no banco desde a
| consolidação — quem a lê é `App\Support\Estrutura`. O que sobrou para cá são
| as listas de escolha do formulário, que são regra de negócio e não dado.
|
| Fonte única: a tela oferece estas opções e o servidor valida contra estas
| mesmas. Escritas nos dois lugares, um dia a tela ofereceria um turno que o
| servidor recusa — e o usuário levaria a culpa por um erro que não cometeu.
|
*/

return [

    /*
     * Os turnos em que uma equipe trabalha. "Diurno e noturno" não é a soma dos
     * outros dois: é a equipe que cobre o dia inteiro, e ela existe de verdade
     * (o Carnaval e o Réveillon são assim).
     */
    'turnos' => [
        'Diurno',
        'Noturno',
        'Diurno e noturno',
    ],

    /*
     * Como o território de uma área é recortado.
     *
     *   bairros     — a lista de bairros (o caso de hoje, e o que alimenta a
     *                 sugestão de área a partir do endereço da demanda);
     *   corredores  — eixos viários, e não polígonos fechados: a equipe
     *                 itinerante percorre avenidas, não um bloco;
     *   cidade      — sem recorte. É a equipe que atende a cidade inteira
     *                 (eventos, força-tarefa), e declarar isso é melhor do que
     *                 cadastrar 160 bairros para dizer a mesma coisa.
     */
    'recortes' => [
        'bairros',
        'corredores',
        'cidade',
    ],
];

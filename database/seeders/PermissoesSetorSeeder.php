<?php

namespace Database\Seeders;

use App\Models\PermissaoSetor;
use App\Services\PermissaoService;
use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Seeder;

/**
 * A concessão INICIAL da matriz, derivada do menu.
 *
 * Cada tela controlável nasce concedida aos setores que `config/retaguarda_menu.php`
 * declara em `setores` — operável de verdade (vê, opera, inclui e exclui), que é
 * o que "este setor usa esta tela" quer dizer. O administrador não é semeado: o
 * acesso total dele é desvio no código, e linha de tabela alguém desmarca por
 * engano.
 *
 * Idempotente e NÃO destrutivo: usa `firstOrCreate`, então rodar de novo cria só
 * o que falta e nunca desfaz o que o gerente decidiu na tela. É o que permite
 * semear de novo depois de acrescentar uma tela ao menu, sem medo.
 *
 * Tela que o menu não conceder a ninguém (como o próprio Modo Gerente, de
 * administrador) simplesmente não gera linha: fica negada a todos, e a concessão
 * — se houver — é ato consciente de quem administra.
 */
class PermissoesSetorSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogoFuncionalidades::slugs() as $slug) {
            foreach (CatalogoFuncionalidades::setoresSemente($slug) as $setor) {
                if ($setor === PermissaoService::SETOR_ADMIN) {
                    continue;
                }

                PermissaoSetor::firstOrCreate(
                    ['setor' => $setor, 'slug' => $slug],
                    [
                        'visivel' => true,
                        'habilitado' => true,
                        'apenas_leitura' => false,
                        'incluir' => true,
                        'excluir' => true,
                    ],
                );
            }
        }
    }
}

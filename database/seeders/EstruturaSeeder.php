<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Equipe;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A estrutura de trabalho — áreas, bairros, equipes e fiscais — saindo do
 * arquivo de protótipo e virando banco.
 *
 * A fonte é `config/prototipo_estrutura.php`, o mesmo arquivo que as telas liam.
 * Semear a partir dele (em vez de redigitar) tem uma razão prática: a
 * demonstração que o cliente já viu continua idêntica depois da consolidação, e
 * qualquer diferença entre o que ele viu e o que o sistema faz agora é DEFEITO,
 * não "mudança da migração".
 *
 * ## Os fiscais viram CONTA de verdade
 *
 * No protótipo o fiscal era um nome com matrícula dentro de um array. Aqui ele é
 * `users` com o setor `fiscal` — porque é ele que vai entrar no aplicativo, e é
 * o `user_id` dele que assina a fiscalização. Sem conta, "quem fez a vistoria"
 * seria texto solto, e o registro não teria autor que sobrevivesse a nada.
 *
 * A senha de demonstração é a própria matrícula em minúsculo — vale para o
 * ambiente de demonstração e **nunca** para produção, onde o primeiro acesso é
 * pelo fluxo de definição de senha.
 *
 * ## Idempotente
 *
 * Roda de novo sem duplicar: tudo é `updateOrCreate` pela chave natural (nome da
 * área, código da equipe, matrícula do usuário). É o que permite semear por cima
 * de um banco que já tem trabalho feito, sem apagar nada.
 */
class EstruturaSeeder extends Seeder
{
    public function run(): void
    {
        $setorFiscal = Setor::where('slug', 'fiscal')->first();
        $setorChefe = Setor::where('slug', 'chefe-de-setor')->first();

        foreach ((array) config('prototipo_estrutura.areas', []) as $dados) {
            $area = Area::updateOrCreate(
                ['nome' => (string) $dados['nome']],
                [
                    'regiao' => $dados['regiao'] ?? null,
                    'recorte' => (string) ($dados['recorte'] ?? Area::RECORTE_BAIRROS),
                    'turno' => $dados['turno'] ?? null,
                    'ativa' => true,
                ],
            );

            $this->ligarChefeDeSetor($area, (array) ($dados['chefe_de_setor'] ?? []), $setorChefe);
            $this->semearBairros($area, (array) ($dados['bairros'] ?? []));

            $equipe = Equipe::updateOrCreate(
                ['codigo' => (string) $dados['equipe']],
                [
                    'nome' => 'Equipe '.$dados['equipe'],
                    'area_id' => $area->id,
                    'encarregado' => $dados['encarregado'] ?? null,
                    'turno' => $dados['turno'] ?? null,
                    'ativa' => true,
                ],
            );

            $this->semearFiscais($equipe, (array) ($dados['fiscais'] ?? []), $setorFiscal);
        }
    }

    /**
     * O Chefe de Setor só vira vínculo quando tem MATRÍCULA.
     *
     * Área cujo chefe é apenas um nome (sem conta de demonstração) fica com
     * `chefe_de_setor_id` nulo de propósito: inventar uma conta para ele faria o
     * sistema prometer um acesso que ninguém tem, e o recorte por área passaria
     * a funcionar na demonstração e a falhar na vida real.
     *
     * @param  array<string, mixed>  $chefe
     */
    private function ligarChefeDeSetor(Area $area, array $chefe, ?Setor $setorChefe): void
    {
        $matricula = $chefe['matricula'] ?? null;

        if ($matricula === null) {
            return;
        }

        $usuario = $this->conta((string) $matricula, (string) ($chefe['nome'] ?? $matricula));

        if ($setorChefe !== null) {
            $usuario->setores()->syncWithoutDetaching([$setorChefe->id]);
        }

        $area->chefe_de_setor_id = $usuario->id;
        $area->save();
    }

    /** @param  list<string>  $bairros */
    private function semearBairros(Area $area, array $bairros): void
    {
        foreach ($bairros as $bairro) {
            /*
             * A chave é o PAR. Semear pelo nome do bairro só faria o último
             * arquivo vencer, e os três bairros que pertencem a duas áreas
             * perderiam uma delas em silêncio — junto com o aviso de divisa que
             * a tela mostra ao coordenador.
             */
            $vinculo = AreaBairro::firstOrCreate([
                'area_id' => $area->id,
                'bairro' => (string) $bairro,
            ]);

            /*
             * A coordenada do bairro é FATO sobre a cidade, e vem do catálogo
             * geográfico. Bairro que não estiver lá fica sem coordenada: aparece
             * na lista e não aparece no mapa — melhor do que plantá-lo no centro
             * da cidade e fazer a chefia acreditar que há trabalho ali.
             */
            $lugar = (array) config('geografia.bairros.'.$bairro, []);

            if ($lugar !== [] && $vinculo->latitude === null) {
                $vinculo->latitude = $lugar['lat'];
                $vinculo->longitude = $lugar['lng'];
                $vinculo->save();
            }
        }
    }

    /** @param  list<array{matricula: string, nome: string}>  $fiscais */
    private function semearFiscais(Equipe $equipe, array $fiscais, ?Setor $setorFiscal): void
    {
        $ids = [];

        foreach ($fiscais as $fiscal) {
            $usuario = $this->conta((string) $fiscal['matricula'], (string) $fiscal['nome']);

            if ($setorFiscal !== null) {
                $usuario->setores()->syncWithoutDetaching([$setorFiscal->id]);
            }

            $ids[] = $usuario->id;
        }

        /*
         * `syncWithoutDetaching`, e não `sync`: o fiscal pode ter sido posto
         * numa equipe por quem administra o sistema, e um seeder que roda de novo
         * não pode desfazer decisão de gente.
         */
        $equipe->fiscais()->syncWithoutDetaching($ids);
    }

    /**
     * A conta da pessoa, criada se ainda não existir.
     *
     * ⚠️ `firstOrCreate`, e não `updateOrCreate`: as contas de demonstração que já
     * existem (`gestor1`, `coordenador`, `admin`) têm e-mail e SENHA definidos por
     * quem administra. Um seeder que "atualiza" esses campos trocaria a senha de
     * quem já usa o sistema toda vez que alguém semeasse a estrutura — e o dono
     * descobriria isso tentando entrar.
     */
    private function conta(string $matricula, string $nome): User
    {
        $login = User::normalizarMatricula($matricula);

        return User::firstOrCreate(
            ['login' => $login],
            [
                'name' => $nome,
                // O e-mail é obrigatório e único; sem e-mail real, um derivado da
                // matrícula mantém a coluna honesta sem inventar endereço de gente.
                'email' => $login.'@sefal.local',
                // Senha de DEMONSTRAÇÃO: a própria matrícula. Em produção o
                // primeiro acesso é pelo fluxo de definição de senha.
                'password' => Hash::make($login),
                'ativo' => true,
            ],
        );
    }
}

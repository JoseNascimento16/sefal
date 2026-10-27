<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Bairro;
use App\Models\Equipe;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A estrutura de trabalho — áreas, bairros, equipes, líderes e fiscais — saindo
 * do arquivo de protótipo e virando banco.
 *
 * A fonte é `config/prototipo_estrutura.php`, o mesmo arquivo que as telas liam.
 * Semear a partir dele (em vez de redigitar) tem uma razão prática: a
 * demonstração que o cliente já viu continua idêntica depois da consolidação, e
 * qualquer diferença entre o que ele viu e o que o sistema faz agora é DEFEITO,
 * não "mudança da migração".
 *
 * ## O LÍDER DE EQUIPE vira conta de verdade (22/09/2026)
 *
 * O documento do cliente nomeia o "encarregado" de cada equipe. Depois da
 * conversa com coordenadores, chefe de setor e líderes, ficou claro que essa
 * pessoa é o **líder de equipe**: é ela que recebe o que o Chefe de Setor
 * encaminha à equipe, direciona aos fiscais e lê o que volta da rua. Então ela
 * entra no sistema — `users` com o setor `lider-de-equipe`, ligada à equipe por
 * `equipes.lider_id`.
 *
 * A matrícula é `lider-<código da equipe>` (minúsculo) até o cliente informar
 * as reais: matrícula identifica gente, e trocá-la depois não mexe no vínculo,
 * que é por id. O nome em texto (`encarregado`) FICA na equipe — é o dado do
 * documento, e é o que a tela mostra se a conta não existir.
 *
 * ## Os fiscais também são conta
 *
 * No protótipo o fiscal era um nome com matrícula dentro de um array. Aqui ele é
 * `users` com o setor `fiscal` — porque é ele que vai entrar no aplicativo, e é
 * o `user_id` dele que assina a fiscalização.
 *
 * ## O que NÃO se semeia mais: um chefe por área
 *
 * Até 22/09 cada área ganhava um `chefe_de_setor_id`. O Chefe de Setor é um só
 * e responde por tudo, então o vínculo por área deixou de existir — a chave
 * `chefe_de_setor` do arquivo de protótipo é ignorada. A conta do chefe é de
 * demonstração e nasce em `sefal:preparar-demonstracao`, não aqui.
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
        $setorLider = Setor::where('slug', 'lider-de-equipe')->first();

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

            $this->ligarLider($equipe, $setorLider);
            $this->semearFiscais($equipe, (array) ($dados['fiscais'] ?? []), $setorFiscal);
        }

        // O catálogo de bairros nasce dos bairros que as áreas citam (25/09/2026).
        Bairro::sincronizarDasAreas();
    }

    /**
     * O encarregado do documento vira o líder da equipe, com conta.
     *
     * Só quando há NOME: equipe sem encarregado no documento fica sem líder, e a
     * tela diz isso — inventar uma conta prometeria um acesso que ninguém tem.
     *
     * `lider_id` só é PREENCHIDO quando está vazio: se quem administra já trocou
     * o líder pela tela, o seeder não desfaz decisão de gente.
     */
    private function ligarLider(Equipe $equipe, ?Setor $setorLider): void
    {
        $nome = trim((string) ($equipe->encarregado ?? ''));

        if ($nome === '') {
            return;
        }

        $usuario = $this->conta('lider-'.mb_strtolower($equipe->codigo), $nome);

        if ($setorLider !== null) {
            $usuario->setores()->syncWithoutDetaching([$setorLider->id]);
        }

        if ($equipe->lider_id === null) {
            $equipe->lider_id = $usuario->id;
            $equipe->save();
        }
    }

    /** @param  list<string>  $bairros */
    private function semearBairros(Area $area, array $bairros): void
    {
        foreach ($bairros as $bairro) {
            /*
             * A chave é o PAR. Semear pelo nome do bairro só faria o último
             * arquivo vencer, e os três bairros que pertencem a duas áreas
             * perderiam uma delas em silêncio — junto com o aviso de divisa que
             * a tela mostra a quem encaminha.
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
     * ⚠️ `firstOrCreate`, e não `updateOrCreate`: as contas que já existem têm
     * e-mail e SENHA definidos por quem administra. Um seeder que "atualiza"
     * esses campos trocaria a senha de quem já usa o sistema toda vez que alguém
     * semeasse a estrutura — e o dono descobriria isso tentando entrar.
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

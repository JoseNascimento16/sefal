<?php

namespace App\Console\Commands;

use App\Models\Demanda;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemonstracaoSeeder;
use Database\Seeders\EstruturaSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

/**
 * Deixa a demonstração pronta para ser percorrida — e só quando ela está vazia.
 *
 * ## Por que este comando existe
 *
 * O ambiente de demonstração roda com filesystem EFÊMERO: a cada boot o banco
 * volta ao arquivo versionado, e o que foi clicado na véspera desaparece. Isso é
 * desejado — a demo sempre abre no mesmo estado. O que NÃO era desejado é o que
 * aconteceu: o arquivo versionado estava vazio, o boot rodava `migrate` (criando
 * as tabelas) e mais nada. O sistema subia íntegro e sem um único registro.
 *
 * O sintoma não pareceu falta de dado: a tela abria normalmente, e a varredura de
 * repetições dizia "nenhuma repetição encontrada" — resposta correta para uma
 * fila vazia, e indistinguível de uma funcionalidade quebrada.
 *
 * ## Por que é IDEMPOTENTE e não destrutivo
 *
 * Ele semeia apenas o que falta e nunca apaga: num ambiente onde alguém pode
 * estar no meio de uma apresentação, um comando de boot que zera a base é pior
 * que a base vazia. Se já há demanda, ele não faz nada e diz por quê.
 *
 * ⚠️ Não roda em produção — o {@see DemonstracaoSeeder} planta cadastro
 * inventado, e lá os ambulantes vêm do SGCI e as demandas, da integração.
 */
class PrepararDemonstracao extends Command
{
    protected $signature = 'sefal:preparar-demonstracao
                            {--senha= : Só para uma conta SEM senha; nunca troca a de quem já tem}';

    protected $description = 'Semeia sistema e demonstração num banco vazio, sem apagar nada do que já existe.';

    public function handle(): int
    {
        if (App::environment('production')) {
            $this->components->error('A demonstração planta cadastros inventados e não roda em produção.');

            return self::FAILURE;
        }

        /*
         * Os SETORES primeiro, sozinhos: as contas precisam deles para receber o
         * papel, e é a única dependência que elas têm.
         */
        Artisan::call('db:seed', ['--class' => SetoresSeeder::class, '--force' => true]);

        /*
         * As contas vêm ANTES do resto, e por dois motivos.
         *
         * O primeiro é ordem: o {@see \Database\Seeders\EstruturaSeeder} também
         * cria essas matrículas, com a senha igual à própria matrícula. Se ele
         * chegar antes, a conta nasce com uma senha que ninguém combinou — e,
         * como este comando nunca sobrescreve senha, é essa que fica.
         *
         * O segundo é o desvio mais abaixo: numa base que já tem demandas, o
         * preparo para por ali. Deixar as contas depois dele significava que,
         * quanto mais pronta a demonstração, menos chance de existir porta — que
         * foi exatamente o que aconteceu.
         */
        $this->garantirContasDaDemonstracao();

        // O resto do sistema: permissões, listas de escolha e a estrutura de
        // áreas e equipes. Todos idempotentes, e é o que faz a guarda de acesso
        // existir.
        $this->components->task('Estrutura do sistema', function () {
            Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

            return true;
        });

        if (Demanda::query()->exists()) {
            $this->components->info(
                'A demonstração já tem demandas — nada semeado. '
                .'Para refazê-la do zero, apague o banco e rode de novo.',
            );

            return self::SUCCESS;
        }

        $this->components->task('Cidade de demonstração', function () {
            Artisan::call('db:seed', ['--class' => DemonstracaoSeeder::class, '--force' => true]);

            return true;
        });

        $this->components->info(sprintf(
            '%d demandas na base, %d delas aguardando pré-triagem.',
            Demanda::count(),
            Demanda::emPreTriagem()->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * As contas pelas quais se entra na demonstração.
     *
     * ## ⚠️ A SENHA DE QUEM JÁ TEM CONTA NUNCA É TOCADA
     *
     * Esta regra custou caro para ser aprendida. Uma versão deste comando repunha
     * a senha a cada boot "para a demonstração ser previsível", e o efeito foi o
     * contrário: quem tinha definido as próprias senhas ficou do lado de fora, e
     * descobriu isso tentando entrar. O mesmo aviso já estava escrito no
     * {@see EstruturaSeeder}, que usa `firstOrCreate` por esse
     * motivo — e foi ignorado aqui.
     *
     * Senha é decisão de quem administra, não do boot. O comando só PREENCHE o
     * que está vazio: conta nova ganha uma senha inicial, conta existente fica
     * exatamente como está. Trocar a senha de alguém é ato deliberado e tem
     * comando próprio: `sefal:setar-senha`.
     *
     * ## Por que existe um COORDENADOR na lista
     *
     * A pré-triagem e a caixa são a mesa DELE. Demonstrá-las como administrador
     * mostra a tela, mas não o papel: o administrador enxerga tudo, então não se
     * vê o recorte que o coordenador de verdade tem.
     *
     * @var list<array{login: string, nome: string, senha: string, setor: ?string, admin: bool}>
     */
    private const CONTAS = [
        ['login' => 'admin', 'nome' => 'Administrador', 'senha' => 'admin123', 'setor' => 'administrador', 'admin' => true],
        ['login' => 'coordenador', 'nome' => 'Coordenador', 'senha' => 'coordenador123', 'setor' => 'coordenador', 'admin' => false],
        ['login' => 'fiscal', 'nome' => 'Fiscal', 'senha' => 'fiscal123', 'setor' => 'fiscal', 'admin' => false],
        ['login' => 'gestor1', 'nome' => 'Gestor 1', 'senha' => 'gestor123', 'setor' => 'chefe-de-setor', 'admin' => false],
        ['login' => 'gestor2', 'nome' => 'Gestor 2', 'senha' => 'gestor123', 'setor' => 'chefe-de-setor', 'admin' => false],
        ['login' => 'gestor3', 'nome' => 'Gestor 3', 'senha' => 'gestor123', 'setor' => 'chefe-de-setor', 'admin' => false],
    ];

    private function garantirContasDaDemonstracao(): void
    {
        foreach (self::CONTAS as $conta) {
            $usuario = User::firstOrNew(['login' => $conta['login']]);
            $nasceuAgora = ! $usuario->exists;

            $usuario->fill([
                'name' => $usuario->name ?: $conta['nome'],
                // Sem e-mail de gente real: um derivado da matrícula mantém a
                // coluna honesta sem inventar endereço de ninguém.
                'email' => $usuario->email ?: $conta['login'].'@sefal.demo',
                'ativo' => true,
            ]);

            // O `admin` só é CONCEDIDO, nunca retirado: se alguém promoveu uma
            // conta, o boot não desfaz isso.
            if ($conta['admin']) {
                $usuario->admin = true;
            }

            /*
             * Só preenche o vazio. O cast `hashed` do model cifra na atribuição —
             * passar por `Hash::make` aqui cifraria duas vezes.
             */
            if ($nasceuAgora || ($usuario->password ?? '') === '') {
                $usuario->password = (string) ($this->option('senha') ?: $conta['senha']);
            }

            $usuario->save();

            if ($conta['setor'] !== null) {
                $setor = Setor::where('slug', $conta['setor'])->first();

                // `syncWithoutDetaching`: quem administra pode ter dado outro
                // setor à conta, e o boot não desfaz decisão de gente.
                $setor && $usuario->setores()->syncWithoutDetaching([$setor->id]);
            }

            $this->components->twoColumnDetail(
                sprintf('<fg=yellow>%s</> · %s', $conta['login'], $conta['setor'] ?? 'administrador'),
                $nasceuAgora ? 'conta criada' : 'já existia — senha preservada',
            );
        }
    }
}

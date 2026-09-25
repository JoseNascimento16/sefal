<?php

namespace App\Console\Commands;

use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemonstracaoSeeder;
use Database\Seeders\EstruturaSeeder;
use Database\Seeders\ParametrizacaoFiscalizacaoSeeder;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
 *
 * ## `--so-estas-contas` — a demonstração com QUATRO portas, e só elas
 *
 * Pedido do dono (24/09/2026): na demonstração pública, uma conta por papel —
 * `admin`, `chefe`, `lider1`, `fiscal1` — e nenhuma outra. Com a opção, depois
 * de semear, o comando apaga toda conta fora da lista e amarra as que ficam à
 * estrutura: `lider1` passa a liderar a equipe A1 — e só ela (dono,
 * 25/09/2026: com todas, o recorte do líder não aparecia na demonstração) —, e
 * `fiscal1` passa a integrar todas as equipes; as fiscalizações assinadas por
 * fiscais apagados passam a ser dele.
 *
 * É opção, e não comportamento: apagar conta é destrutivo, e este comando também
 * roda fora da demonstração pública. Só o boot do Render a passa.
 */
class PrepararDemonstracao extends Command
{
    /** A equipe que o `lider1` lidera na demonstração enxuta — e só ela. */
    public const EQUIPE_DO_LIDER = 'A1';

    protected $signature = 'sefal:preparar-demonstracao
                            {--senha= : Só para uma conta SEM senha; nunca troca a de quem já tem}
                            {--so-estas-contas : Apaga toda conta fora da lista da demonstração (só no Render)}';

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
            /*
             * Na demonstração enxuta com a estrutura já no banco, a ESTRUTURA não
             * é semeada de novo: o `EstruturaSeeder` recriaria ~40 contas (líderes
             * por equipe e fiscais), cada uma com bcrypt, só para o enxugamento
             * apagá-las em seguida — minutos de CPU no boot do Render, o bastante
             * para o deploy estourar o tempo (aconteceu em 24/09/2026). Permissões
             * e listas de escolha continuam: são baratas e trazem tela nova.
             */
            if ($this->option('so-estas-contas') && Equipe::query()->exists()) {
                Artisan::call('db:seed', ['--class' => PermissoesSetorSeeder::class, '--force' => true]);
                Artisan::call('db:seed', ['--class' => ParametrizacaoFiscalizacaoSeeder::class, '--force' => true]);

                return true;
            }

            Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

            return true;
        });

        if (Demanda::query()->exists()) {
            $this->components->info(
                'A demonstração já tem demandas — nada semeado. '
                .'Para refazê-la do zero, apague o banco e rode de novo.',
            );

            $this->enxugarSePedido();
            // As fotos e os anexos semeados precisam de arquivo para ver e baixar —
            // e o disco do Render é apagado a cada deploy.
            Artisan::call('sefal:arquivos-de-demonstracao');

            return self::SUCCESS;
        }

        $this->components->task('Cidade de demonstração', function () {
            Artisan::call('db:seed', ['--class' => DemonstracaoSeeder::class, '--force' => true]);

            return true;
        });

        $this->enxugarSePedido();
        Artisan::call('sefal:arquivos-de-demonstracao');

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
     * ## Uma conta por papel (decisão do dono, 24/09/2026)
     *
     * `admin` (administrador), `chefe` (o Chefe de Setor — um só, vê tudo),
     * `lider1` (líder de equipe) e `fiscal1` (fiscal). Com `--so-estas-contas`
     * elas são as ÚNICAS, e `lider1`/`fiscal1` são amarrados a todas as equipes
     * ({@see enxugarSePedido}); sem a opção, as contas da estrutura
     * (`lider-<equipe>`, fiscais) continuam existindo ao lado delas.
     *
     * Não há COORDENADOR: os coordenadores trabalham no e-Salvador e não entram
     * no SEFAL (decisão do dono, 22/09/2026).
     *
     * @var list<array{login: string, nome: string, senha: string, setor: ?string, admin: bool}>
     */
    private const CONTAS = [
        ['login' => 'admin', 'nome' => 'Administrador', 'senha' => 'admin123', 'setor' => 'administrador', 'admin' => true],
        ['login' => 'chefe', 'nome' => 'Chefe de Setor', 'senha' => 'chefe123', 'setor' => 'chefe-de-setor', 'admin' => false],
        ['login' => 'lider1', 'nome' => 'Líder de Equipe', 'senha' => 'lider123', 'setor' => 'lider-de-equipe', 'admin' => false],
        ['login' => 'fiscal1', 'nome' => 'Fiscal', 'senha' => 'fiscal123', 'setor' => 'fiscal', 'admin' => false],
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

    /**
     * Deixa na base SÓ as contas da lista — quando `--so-estas-contas` é passada.
     *
     * Amarra antes de apagar, para nada ficar órfão: `lider1` lidera a equipe
     * A1 (só ela; as outras ficam sem líder com conta), `fiscal1` integra todas, e as fiscalizações dos fiscais que saem
     * passam a ele (`fiscal_id` é obrigatório e não tem `ON DELETE`). O resto das
     * referências a `users` é `nullOnDelete`/`cascadeOnDelete` e se resolve só.
     */
    private function enxugarSePedido(): void
    {
        if (! $this->option('so-estas-contas')) {
            return;
        }

        $this->components->task('Só as contas da demonstração', function () {
            DB::transaction(function () {
                $ficam = User::whereIn('login', array_column(self::CONTAS, 'login'))->pluck('id', 'login');
                $lider = $ficam['lider1'] ?? null;
                $fiscal = $ficam['fiscal1'] ?? null;

                // O líder da demonstração é o da A1 e de nenhuma outra (dono,
                // 25/09/2026). As demais ficam sem líder com conta — é o que faz o
                // recorte do líder aparecer: ele vê só a fila e o mapa da A1.
                if ($lider !== null) {
                    Equipe::query()->where('codigo', self::EQUIPE_DO_LIDER)->update(['lider_id' => $lider]);
                    Equipe::query()->where('codigo', '!=', self::EQUIPE_DO_LIDER)->update(['lider_id' => null]);
                }

                if ($fiscal !== null) {
                    DB::table('equipe_fiscais')->insertOrIgnore(Equipe::pluck('id')->map(static fn (int $equipe): array => [
                        'equipe_id' => $equipe,
                        'user_id' => $fiscal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all());

                    Fiscalizacao::whereNotIn('fiscal_id', $ficam->values())->update(['fiscal_id' => $fiscal]);
                }

                // Remoção DE VEZ, e não a lixeira da tela de Usuários: a
                // demonstração fica com essas contas e nenhuma outra — nem na aba
                // Excluídos, nem ocupando matrícula.
                User::withTrashed()->whereNotIn('id', $ficam->values())->get()->each->forceDelete();
            });

            return true;
        });
    }
}

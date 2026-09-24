<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\UsuarioRequest;
use App\Models\Setor;
use App\Models\User;
use App\Support\ConviteDePrimeiroAcesso;
use App\Support\ListagensDaRetaguarda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sistema › Usuários — quem tem conta na Retaguarda, e em que setor.
 *
 * Trazida da tela de Usuários do Codecon (pedido do dono, 25/09/2026), com o
 * que ela faz: incluir com convite de primeiro acesso por e-mail, alterar
 * setores e situação, reenviar o convite, excluir para uma LIXEIRA restaurável
 * e remover de vez depois de {@see DIAS_RETENCAO} dias.
 *
 * O que muda por ser o SEFAL:
 *
 *  - não há cargo nem Diretor; o posto único daqui é o **Chefe de Setor** (RN-01
 *    de papéis): marcar outra pessoa como chefe tira o setor de quem tinha, e a
 *    tela avisa QUEM sai antes de salvar;
 *  - a conta com HISTÓRICO (trâmite, vistoria, decisão) nunca é removida de vez:
 *    fica na lixeira, sem login, para o nome continuar no que ela fez;
 *  - quem entra aqui é decidido pela matriz do Modo Gerente (slug `usuarios`,
 *    semeado só para o administrador). Se a tela for concedida a outro setor, o
 *    poder de criar e tirar ADMINISTRADOR continua só com administrador — senão a
 *    concessão da tela seria, de brinde, a concessão de tudo.
 */
class UsuariosController extends Controller
{
    /** Dias entre a exclusão e a remoção definitiva. */
    public const DIAS_RETENCAO = 3;

    public const ADMINISTRADOR = 'administrador';

    public const CHEFE = 'chefe-de-setor';

    public const LIDER = 'lider-de-equipe';

    /** O que cada setor faz, dito na tela ao lado da caixa de marcar. */
    private const DESCRICOES = [
        self::ADMINISTRADOR => 'Vê e administra tudo, inclusive usuários e o Modo Gerente.',
        self::CHEFE => 'Recebe o que chega dos canais, encaminha às equipes e responde à origem. É um só no setor.',
        self::LIDER => 'Encarregado de uma equipe: envia aos fiscais e decide sobre o retorno de campo. A equipe dele é vinculada em Áreas e Equipes.',
        'fiscal' => 'Trabalha em rua pelo aplicativo; na Retaguarda, consulta.',
    ];

    public function index(Request $request): Response
    {
        $ativos = User::query()
            ->select(['id', 'login', 'name', 'email', 'admin', 'ativo', 'senha_definida_em', 'created_at'])
            ->with(['setores', 'equipesQueLidera:id,codigo,lider_id', 'equipesComoFiscal:equipes.id,codigo'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'login' => $u->login,
                'name' => $u->name,
                'email' => $u->email,
                'ativo' => (bool) $u->ativo,
                'senhaDefinida' => $u->senhaDefinida(),
                'setores' => $this->setoresDe($u),
                'lidera' => $u->equipesQueLidera->pluck('codigo')->sort()->values()->all(),
                'fiscalEm' => $u->equipesComoFiscal->pluck('codigo')->sort()->values()->all(),
                'criadoEm' => $u->created_at?->format('Y-m-d'),
            ]);

        $excluidos = User::onlyTrashed()
            ->select(['id', 'login', 'name', 'email', 'admin', 'deleted_at'])
            ->with('setores')
            ->orderByDesc('deleted_at')
            ->get()
            ->map(function (User $u): array {
                $historico = $u->temHistorico();
                $remocao = $u->deleted_at->copy()->addDays(self::DIAS_RETENCAO);

                return [
                    'id' => $u->id,
                    'login' => $u->login,
                    'name' => $u->name,
                    'email' => $u->email,
                    'setores' => $this->setoresDe($u),
                    'excluidoEm' => $u->deleted_at->format('Y-m-d\TH:i:s'),
                    // Conta com histórico não é removida — a data seria promessa falsa.
                    'remocaoEm' => $historico ? null : $remocao->format('Y-m-d\TH:i:s'),
                    'diasRestantes' => $historico ? null : max(0, (int) ceil(Date::now()->diffInSeconds($remocao, false) / 86400)),
                    'temHistorico' => $historico,
                ];
            });

        return Inertia::render('Retaguarda/Sistema/Usuarios', [
            'usuarios' => $ativos,
            'excluidos' => $excluidos,
            'setoresOpcoes' => $this->setoresOpcoes(),
            'diasRetencao' => self::DIAS_RETENCAO,
            'euId' => $request->user()?->id,
            // Criar, tirar e mexer em ADMINISTRADOR é só de administrador — mesmo
            // que a tela seja concedida a outro setor no Modo Gerente.
            'mexeEmAdministrador' => (bool) $request->user()?->ehAdmin(),
            'listagens' => ListagensDaRetaguarda::para(['usuarios.ativos', 'usuarios.excluidos']),
        ]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $setores = $dados['setores'];

        if ($recusa = $this->recusaDeAdministrador($request->user(), null, $setores)) {
            return back()->withErrors(['setores' => $recusa]);
        }

        [$usuario, $saiu] = DB::transaction(function () use ($dados, $setores) {
            $usuario = User::criarComPrimeiroAcessoPendente([
                'login' => $dados['login'],
                'name' => $dados['name'],
                'email' => $dados['email'],
                'admin' => in_array(self::ADMINISTRADOR, $setores, true),
                'ativo' => $dados['ativo'],
            ]);

            $usuario->setores()->sync($this->idsDosSetores($setores));

            return [$usuario, $this->garantirChefeUnico($usuario, $setores)];
        });

        $aviso = $this->avisoDeChefe($saiu, false);

        return match (ConviteDePrimeiroAcesso::enviar($usuario)) {
            ConviteDePrimeiroAcesso::ENVIADO => to_route('retaguarda.usuarios.index')
                ->with('flash.sucesso', "Conta de {$usuario->name} criada. O convite para definir a senha foi enviado para {$usuario->email}.".$aviso),
            default => to_route('retaguarda.usuarios.index')
                ->with('flash.erro', "Conta de {$usuario->name} criada, mas o convite não pôde ser enviado agora. Abra a conta e use \"Enviar convite\".".$aviso),
        };
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $dados = $request->validated();
        $setores = $dados['setores'];
        $logado = $request->user();
        $eraAdministrador = $usuario->ehAdmin();

        if ($recusa = $this->recusaDeAdministrador($logado, $usuario, $setores)) {
            return back()->withErrors(['setores' => $recusa]);
        }

        // Travas contra se trancar do lado de fora num clique: só outro
        // administrador desfaria.
        if ($logado?->id === $usuario->id) {
            if ($eraAdministrador && ! in_array(self::ADMINISTRADOR, $setores, true)) {
                return back()->withErrors(['setores' => 'Você não pode tirar o setor Administrador de si mesmo.']);
            }

            if (! $dados['ativo']) {
                return back()->withErrors(['ativo' => 'Você não pode desativar a sua própria conta.']);
            }
        }

        $eraChefe = $usuario->setores->contains('slug', self::CHEFE);

        $saiu = DB::transaction(function () use ($usuario, $dados, $setores) {
            $usuario->update([
                'name' => $dados['name'],
                'email' => $dados['email'],
                'admin' => in_array(self::ADMINISTRADOR, $setores, true),
                'ativo' => $dados['ativo'],
            ]);

            $usuario->setores()->sync($this->idsDosSetores($setores));

            return $this->garantirChefeUnico($usuario, $setores);
        });

        $ficouSemChefe = $eraChefe && ! in_array(self::CHEFE, $setores, true) && ! $this->existeChefe();

        return to_route('retaguarda.usuarios.index')
            ->with('flash.sucesso', "Dados de {$usuario->name} atualizados.".$this->avisoDeChefe($saiu, $ficouSemChefe));
    }

    /** Vai para a lixeira: some do sistema e do login, e pode voltar. */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        $logado = $request->user();

        if ($logado?->id === $usuario->id) {
            return back()->with('flash.erro', 'Você não pode excluir a sua própria conta.');
        }

        if ($usuario->ehAdmin() && ! $logado?->ehAdmin()) {
            return back()->with('flash.erro', 'Só um administrador pode excluir a conta de outro administrador.');
        }

        $usuario->delete();

        $destino = $usuario->temHistorico()
            ? 'Ela tem histórico no sistema, então fica guardada na lixeira — sem acesso, mas com o nome preservado no que fez.'
            : 'Ela será removida de vez em '.self::DIAS_RETENCAO.' dias; até lá, pode ser restaurada.';

        return to_route('retaguarda.usuarios.index')
            ->with('flash.sucesso', "Conta de {$usuario->name} movida para Excluídos. {$destino}");
    }

    public function restaurar(int $usuario): RedirectResponse
    {
        $conta = User::onlyTrashed()->findOrFail($usuario);
        $conta->restore();

        return to_route('retaguarda.usuarios.index')
            ->with('flash.sucesso', "Conta de {$conta->name} restaurada, com os mesmos setores e equipes.");
    }

    public function convite(User $usuario): RedirectResponse
    {
        if ($usuario->senhaDefinida()) {
            return back()->with('flash.erro', "{$usuario->name} já definiu a senha. Para trocá-la, a própria pessoa usa \"Esqueci minha senha\" na tela de entrada.");
        }

        return match (ConviteDePrimeiroAcesso::enviar($usuario)) {
            ConviteDePrimeiroAcesso::ENVIADO => back()->with('flash.sucesso', "Convite enviado para {$usuario->email}."),
            ConviteDePrimeiroAcesso::AGUARDE => back()->with('flash.erro', 'Um convite acabou de ser enviado para esta conta. Aguarde um minuto antes de reenviar.'),
            default => back()->with('flash.erro', 'Não foi possível enviar o convite agora. Confira o e-mail da conta e tente de novo.'),
        };
    }

    // ── Regras ──────────────────────────────────────────────────────────────

    /**
     * Mexer em ADMINISTRADOR — dar, tirar, ou alterar a conta de quem é — é só de
     * administrador. Devolve o motivo da recusa, ou nulo.
     *
     * @param  list<string>  $setores
     */
    private function recusaDeAdministrador(?User $logado, ?User $alvo, array $setores): ?string
    {
        if ($logado?->ehAdmin()) {
            return null;
        }

        if ($alvo?->ehAdmin()) {
            return 'Só um administrador pode alterar a conta de outro administrador.';
        }

        if (in_array(self::ADMINISTRADOR, $setores, true)) {
            return 'Só um administrador pode dar o setor Administrador a alguém.';
        }

        return null;
    }

    /**
     * O Chefe de Setor é UM só (RN-01 de papéis). Marcar esta conta como chefe
     * tira o setor de quem o tinha; devolve os nomes de quem saiu, para a tela
     * dizer em voz alta — a troca muda de mãos a Caixa de Entrada inteira.
     *
     * @param  list<string>  $setores
     * @return list<string>
     */
    private function garantirChefeUnico(User $usuario, array $setores): array
    {
        if (! in_array(self::CHEFE, $setores, true)) {
            return [];
        }

        $chefe = Setor::where('slug', self::CHEFE)->first();

        if ($chefe === null) {
            return [];
        }

        $outros = User::query()
            ->where('id', '!=', $usuario->id)
            ->whereHas('setores', fn ($q) => $q->where('setores.id', $chefe->id))
            ->get();

        foreach ($outros as $outro) {
            $outro->setores()->detach($chefe->id);
        }

        return $outros->pluck('name')->all();
    }

    private function existeChefe(): bool
    {
        return User::whereHas('setores', fn ($q) => $q->where('slug', self::CHEFE))->exists();
    }

    /** @param  list<string>  $saiu */
    private function avisoDeChefe(array $saiu, bool $ficouSemChefe): string
    {
        if ($saiu !== []) {
            $nomes = count($saiu) === 1
                ? $saiu[0]
                : implode(', ', array_slice($saiu, 0, -1)).' e '.$saiu[count($saiu) - 1];

            return ' '.$nomes.(count($saiu) === 1 ? ' deixou' : ' deixaram').' de ser Chefe de Setor — o setor tem um chefe só.';
        }

        return $ficouSemChefe
            ? ' Atenção: o sistema ficou sem Chefe de Setor — a Caixa de Entrada fica sem dono até alguém ser marcado.'
            : '';
    }

    /**
     * Os setores da conta como a tela os marca. O administrador pela FLAG (conta
     * antiga, de antes do setor) aparece com o setor marcado: são duas portas
     * para o mesmo papel, e a tela mostra uma só.
     *
     * @return list<string>
     */
    private function setoresDe(User $u): array
    {
        $slugs = $u->setores->pluck('slug')->all();

        if ($u->admin && ! in_array(self::ADMINISTRADOR, $slugs, true)) {
            $slugs[] = self::ADMINISTRADOR;
        }

        $ordem = array_keys((array) config('retaguarda.setores', []));
        usort($slugs, fn (string $a, string $b): int => array_search($a, $ordem, true) <=> array_search($b, $ordem, true));

        return array_values($slugs);
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function idsDosSetores(array $slugs): array
    {
        return Setor::whereIn('slug', $slugs)->pluck('id')->all();
    }

    /** @return list<array{slug: string, nome: string, descricao: string}> */
    private function setoresOpcoes(): array
    {
        $nomes = Setor::query()->pluck('nome', 'slug')->all();

        return collect(array_keys((array) config('retaguarda.setores', [])))
            ->filter(fn (string $slug): bool => isset($nomes[$slug]))
            ->map(fn (string $slug): array => [
                'slug' => $slug,
                'nome' => $nomes[$slug],
                'descricao' => self::DESCRICOES[$slug] ?? '',
            ])
            ->values()
            ->all();
    }
}

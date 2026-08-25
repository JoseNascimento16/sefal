<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\PermissaoLog;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Services\PermissaoService;
use App\Support\CatalogoFuncionalidades;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modo Gerente — a tela onde se decide quem entra onde.
 *
 * Mostra a matriz `tela × setor × ação` inteira, de uma vez: quem distribui
 * acesso precisa ver o conjunto para perceber o que ficou aberto demais. Cada
 * tela é salva por si, para que um ajuste num canto não regrave a casa toda.
 *
 * O administrador aparece na matriz travado, e não editável: o acesso total dele
 * é desvio no código ({@see PermissaoService}) e não uma linha que alguém possa
 * desmarcar — o primeiro efeito de desmarcá-la seria ninguém mais conseguir
 * abrir esta tela para remarcar.
 */
class ModoGerenteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Retaguarda/Sistema/ModoGerente', [
            'setores' => $this->setores(),
            'funcionalidades' => CatalogoFuncionalidades::itens(),
            'matriz' => $this->matriz(),
            'acoes' => self::ACOES_NA_TELA,
            // A tela diz em voz alta se o bloqueio está de fato ligado. Sem isso,
            // quem configura acha que tirou um acesso e não tirou — a matriz já
            // vale, mas o modo `log` só observa.
            'enforce' => (string) config('retaguarda.permissao_enforce', 'log'),
            'historico' => $this->historico(),
        ]);
    }

    /**
     * Nome e explicação de cada ação, para a tela não ter de adivinhar o que
     * "Habilitado" significa (e para as duas não discordarem).
     */
    private const ACOES_NA_TELA = [
        ['chave' => 'visivel', 'rotulo' => 'Vê', 'ajuda' => 'A tela aparece no menu e abre. É pré-requisito de todo o resto.'],
        ['chave' => 'habilitado', 'rotulo' => 'Opera', 'ajuda' => 'Pode usar as ações da tela e alterar o que já existe.'],
        ['chave' => 'apenas_leitura', 'rotulo' => 'Só consulta', 'ajuda' => 'Abre para olhar, sem incluir nem excluir.'],
        ['chave' => 'incluir', 'rotulo' => 'Inclui', 'ajuda' => 'Pode criar registro novo.'],
        ['chave' => 'excluir', 'rotulo' => 'Exclui', 'ajuda' => 'Pode excluir registro.'],
    ];

    public function salvar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'slug' => ['required', 'string', Rule::in(CatalogoFuncionalidades::slugs())],
            'matriz' => ['required', 'array'],
            'matriz.*.setor' => ['required', 'string'],
            'matriz.*.visivel' => ['boolean'],
            'matriz.*.habilitado' => ['boolean'],
            'matriz.*.apenas_leitura' => ['boolean'],
            'matriz.*.incluir' => ['boolean'],
            'matriz.*.excluir' => ['boolean'],
        ], [
            'slug.in' => 'Essa tela não está sob o controle de acesso.',
        ]);

        $slug = (string) $dados['slug'];
        $rotulo = CatalogoFuncionalidades::rotulo($slug) ?? $slug;
        $setoresValidos = array_keys((array) config('retaguarda.setores', []));

        foreach ($dados['matriz'] as $linha) {
            $setor = (string) $linha['setor'];

            // Setor inventado é ignorado em silêncio de propósito: a lista de
            // setores é fechada e vem do servidor, então um valor de fora só
            // chega por requisição forjada — não é erro a explicar ao usuário.
            // O administrador também não grava: ele é desvio, não concessão.
            if (! in_array($setor, $setoresValidos, true) || $setor === PermissaoService::SETOR_ADMIN) {
                continue;
            }

            PermissaoSetor::updateOrCreate(
                ['setor' => $setor, 'slug' => $slug],
                PermissaoService::normalizar($linha),
            );
        }

        PermissaoLog::create([
            'user_id' => $request->user()?->getKey(),
            // O nome vai gravado, e não só a chave: a conta pode ser renomeada
            // ou desligada, e o histórico tem de continuar legível.
            'user_nome' => $request->user()?->name,
            'funcionalidade_slug' => $slug,
            'descricao' => "Permissões da tela \"{$rotulo}\" alteradas.",
        ]);

        return back()->with(
            'flash.sucesso',
            "Permissões de \"{$rotulo}\" salvas. Quem já está no sistema vê a mudança na próxima navegação.",
        );
    }

    /**
     * A matriz como a tela precisa dela: tela => setor => ações.
     *
     * @return array<string, array<string, array<string, bool>>>
     */
    private function matriz(): array
    {
        $gravadas = PermissaoSetor::all()->keyBy(fn (PermissaoSetor $p): string => $p->slug.'|'.$p->setor);

        // Fora do laço: dentro, seria uma consulta de setores por tela.
        $setores = $this->setores();

        $matriz = [];

        foreach (CatalogoFuncionalidades::slugs() as $slug) {
            foreach ($setores as $setor) {
                $ehAdmin = $setor['slug'] === PermissaoService::SETOR_ADMIN;
                $linha = $gravadas->get($slug.'|'.$setor['slug']);

                $matriz[$slug][$setor['slug']] = [
                    'visivel' => $ehAdmin || (bool) $linha?->visivel,
                    'habilitado' => $ehAdmin || (bool) $linha?->habilitado,
                    'apenas_leitura' => ! $ehAdmin && (bool) $linha?->apenas_leitura,
                    'incluir' => $ehAdmin || (bool) $linha?->incluir,
                    'excluir' => $ehAdmin || (bool) $linha?->excluir,
                ];
            }
        }

        return $matriz;
    }

    /**
     * Setores na ordem do catálogo, com o administrador marcado como travado.
     *
     * @return list<array{slug: string, nome: string, travado: bool}>
     */
    private function setores(): array
    {
        $nomes = (array) config('retaguarda.setores', []);
        $doBanco = Setor::pluck('nome', 'slug');

        $setores = [];

        foreach ($nomes as $slug => $nome) {
            $setores[] = [
                'slug' => (string) $slug,
                'nome' => (string) $doBanco->get((string) $slug, $nome),
                'travado' => $slug === PermissaoService::SETOR_ADMIN,
            ];
        }

        return $setores;
    }

    /**
     * As últimas alterações de permissão. Curto de propósito: serve para
     * conferir o que acabou de ser mexido, não para auditoria completa — essa
     * está na tabela, que não é apagada.
     *
     * @return list<array{quando: string, quem: string, tela: string, descricao: string}>
     */
    private function historico(): array
    {
        $registros = [];

        foreach (PermissaoLog::query()->latest('id')->limit(15)->get() as $log) {
            $registros[] = [
                // Data em BR, como em todo o sistema — nunca a forma do banco.
                'quando' => $log->created_at?->format('d/m/Y H:i') ?? '',
                'quem' => $log->user_nome ?? 'Não identificado',
                'tela' => CatalogoFuncionalidades::rotulo($log->funcionalidade_slug) ?? $log->funcionalidade_slug,
                'descricao' => $log->descricao,
            ];
        }

        return $registros;
    }
}

<?php

namespace App\Http\Controllers\Aplicativo;

use App\Actions\Fortify\AutenticarPorMatricula;
use App\Http\Controllers\Controller;
use App\Models\DocumentoCampo;
use App\Models\Fiscalizacao;
use App\Models\Levantamento;
use App\Rules\ArquivoSeguro;
use App\Support\Aplicativo\AplicativoParaTela;
use App\Support\Aplicativo\CampoDoFiscal;
use App\Support\Aplicativo\LevantamentoDeCampo;
use App\Support\Aplicativo\RegistroDeCampo;
use App\Support\Aplicativo\ReservaDeNumeros;
use App\Support\Prototipo\RecomendacoesDoFiscal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O aplicativo do fiscal falando com o servidor (`/app/api/*`).
 *
 * Mesma origem da Retaguarda, mesma sessão: o login do aplicativo é o mesmo
 * usuário, a mesma senha e as mesmas recusas faladas da Retaguarda
 * ({@see AutenticarPorMatricula}). Tudo responde JSON.
 */
class AplicativoController extends Controller
{
    /** Matrícula e senha → sessão aberta e quem entrou. */
    public function entrar(Request $request): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:200'],
        ], [
            'login.required' => 'Informe a matrícula.',
            'password.required' => 'Informe a senha.',
        ]);

        $user = app(AutenticarPorMatricula::class)($request);
        $campo = new CampoDoFiscal($user);

        if (! $campo->temCampo()) {
            throw ValidationException::withMessages(['login' => [AplicativoParaTela::semEquipe($user)]]);
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(AplicativoParaTela::sessao($campo));
    }

    public function sair(Request $request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function sessao(Request $request): JsonResponse
    {
        return response()->json(AplicativoParaTela::sessao(new CampoDoFiscal($request->user())));
    }

    /**
     * O "kit" do turno: a fila da equipe, os registros do fiscal, o catálogo de
     * bairros e os números oficiais reservados para ele.
     */
    public function kit(Request $request): JsonResponse
    {
        $campo = new CampoDoFiscal($request->user());

        return response()->json([
            'demandas' => $campo->fila()->map(AplicativoParaTela::demanda(...))->values()->all(),
            'registros' => $campo->registros()->map(AplicativoParaTela::registro(...))->values()->all(),
            'bairros' => AplicativoParaTela::bairros(),
            'ambulantes' => AplicativoParaTela::ambulantes(),
            'incidencias' => AplicativoParaTela::incidencias(),
            'operacoes' => AplicativoParaTela::operacoes($campo),
            'equipesDaCidade' => AplicativoParaTela::equipesDaCidade(),
            'levantamentos' => AplicativoParaTela::levantamentos($campo),
            'numeros' => (new ReservaDeNumeros)->repor($request->user()),
            'servidorEm' => Date::now()->toIso8601String(),
        ]);
    }

    /**
     * Recebe um levantamento de ambulantes concluído. Idempotente pelo
     * `client_id` (201 no primeiro envio, 200 no reenvio). As linhas vêm como
     * JSON dentro do multipart, e a foto de cada linha em `fotos[<id da linha>]`.
     */
    public function levantar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'client_id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'rua' => ['required', 'string', 'max:200'],
            'bairro' => ['required', 'string', 'max:120'],
            'equipes' => ['required', 'array', 'min:1', 'max:20'],
            'equipes.*' => ['string', Rule::exists('equipes', 'codigo')],
            'iniciado_em' => ['nullable', 'date'],
            'linhas' => ['required', 'string', 'max:200000'],
            'fotos' => ['nullable', 'array', 'max:100'],
            'fotos.*' => ['file', 'max:10240', new ArquivoSeguro(['jpg', 'jpeg', 'png', 'webp'])],
        ], [
            'rua.required' => 'Informe a rua do levantamento.',
            'bairro.required' => 'Informe o bairro do levantamento.',
            'equipes.required' => 'Escolha ao menos uma equipe para o levantamento sair assinado.',
            'equipes.*.exists' => 'Uma das equipes escolhidas não existe mais no cadastro.',
            'fotos.*.max' => 'Cada foto pode ter no máximo 10 MB.',
        ]);

        $linhas = json_decode($dados['linhas'], true);

        if (! is_array($linhas) || $linhas === []) {
            throw ValidationException::withMessages(['linhas' => ['O levantamento chegou sem nenhum ambulante. Some ao menos um antes de concluir.']]);
        }

        $dados['linhas'] = Validator::make(['linhas' => $linhas], [
            'linhas' => ['array', 'max:100'],
            'linhas.*.id' => ['required', 'string', 'max:60'],
            'linhas.*.nome' => ['required', 'string', 'max:150'],
            'linhas.*.documento' => ['nullable', 'string', 'max:20'],
            'linhas.*.contato' => ['nullable', 'string', 'max:40'],
            'linhas.*.referencia' => ['nullable', 'string', 'max:150'],
            'linhas.*.atividade' => ['nullable', 'string', 'max:80'],
            'linhas.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'linhas.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'linhas.*.nome.required' => 'Há um ambulante sem nome no levantamento.',
        ])->validate()['linhas'];

        $ja = Levantamento::where('client_id', $dados['client_id'])->exists();
        $levantamento = (new LevantamentoDeCampo(new CampoDoFiscal($request->user())))
            ->gravar($dados, $request->file('fotos', []));

        return response()->json(['levantamento' => AplicativoParaTela::levantamento($levantamento)], $ja ? 200 : 201);
    }

    /** Repõe o estoque de números oficiais do fiscal. */
    public function numeros(Request $request): JsonResponse
    {
        return response()->json(['numeros' => (new ReservaDeNumeros)->repor($request->user())]);
    }

    /**
     * Recebe um registro despachado. Idempotente pelo `client_id`: o reenvio
     * devolve o registro já gravado (200), e o primeiro envio responde 201.
     */
    public function registrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'client_id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'demanda_id' => ['nullable', 'integer'],
            'desfecho' => ['required', Rule::in(Fiscalizacao::DESFECHOS)],
            'ocorrencias' => ['nullable', 'array', 'max:20'],
            'ocorrencias.*' => ['string', Rule::in(array_keys((array) config('prototipo_denuncias.ocorrencias_do_fiscal', [])))],
            'relato' => ['nullable', 'string', 'max:4000'],
            'ambulante' => ['nullable', 'string', 'max:255'],
            'ambulante_id' => ['nullable', 'integer', 'exists:ambulantes,id'],
            'retorno_em' => ['nullable', 'date_format:Y-m-d'],
            // A coordenada é a MEDIDA pelo aparelho. Sem ela, o registro diz por
            // quê — nunca chega um ponto inventado no lugar (dono, 14/10/2026).
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'precisao_m' => ['nullable', 'numeric', 'min:0', 'max:65000'],
            'sem_gps_motivo' => ['nullable', 'required_without:latitude', Rule::in(array_keys((array) config('prototipo_denuncias.motivos_sem_gps', [])))],
            // A operação em que a avulsa foi feita (tela da operação no aplicativo).
            'operacao_id' => ['nullable', 'integer'],
            'endereco' => ['nullable', 'string', 'max:200'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'aberta_em' => ['nullable', 'date'],
            'consideracoes' => ['nullable', 'string', 'max:4000'],
            'recomendacoes' => ['nullable', 'array'],
            'recomendacoes.*' => ['string', Rule::in(RecomendacoesDoFiscal::chaves())],
            'fotos' => ['nullable', 'array', 'max:10'],
            'fotos.*' => ['file', 'max:10240', new ArquivoSeguro(['jpg', 'jpeg', 'png', 'webp'])],
            'documento' => ['nullable', 'string', 'max:60000'],
        ], [
            'desfecho.required' => 'Escolha o desfecho da vistoria.',
            'sem_gps_motivo.required_without' => 'O registro chegou sem a localização e sem o motivo. Abra o registro de novo para o aparelho tentar o GPS.',
            'fotos.max' => 'No máximo 10 fotos por registro.',
            'fotos.*.max' => 'Cada foto pode ter no máximo 10 MB.',
        ]);

        $dados['documento'] = $this->documento($dados['documento'] ?? null);

        $campo = new CampoDoFiscal($request->user());
        $ja = Fiscalizacao::where('client_id', $dados['client_id'])->exists();
        $vistoria = (new RegistroDeCampo($campo))->gravar($dados, array_values($request->file('fotos', [])));

        return response()->json([
            'registro' => AplicativoParaTela::registro($vistoria),
            'numeros' => (new ReservaDeNumeros)->repor($request->user()),
        ], $ja ? 200 : 201);
    }

    /**
     * O documento vem como JSON dentro do multipart (o envio leva fotos junto).
     *
     * @return array<string, mixed>|null
     */
    private function documento(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $documento = json_decode($json, true);

        if (! is_array($documento)) {
            throw ValidationException::withMessages(['documento' => ['O documento lavrado chegou ilegível. Tente enviar de novo.']]);
        }

        return Validator::make($documento, [
            'tipo' => ['required', Rule::in(DocumentoCampo::TIPOS)],
            'numero' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'notificado' => ['nullable', 'string', 'max:200'],
            'documento_notificado' => ['nullable', 'string', 'max:20'],
            'prazo' => ['nullable', Rule::in(array_keys((array) config('prototipo_documentos_campo.prazos_np', [])))],
            'motivos' => ['nullable', 'array'],
            'motivos.*' => ['string', 'max:40'],
            'sancoes' => ['nullable', 'array'],
            'sancoes.*' => ['string', 'max:40'],
            'itens' => ['nullable', 'array', 'max:50'],
            'guarda_prazo' => ['nullable', 'string', 'max:40'],
            'guarda_destinacao' => ['nullable', 'string', 'max:60'],
            'dados' => ['nullable', 'array'],
        ], [
            'numero.regex' => 'O número do documento deve ter só algarismos.',
        ])->validate();
    }
}

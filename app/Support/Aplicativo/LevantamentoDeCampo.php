<?php

namespace App\Support\Aplicativo;

use App\Models\Equipe;
use App\Models\Levantamento;
use App\Models\LevantamentoLinha;
use App\Support\Protocolo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grava o levantamento de ambulantes que o aplicativo concluiu.
 *
 * Idempotente pelo `client_id`. Os ENCARREGADOS não vêm do aparelho: saem das
 * equipes escolhidas, do cadastro — o aplicativo só mostra o que o servidor
 * vai assinar, e um nome digitado divergiria do líder cadastrado.
 */
final class LevantamentoDeCampo
{
    public function __construct(private readonly CampoDoFiscal $campo) {}

    /**
     * @param  array{client_id: string, rua: string, bairro: string, equipes: list<string>, iniciado_em?: string|null, linhas: list<array<string, mixed>>}  $dados
     * @param  array<string, UploadedFile>  $fotos  a foto de cada linha, pela chave local da linha
     */
    public function gravar(array $dados, array $fotos): Levantamento
    {
        $existente = Levantamento::where('client_id', $dados['client_id'])->first();

        if ($existente !== null) {
            return $existente;
        }

        $equipes = Equipe::with('lider')->whereIn('codigo', $dados['equipes'])->orderBy('codigo')->get();
        $agora = Date::now();

        return DB::transaction(function () use ($dados, $fotos, $equipes, $agora) {
            $levantamento = Levantamento::create([
                'protocolo' => Protocolo::proximo('LEV', $agora, Levantamento::class),
                'client_id' => $dados['client_id'],
                'user_id' => $this->campo->user->id,
                'rua' => $dados['rua'],
                'bairro' => $dados['bairro'],
                'equipes' => $equipes->pluck('codigo')->all(),
                'encarregados' => $equipes->map(fn (Equipe $e) => $e->nomeDoLider())->unique()->values()->all(),
                'iniciado_em' => isset($dados['iniciado_em'])
                    ? Date::parse($dados['iniciado_em'])->setTimezone(config('app.timezone'))
                    : $agora,
                'concluido_em' => $agora,
            ]);

            foreach (array_values($dados['linhas']) as $i => $linha) {
                $foto = $fotos[(string) ($linha['id'] ?? '')] ?? null;

                LevantamentoLinha::create([
                    'levantamento_id' => $levantamento->id,
                    // A numeração é a do papel, na ordem em que o fiscal somou.
                    'item' => str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'nome' => $linha['nome'],
                    'documento' => $linha['documento'] ?? null,
                    'contato' => $linha['contato'] ?? null,
                    'referencia' => $linha['referencia'] ?? null,
                    'atividade' => $linha['atividade'] ?? null,
                    'latitude' => $linha['latitude'] ?? null,
                    'longitude' => $linha['longitude'] ?? null,
                    'foto' => $foto?->storeAs(
                        'levantamentos/'.$levantamento->id,
                        Str::uuid().'.'.strtolower($foto->getClientOriginalExtension() ?: 'jpg'),
                        RegistroDeCampo::DISCO,
                    ),
                ]);
            }

            return $levantamento->fresh('linhas') ?? $levantamento;
        });
    }
}

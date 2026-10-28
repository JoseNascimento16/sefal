<?php

use App\Models\Area;
use App\Models\CicloDeFiscalizacao;
use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\Setor;
use App\Models\User;
use App\Support\Aplicativo\ReservaDeNumeros;
use App\Support\CiclosDeFiscalizacao;
use App\Support\DecisaoDaChefia;
use App\Support\PrazosDaNotificacao;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| O retorno da Notificação Preliminar (dono, 28/10/2026 — "Decisão A")
|--------------------------------------------------------------------------
|
| A notificação deixa o caso com o notificado: a demanda (ou a ronda) fica
| AGUARDANDO REGULARIZAÇÃO na fila do aplicativo, sem passar pelo líder. No fim
| do prazo a equipe registra o RETORNO, que vai ao líder. Prazo vencido sem
| retorno vira RETORNO VENCIDO, na mesa do líder. E a ronda que volta à fila —
| pela notificação ou porque o líder mandou a equipe voltar — chega ao
| aplicativo como Fiscalização sem demanda.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    Storage::fake('local');
});

/** @return array{fiscal: User, equipe: Equipe} */
function campoDoRetornoDaNotificacao(string $codigo = 'R1'): array
{
    $area = Area::create(['nome' => 'Área '.$codigo, 'regiao' => 'Orla', 'recorte' => 'bairros', 'turno' => 'Diurno', 'ativa' => true]);
    $equipe = Equipe::create(['codigo' => $codigo, 'nome' => 'Equipe '.$codigo, 'area_id' => $area->id, 'turno' => 'Turno manhã', 'ativa' => true]);
    $fiscal = User::factory()->create(['login' => 'fiscal-'.strtolower($codigo), 'admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());
    $equipe->fiscais()->attach($fiscal->id);

    return ['fiscal' => $fiscal->fresh(), 'equipe' => $equipe];
}

function demandaDoRetornoDaNotificacao(Equipe $equipe): Demanda
{
    $demanda = Demanda::create([
        'protocolo' => 'DEM-RET-'.Demanda::count(), 'canal' => Demanda::CANAL_FALA_SALVADOR, 'entrada' => Demanda::ENTRADA_BALCAO,
        'recebida_em' => Date::now()->subDay(), 'prazo_em' => Date::now()->addDays(9), 'assunto' => 'Barraca no passeio',
        'relato' => 'Barraca ocupando a calçada.', 'logradouro' => 'Rua A', 'bairro' => 'Pituba',
        'situacao' => Demanda::DIRECIONADA_AOS_FISCAIS, 'equipe_id' => $equipe->id, 'area_id' => $equipe->area_id,
    ]);

    CiclosDeFiscalizacao::abrirParaDemanda($demanda);

    return $demanda;
}

/** @param  array<string, mixed>  $extra */
function envioDoRetornoDaNotificacao(array $extra = []): array
{
    return [
        'client_id' => 'r-'.uniqid(),
        'desfecho' => Fiscalizacao::REGULARIZADO_NO_LOCAL,
        'relato' => 'Conferido no ponto.',
        'latitude' => -12.98,
        'longitude' => -38.45,
        'endereco' => 'Rua A, 10',
        'bairro' => 'Pituba',
        ...$extra,
    ];
}

/** O registro com Notificação Preliminar de 48 horas, com número reservado ao fiscal. */
function notificacaoDoRetorno(User $fiscal, array $extra = []): array
{
    $numero = (new ReservaDeNumeros)->repor($fiscal)['np'][0];

    return envioDoRetornoDaNotificacao([
        'desfecho' => Fiscalizacao::NOTIFICACAO_EMITIDA,
        'documento' => json_encode(['tipo' => 'np', 'numero' => $numero, 'notificado' => 'João', 'prazo' => '48h', 'motivos' => ['dam'], 'dados' => ['assinaturas' => []]]),
        ...$extra,
    ]);
}

/** @return array<string, mixed>|null */
function itemDaFilaDoRetorno(User $fiscal, string $id): ?array
{
    return collect(test()->actingAs($fiscal)->getJson(route('aplicativo.kit'))->assertOk()->json('demandas'))
        ->firstWhere('id', $id);
}

it('a Notificação Preliminar deixa a demanda aguardando regularização na fila, sem passar pelo líder', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoRetornoDaNotificacao();
    $demanda = demandaDoRetornoDaNotificacao($equipe);

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), notificacaoDoRetorno($fiscal, ['demanda_id' => $demanda->id]))->assertCreated();

    $notificacao = Fiscalizacao::firstOrFail();
    $ciclo = CicloDeFiscalizacao::findOrFail($notificacao->ciclo_id);

    expect($demanda->fresh()->situacao)->toBe(Demanda::AGUARDANDO_REGULARIZACAO)
        ->and($notificacao->situacao)->toBe(Fiscalizacao::PRAZO_CORRENDO)
        ->and($ciclo->aDecidir())->toBeFalse()
        ->and($ciclo->desfechoAtual())->toBe('Aguardando regularização');

    $item = itemDaFilaDoRetorno($fiscal, 'den-'.$demanda->id);

    // O prazo do cartão é o da notificação (48 horas), não o do atendimento (9 dias).
    expect($item['situacao'])->toBe(Demanda::AGUARDANDO_REGULARIZACAO)
        ->and($item['aguardaLider'])->toBeFalse()
        ->and($item['prazoBr'])->toBe(Date::today()->addDays(2)->format('d/m/Y'));
});

it('no retorno, só cabem os desfechos de retorno — e o retorno vai ao líder', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoRetornoDaNotificacao();
    $demanda = demandaDoRetornoDaNotificacao($equipe);
    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), notificacaoDoRetorno($fiscal, ['demanda_id' => $demanda->id]))->assertCreated();

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), envioDoRetornoDaNotificacao(['demanda_id' => $demanda->id]))
        ->assertStatus(422)
        ->assertJsonPath('errors.desfecho.0', fn (string $m) => str_contains($m, 'o registro é o retorno'));

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), envioDoRetornoDaNotificacao([
        'demanda_id' => $demanda->id, 'desfecho' => Fiscalizacao::SITUACAO_MANTIDA,
    ]))->assertCreated();

    [$notificacao, $retorno] = Fiscalizacao::orderBy('id')->get()->all();
    $ciclo = CicloDeFiscalizacao::with('vistorias')->findOrFail($retorno->ciclo_id);

    expect($retorno->ciclo_id)->toBe($notificacao->ciclo_id)
        ->and($retorno->situacao)->toBe(Fiscalizacao::AGUARDANDO_LEITURA)
        ->and($notificacao->fresh()->situacao)->toBe(Fiscalizacao::RETORNO_REGISTRADO)
        ->and($demanda->fresh()->situacao)->toBe(Demanda::EM_CAMPO)
        ->and($ciclo->aDecidir())->toBeTrue()
        ->and($ciclo->vistoriaPendente()?->id)->toBe($retorno->id);
});

it('prazo vencido sem retorno vira RETORNO VENCIDO, na mesa do líder, e o retorno segue possível', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoRetornoDaNotificacao();
    $demanda = demandaDoRetornoDaNotificacao($equipe);
    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), notificacaoDoRetorno($fiscal, ['demanda_id' => $demanda->id]))->assertCreated();

    // O dia do prazo ainda é dia de voltar: não vence.
    expect(PrazosDaNotificacao::vencer(Date::now()->addDays(2)))->toBe(0)
        ->and($demanda->fresh()->situacao)->toBe(Demanda::AGUARDANDO_REGULARIZACAO);

    $this->travelTo(Date::now()->addDays(3));

    expect(PrazosDaNotificacao::vencer())->toBe(1)
        // Idempotente: o que já venceu não é tocado de novo.
        ->and(PrazosDaNotificacao::vencer())->toBe(0);

    $ciclo = CicloDeFiscalizacao::with('vistorias')->firstOrFail();

    expect($demanda->fresh()->situacao)->toBe(Demanda::RETORNO_VENCIDO)
        ->and($demanda->tramites()->reorder('ordem', 'desc')->value('acao'))->toContain('alerta ao líder')
        ->and($ciclo->retornoVencido())->toBeTrue()
        ->and($ciclo->aDecidir())->toBeTrue()
        ->and($ciclo->desfechoAtual())->toBe('Retorno vencido')
        ->and(itemDaFilaDoRetorno($fiscal, 'den-'.$demanda->id)['situacao'])->toBe(Demanda::RETORNO_VENCIDO);

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), envioDoRetornoDaNotificacao([
        'demanda_id' => $demanda->id, 'desfecho' => Fiscalizacao::REGULARIZADO_APOS_NOTIFICACAO,
    ]))->assertCreated();

    expect($ciclo->fresh('vistorias')->retornoVencido())->toBeFalse()
        ->and($demanda->fresh()->situacao)->toBe(Demanda::EM_CAMPO);
});

it('a Notificação lavrada numa RONDA volta à fila como Fiscalização sem demanda, e o retorno entra nela', function () {
    ['fiscal' => $fiscal] = campoDoRetornoDaNotificacao();

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), notificacaoDoRetorno($fiscal))->assertCreated();
    $ciclo = CicloDeFiscalizacao::firstOrFail();

    $item = itemDaFilaDoRetorno($fiscal, 'fsc-'.$ciclo->id);

    expect($ciclo->demanda_id)->toBeNull()
        ->and($item)->not->toBeNull()
        ->and($item['origem'])->toBe('ronda')
        ->and($item['cicloId'])->toBe($ciclo->id)
        ->and($item['situacao'])->toBe(Demanda::AGUARDANDO_REGULARIZACAO)
        ->and($item['documento']['tipo'])->toBe('np');

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), envioDoRetornoDaNotificacao([
        'ciclo_id' => $ciclo->id, 'desfecho' => Fiscalizacao::SITUACAO_MANTIDA,
    ]))->assertCreated();

    $retorno = Fiscalizacao::latest('id')->firstOrFail();

    expect($retorno->ciclo_id)->toBe($ciclo->id)
        ->and($retorno->situacao)->toBe(Fiscalizacao::AGUARDANDO_LEITURA)
        ->and(CicloDeFiscalizacao::count())->toBe(1)
        // Feito o retorno, a ronda sai da fila: agora é o líder quem decide.
        ->and(itemDaFilaDoRetorno($fiscal, 'fsc-'.$ciclo->id))->toBeNull();
});

it('"mandar a equipe voltar" numa ronda põe a Fiscalização na fila do aplicativo', function () {
    ['fiscal' => $fiscal] = campoDoRetornoDaNotificacao();

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), envioDoRetornoDaNotificacao())->assertCreated();
    $ciclo = CicloDeFiscalizacao::firstOrFail();

    expect(itemDaFilaDoRetorno($fiscal, 'fsc-'.$ciclo->id))->toBeNull();

    (new DecisaoDaChefia(User::factory()->create()))
        ->pedirNovaVistoria([Fiscalizacao::firstOrFail()->id], 'Voltar ao ponto: o ambulante remontou a barraca à tarde.');

    $item = itemDaFilaDoRetorno($fiscal, 'fsc-'.$ciclo->id);

    expect($item['situacao'])->toBe(Demanda::DIRECIONADA_AOS_FISCAIS)
        ->and($item['detalhe'])->toContain('remontou a barraca')
        ->and($ciclo->fresh('vistorias')->desfechoAtual())->toBe('Nova vistoria em campo');

    // Na volta determinada, a vistoria é comum: não cabe desfecho de retorno.
    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), envioDoRetornoDaNotificacao([
        'ciclo_id' => $ciclo->id, 'desfecho' => Fiscalizacao::SITUACAO_MANTIDA,
    ]))->assertStatus(422)->assertJsonValidationErrors('desfecho');

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), envioDoRetornoDaNotificacao([
        'ciclo_id' => $ciclo->id, 'desfecho' => Fiscalizacao::NADA_ENCONTRADO,
    ]))->assertCreated();

    expect(Fiscalizacao::latest('id')->value('ciclo_id'))->toBe($ciclo->id)
        ->and(itemDaFilaDoRetorno($fiscal, 'fsc-'.$ciclo->id))->toBeNull();
});

it('a Fiscalização de outra equipe é recusada nominalmente, sem gravar nada', function () {
    ['fiscal' => $dono] = campoDoRetornoDaNotificacao('R1');
    ['fiscal' => $outro] = campoDoRetornoDaNotificacao('R2');

    $this->actingAs($dono)->postJson(route('aplicativo.fiscalizacoes'), notificacaoDoRetorno($dono))->assertCreated();
    $ciclo = CicloDeFiscalizacao::firstOrFail();

    $this->actingAs($outro)->postJson(route('aplicativo.fiscalizacoes'), envioDoRetornoDaNotificacao([
        'ciclo_id' => $ciclo->id, 'desfecho' => Fiscalizacao::REGULARIZADO_APOS_NOTIFICACAO,
    ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.ciclo_id.0', fn (string $m) => str_contains($m, 'não está mais na fila da sua equipe'));

    expect(Fiscalizacao::count())->toBe(1)
        ->and(itemDaFilaDoRetorno($outro, 'fsc-'.$ciclo->id))->toBeNull();
});

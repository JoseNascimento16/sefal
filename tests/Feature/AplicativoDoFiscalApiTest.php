<?php

use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\NumeroReservado;
use App\Models\Setor;
use App\Models\User;
use App\Support\Aplicativo\ReservaDeNumeros;
use App\Support\CiclosDeFiscalizacao;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| O aplicativo do fiscal falando com o servidor (dono, 13/10/2026)
|--------------------------------------------------------------------------
|
| Login real, a fila da equipe, a reserva de números de NP/AA e a gravação do
| registro despachado — que vira vistoria esperando a leitura do líder.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    Storage::fake('local');
});

/** @return array{fiscal: User, equipe: Equipe, area: Area} */
function campoDoAplicativo(string $codigo = 'Z1'): array
{
    $area = Area::create(['nome' => 'Área '.$codigo, 'regiao' => 'Orla', 'recorte' => 'bairros', 'turno' => 'Diurno', 'ativa' => true]);
    $equipe = Equipe::create(['codigo' => $codigo, 'nome' => 'Equipe '.$codigo, 'area_id' => $area->id, 'turno' => 'Turno manhã', 'ativa' => true]);
    $fiscal = User::factory()->create(['login' => 'fiscal-'.strtolower($codigo), 'admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());
    $equipe->fiscais()->attach($fiscal->id);

    return ['fiscal' => $fiscal->fresh(), 'equipe' => $equipe, 'area' => $area];
}

function demandaDoAplicativo(Equipe $equipe, string $situacao = Demanda::DIRECIONADA_AOS_FISCAIS): Demanda
{
    $demanda = Demanda::create([
        'protocolo' => 'DEM-APP-'.Demanda::count(), 'canal' => Demanda::CANAL_FALA_SALVADOR, 'entrada' => Demanda::ENTRADA_BALCAO,
        'recebida_em' => Date::now()->subDay(), 'prazo_em' => Date::now()->addDays(3), 'assunto' => 'Barraca no passeio',
        'relato' => 'Barraca ocupando a calçada.', 'logradouro' => 'Rua A', 'bairro' => 'Pituba',
        'situacao' => $situacao, 'equipe_id' => $equipe->id, 'area_id' => $equipe->area_id,
    ]);

    CiclosDeFiscalizacao::abrirParaDemanda($demanda);

    return $demanda;
}

/** @param  array<string, mixed>  $extra */
function registroDoAplicativo(array $extra = []): array
{
    return [
        'client_id' => 'c-'.uniqid(),
        'desfecho' => Fiscalizacao::REGULARIZADO_NO_LOCAL,
        'relato' => 'Orientado, desmontou na hora.',
        'ocorrencias' => ['passeio'],
        'latitude' => -12.98,
        'longitude' => -38.45,
        'precisao_m' => 7.6,
        'endereco' => 'Rua A, 10',
        'bairro' => 'Pituba',
        'consideracoes' => 'Sem reincidência.',
        'recomendacoes' => ['nada'],
        ...$extra,
    ];
}

// ── Porta ────────────────────────────────────────────────────────────────────

it('entra com matrícula e senha e devolve o fiscal e a equipe dele', function () {
    ['fiscal' => $fiscal] = campoDoAplicativo();

    $this->postJson(route('aplicativo.entrar'), ['login' => $fiscal->login, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('fiscal.matricula', $fiscal->login)
        ->assertJsonPath('equipe.codigo', 'Z1')
        ->assertJsonPath('equipes', ['Z1']);

    $this->assertAuthenticatedAs($fiscal);
});

it('recusa senha errada com a mesma frase da Retaguarda', function () {
    ['fiscal' => $fiscal] = campoDoAplicativo();

    $this->postJson(route('aplicativo.entrar'), ['login' => $fiscal->login, 'password' => 'errada'])
        ->assertStatus(422)
        ->assertJsonPath('errors.login.0', 'Matrícula ou senha inválida. Confira os dados e tente novamente.');

    $this->assertGuest();
});

it('recusa quem não está em equipe nenhuma dizendo o porquê', function () {
    $semEquipe = User::factory()->create(['login' => 'sem-equipe', 'ativo' => true]);

    $this->postJson(route('aplicativo.entrar'), ['login' => 'sem-equipe', 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonPath('errors.login.0', fn (string $m) => str_contains($m, 'não está em nenhuma equipe'));

    $this->assertGuest();
    expect($semEquipe->exists)->toBeTrue();
});

it('sem sessão, as rotas do aplicativo respondem 401 com a frase de entrar de novo', function () {
    $this->getJson(route('aplicativo.kit'))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Sua sessão terminou. Entre de novo com matrícula e senha.');
});

// ── Fila ─────────────────────────────────────────────────────────────────────

it('a fila traz só as demandas na mão da equipe do fiscal', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoAplicativo('Z1');
    ['equipe' => $outra] = campoDoAplicativo('Z2');

    $minha = demandaDoAplicativo($equipe);
    demandaDoAplicativo($outra);
    demandaDoAplicativo($equipe, Demanda::ENCAMINHADA_AO_LIDER);

    $kit = $this->actingAs($fiscal)->getJson(route('aplicativo.kit'))->assertOk()->json();

    expect(collect($kit['demandas'])->pluck('protocolo')->all())->toBe([$minha->protocolo])
        ->and($kit['demandas'][0]['origem'])->toBe('fala-salvador')
        ->and($kit['demandas'][0]['situacao'])->toBe(Demanda::DIRECIONADA_AOS_FISCAIS)
        ->and($kit['numeros']['np'])->toHaveCount(ReservaDeNumeros::ESTOQUE)
        ->and($kit['numeros']['aa'])->toHaveCount(ReservaDeNumeros::ESTOQUE);
});

// ── Registro ─────────────────────────────────────────────────────────────────

it('o registro despachado vira vistoria aguardando o líder, com fotos, e move a demanda', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoAplicativo();
    $demanda = demandaDoAplicativo($equipe);

    $resposta = $this->actingAs($fiscal)->post(route('aplicativo.fiscalizacoes'), registroDoAplicativo([
        'demanda_id' => $demanda->id,
        'fotos' => [UploadedFile::fake()->image('ponto.jpg')],
    ]), ['Accept' => 'application/json'])->assertCreated();

    $vistoria = Fiscalizacao::firstOrFail();

    expect($vistoria->situacao)->toBe(Fiscalizacao::AGUARDANDO_LEITURA)
        ->and($vistoria->fiscal_id)->toBe($fiscal->id)
        ->and($vistoria->equipe_id)->toBe($equipe->id)
        ->and($vistoria->ciclo_id)->not->toBeNull()
        ->and($vistoria->relato)->toBe('Orientado, desmontou na hora.')
        ->and($vistoria->ocorrencias)->toBe(['passeio'])
        ->and($vistoria->precisao_m)->toBe(8)
        ->and($vistoria->recomendacoes()->pluck('chave')->all())->toBe(['nada'])
        ->and($vistoria->fotos)->toHaveCount(1)
        ->and($demanda->fresh()->situacao)->toBe(Demanda::EM_CAMPO);

    Storage::disk('local')->assertExists($vistoria->fotos->first()->caminho);

    $passo = DemandaTramite::where('demanda_id', $demanda->id)->latest('ordem')->firstOrFail();
    expect($passo->fiscalizacao_id)->toBe($vistoria->id)
        ->and($passo->papel)->toBe(DemandaTramite::PAPEL_FISCAL);

    $resposta->assertJsonPath('registro.protocolo', $vistoria->protocolo)
        ->assertJsonPath('registro.envio', 'enviado');

    // Na fila, a demanda já não é ato do fiscal: espera o líder.
    $kit = $this->actingAs($fiscal)->getJson(route('aplicativo.kit'))->json();
    expect($kit['demandas'][0]['aguardaLider'])->toBeTrue()
        ->and($kit['registros'][0]['protocolo'])->toBe($vistoria->protocolo);
});

it('o reenvio do mesmo registro não duplica a vistoria', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoAplicativo();
    $demanda = demandaDoAplicativo($equipe);
    $dados = registroDoAplicativo(['demanda_id' => $demanda->id]);

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), $dados)->assertCreated();
    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), $dados)->assertOk();

    expect(Fiscalizacao::count())->toBe(1)
        ->and(DemandaTramite::where('demanda_id', $demanda->id)->where('papel', DemandaTramite::PAPEL_FISCAL)->count())->toBe(1);
});

it('registro avulso, sem demanda, abre a própria Fiscalização na equipe', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoAplicativo();

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), registroDoAplicativo())->assertCreated();

    $vistoria = Fiscalizacao::firstOrFail();
    expect($vistoria->origem)->toBe(Fiscalizacao::ORIGEM_AVULSA)
        ->and($vistoria->ciclo->equipe_id)->toBe($equipe->id);
});

it('recusa vistoria em demanda de outra equipe, dizendo de quem ela é', function () {
    ['fiscal' => $fiscal] = campoDoAplicativo('Z1');
    ['equipe' => $outra] = campoDoAplicativo('Z2');
    $alheia = demandaDoAplicativo($outra);

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), registroDoAplicativo(['demanda_id' => $alheia->id]))
        ->assertStatus(422)
        ->assertJsonPath('errors.demanda_id.0', fn (string $m) => str_contains($m, 'não está mais com a sua equipe'));

    expect(Fiscalizacao::count())->toBe(0);
});

// ── Documento e números ──────────────────────────────────────────────────────

it('desfecho com Notificação exige o documento, e o número tem de ser reservado ao fiscal', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoAplicativo();
    $demanda = demandaDoAplicativo($equipe);
    $np = ['desfecho' => Fiscalizacao::NOTIFICACAO_EMITIDA, 'demanda_id' => $demanda->id];

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), registroDoAplicativo($np))
        ->assertStatus(422)->assertJsonValidationErrors('documento');

    $alheio = json_encode(['tipo' => 'np', 'numero' => '999999', 'notificado' => 'João', 'prazo' => '48h']);
    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), registroDoAplicativo([...$np, 'documento' => $alheio]))
        ->assertStatus(422)
        ->assertJsonPath('errors.documento.0', fn (string $m) => str_contains($m, 'não foi reservado para você'));

    $numero = (new ReservaDeNumeros)->repor($fiscal)['np'][0];
    $documento = json_encode(['tipo' => 'np', 'numero' => $numero, 'notificado' => 'João', 'prazo' => '48h', 'motivos' => ['dam'], 'dados' => ['assinaturas' => []]]);

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), registroDoAplicativo([...$np, 'documento' => $documento]))
        ->assertCreated()
        ->assertJsonPath('registro.documento', 'NP '.$numero);

    $vistoria = Fiscalizacao::firstOrFail();
    expect($vistoria->documento->prazo_ate->toDateString())->toBe(Date::now()->addDays(2)->toDateString())
        ->and(NumeroReservado::where('numero', $numero)->value('fiscalizacao_id'))->toBe($vistoria->id);
});

it('a reserva nunca entrega o mesmo número a dois fiscais e repõe o estoque depois do uso', function () {
    ['fiscal' => $um] = campoDoAplicativo('Z1');
    ['fiscal' => $dois] = campoDoAplicativo('Z2');

    $reserva = new ReservaDeNumeros;
    $deUm = $reserva->repor($um)['np'];
    $deDois = $reserva->repor($dois)['np'];

    expect(array_intersect($deUm, $deDois))->toBe([])
        ->and((int) $deUm[0])->toBe(ReservaDeNumeros::INICIO['np']);

    NumeroReservado::where('numero', $deUm[0])->update(['usado_em' => Date::now()]);

    expect($reserva->repor($um)['np'])->toHaveCount(ReservaDeNumeros::ESTOQUE)
        ->not->toContain($deUm[0]);
});

// ── De volta à Retaguarda ────────────────────────────────────────────────────

it('o líder vê na Retaguarda a vistoria vinda do aplicativo e, mandando voltar, a demanda volta à fila do fiscal', function () {
    ['fiscal' => $fiscal, 'equipe' => $equipe] = campoDoAplicativo();
    $demanda = demandaDoAplicativo($equipe);
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), registroDoAplicativo(['demanda_id' => $demanda->id]))
        ->assertCreated();
    $vistoria = Fiscalizacao::firstOrFail();

    $props = $this->actingAs($admin)->get(route('retaguarda.fiscalizacoes.index'))->assertOk()->viewData('page')['props'];
    expect(json_encode($props['fiscalizacoes'], JSON_UNESCAPED_UNICODE))->toContain($vistoria->protocolo);

    $this->actingAs($admin)->post(route('retaguarda.fiscalizacoes.nova-vistoria'), [
        'ids' => [$vistoria->ciclo_id],
        'justificativa' => 'Voltar no fim de semana, quando a barraca monta.',
    ])->assertSessionHasNoErrors();

    $fila = collect($this->actingAs($fiscal)->getJson(route('aplicativo.kit'))->json('demandas'));
    expect($fila->firstWhere('protocolo', $demanda->protocolo))
        ->situacao->toBe(Demanda::DIRECIONADA_AOS_FISCAIS)
        ->aguardaLider->toBeFalse();
});

it('a hora em UTC que o aparelho manda é gravada no fuso de Salvador', function () {
    ['fiscal' => $fiscal] = campoDoAplicativo();
    Date::setTestNow(Date::parse('2026-10-13 12:30', config('app.timezone')));

    $this->actingAs($fiscal)->postJson(route('aplicativo.fiscalizacoes'), registroDoAplicativo([
        'aberta_em' => '2026-10-13T15:14:00.000Z',
    ]))->assertCreated()->assertJsonPath('registro.hora', '12:14');

    Date::setTestNow();
});

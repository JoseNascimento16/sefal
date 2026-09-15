<?php

use App\Models\Ambulante;
use App\Models\Fiscalizacao;
use App\Models\LocalizacaoAmbulante;
use App\Models\User;
use App\Support\Apresentacao\MapaDaCidade;
use Database\Seeders\DemonstracaoSeeder;
use Database\Seeders\EstruturaSeeder;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Os mapas depois da consolidação: eles desenham o que EXISTE
|--------------------------------------------------------------------------
|
| Antes, os pontos eram inventados por um gerador — o pino era um desenho, e
| clicar nele não levava a lugar nenhum. Agora cada pino é um `ambulante` com
| trilha. O que estes testes protegem é justamente a diferença:
|
| 1. **todo pino tem cadastro atrás.** Um pino sem `ambulante_id` é um desenho, e
|    desenho não se fiscaliza;
| 2. **o vazio é resposta.** Sem registro hoje, "registros de hoje" é ZERO — e não
|    um número plausível. Quem olha o mapa para decidir para onde mandar gente
|    precisa distinguir "não houve trabalho" de "houve e eu não sei";
| 3. **o pino fica no ÚLTIMO ponto conhecido.** O ambulante se move, e mostrar o
|    primeiro ponto mandaria a equipe ao endereço de seis meses atrás.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    $this->seed(EstruturaSeeder::class);
    $this->seed(DemonstracaoSeeder::class);
});

it('desenha um pino por ambulante, e todo pino tem cadastro atrás', function () {
    $mapa = MapaDaCidade::aoVivo();

    expect($mapa['pontos'])->not->toBeEmpty();

    $comCadastro = Ambulante::has('ultimaLocalizacao')->count();

    expect($mapa['pontos'])->toHaveCount($comCadastro);

    foreach ($mapa['pontos'] as $ponto) {
        // O que faz o pino ser clicável: ele aponta uma linha de verdade.
        expect(Ambulante::whereKey($ponto['ambulante_id'])->exists())->toBeTrue()
            ->and($ponto['lat'])->toBeFloat()
            ->and($ponto['lng'])->toBeFloat();
    }
});

it('põe o pino no ÚLTIMO ponto conhecido, não no primeiro', function () {
    $ambulante = Ambulante::has('ultimaLocalizacao')->firstOrFail();

    LocalizacaoAmbulante::create([
        'ambulante_id' => $ambulante->id,
        'latitude' => -12.9111111,
        'longitude' => -38.4222222,
        'fonte' => LocalizacaoAmbulante::FONTE_FISCALIZACAO,
        'bairro' => 'Itapuã',
        'registrada_em' => now(),
    ]);

    $ponto = collect(MapaDaCidade::aoVivo()['pontos'])
        ->firstWhere('ambulante_id', $ambulante->id);

    expect($ponto['lat'])->toBe(-12.9111111)
        ->and($ponto['bairro'])->toBe('Itapuã');
});

it('conta ZERO registros de hoje quando ninguém registrou nada hoje', function () {
    // Empurra tudo para ontem: o mapa tem de dizer que hoje não houve trabalho.
    Fiscalizacao::query()->update(['concluida_em' => now()->subDays(3)]);

    expect(MapaDaCidade::aoVivo()['registros'])->toBe([]);
});

it('não inventa fiscal em campo: a lista é quem tem vistoria ABERTA agora', function () {
    $mapa = MapaDaCidade::aoVivo();

    expect($mapa['fiscais'])->toHaveCount(Fiscalizacao::where('situacao', Fiscalizacao::EM_CAMPO)
        ->whereNotNull('latitude')->count());
});

it('o mapa de calor sai das fiscalizações com GPS, e só de bairro que tem coordenada', function () {
    $calor = MapaDaCidade::calor();

    expect($calor['bairros'])->not->toBeEmpty();

    $bairrosNoMapa = array_column($calor['bairros'], 'bairro');

    foreach ($calor['pontos'] as [$indice, $lat, $lng, $dias, $noturno]) {
        expect($bairrosNoMapa)->toHaveKey($indice)
            ->and($dias)->toBeLessThanOrEqual($calor['janela_em_dias'])
            ->and($noturno)->toBeIn([0, 1]);
    }

    // Nenhum ponto a mais do que fiscalização com GPS existe: o mapa não infla.
    expect(count($calor['pontos']))
        ->toBeLessThanOrEqual(Fiscalizacao::whereNotNull('latitude')->count());
});

it('as duas telas abrem', function () {
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);

    $this->actingAs($admin)->get('/retaguarda/mapa')->assertOk();
    $this->actingAs($admin)->get('/retaguarda/mapa-de-calor')->assertOk();
});

it('o seeder de demonstração se recusa a rodar em produção', function () {
    app()['env'] = 'production';

    /*
     * Chamado direto, e não por `$this->seed()`: em produção o comando pergunta
     * "tem certeza?" antes de qualquer coisa, e o teste morreria na pergunta sem
     * nunca chegar à guarda que ele existe para provar.
     */
    (new DemonstracaoSeeder)->run();
})->throws(RuntimeException::class, 'não roda em produção');

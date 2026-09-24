<?php

use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\Setor;
use App\Models\User;
use App\Services\ESalvador\ESalvador;
use App\Services\ESalvador\EscritaNaoLiberada;
use App\Support\Estrutura;
use App\Support\RetornoAoCanal;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O retorno ao canal — a ESTRUTURA do ato, sem escrever no e-Salvador
|--------------------------------------------------------------------------
|
| Concluído o trabalho, o Chefe de Setor devolve o resultado a quem pediu:
| responde no processo do e-Salvador, ou abre um processo para a avulsa. A API é
| de PRODUÇÃO e a escrita está proibida (decisão do dono, 15/09 e 23/09/2026):
| o ato fica REGISTRADO aqui e nada sai para a rede. Estes testes protegem as
| duas metades — o registro que existe e a rede que NÃO é tocada.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    Http::fake();
});

function pessoaCom(string $slug): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', $slug)->firstOrFail());

    return $u->fresh();
}

function concluida(array $extra = []): Demanda
{
    $area = Area::firstOrCreate(['nome' => 'Área 5'], ['regiao' => 'Orla']);
    $equipe = Equipe::firstOrCreate(['codigo' => 'C1'], ['nome' => 'Equipe C1', 'area_id' => $area->id]);
    Estrutura::esquecer();

    return Demanda::create(array_merge([
        'protocolo' => 'DEM-0001',
        'canal' => Demanda::CANAL_E_SALVADOR,
        'entrada' => Demanda::ENTRADA_INTEGRACAO,
        'numero_origem' => '215.5382.000123/2026',
        'recebida_em' => Date::now()->subDays(10),
        'assunto' => 'Mesas na calçada',
        'bairro' => 'Costa Azul',
        'situacao' => Demanda::CONCLUIDA,
        'concluida_em' => Date::now()->subDay(),
        'area_id' => $area->id,
        'equipe_id' => $equipe->id,
    ], $extra));
}

it('o chefe registra a resposta ao e-Salvador de uma demanda concluída — e nada sai para a rede', function () {
    $demanda = concluida();
    $chefe = pessoaCom('chefe-de-setor');

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'Equipe esteve no local em 20/09; o estabelecimento recolheu as mesas e foi orientado.',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso');

    $demanda->refresh();

    expect($demanda->respondida_ao_canal_em)->not->toBeNull()
        ->and($demanda->resposta_ao_canal)->toContain('recolheu as mesas')
        // Na resposta, o processo é o de origem: não se pede o que já se sabe.
        ->and($demanda->processo_esalvador)->toBe('215.5382.000123/2026')
        ->and($demanda->respondida_por_id)->toBe($chefe->id)
        // A situação não muda: responder não reabre nem "conclui de novo".
        ->and($demanda->situacao)->toBe(Demanda::CONCLUIDA);

    $passo = $demanda->ultimoTramite();

    expect($passo->acao)->toBe('Resposta registrada no e-Salvador')
        ->and($passo->papel)->toBe(DemandaTramite::PAPEL_CHEFE_DE_SETOR)
        ->and($passo->campos)->toMatchArray(['Processo no e-Salvador' => '215.5382.000123/2026'])
        ->and($passo->campos['Enviado pela integração'])->toStartWith('Não');

    // A LEI: com a escrita proibida, a rede não é tocada.
    Http::assertNothingSent();
});

it('a avulsa vira processo: o chefe registra a abertura, e o número do processo é obrigatório', function () {
    $demanda = concluida(['canal' => Demanda::CANAL_AVULSA, 'entrada' => Demanda::ENTRADA_BALCAO, 'numero_origem' => null]);
    $chefe = pessoaCom('chefe-de-setor');
    $texto = 'Vistoria em 21/09 confirmou a ocupação; ponto notificado e regularizado no local.';

    // Sem o número: com a integração desligada, é ele que prova a abertura.
    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), ['texto' => $texto])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'número do processo'));

    expect($demanda->fresh()->respondida_ao_canal_em)->toBeNull();

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => $texto,
            'processo' => '215.5382.004567/2026',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso', fn (string $r): bool => str_contains($r, '215.5382.004567/2026'));

    $demanda->refresh();

    expect($demanda->processo_esalvador)->toBe('215.5382.004567/2026')
        ->and($demanda->ultimoTramite()->acao)->toBe('Processo aberto no e-Salvador');

    Http::assertNothingSent();
});

it('só o que foi CONCLUÍDO volta ao canal', function () {
    $demanda = concluida(['situacao' => Demanda::EM_CAMPO, 'concluida_em' => null]);

    $this->actingAs(pessoaCom('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'Tentativa de responder antes do resultado da fiscalização.',
        ])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'CONCLUIU'));

    expect($demanda->fresh()->respondida_ao_canal_em)->toBeNull()
        ->and($demanda->fresh()->tramites()->count())->toBe(0);
});

it('o retorno é um só: a segunda tentativa é recusada dizendo quando foi a primeira', function () {
    $demanda = concluida();
    $chefe = pessoaCom('chefe-de-setor');
    $rota = route('retaguarda.denuncias.responder-ao-canal', $demanda);

    $this->actingAs($chefe)->post($rota, ['texto' => 'Primeira resposta, com o resultado da vistoria.']);
    $this->actingAs($chefe)->post($rota, ['texto' => 'Segunda resposta, que não deveria entrar.'])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'já teve o retorno'));

    expect($demanda->fresh()->resposta_ao_canal)->toContain('Primeira')
        ->and($demanda->fresh()->tramites()->count())->toBe(1);
});

it('o Fala Salvador também volta à origem: o chefe registra aqui, e a resposta ao cidadão é feita no canal', function () {
    // Até 24/09/2026 o Fala Salvador fechava na conclusão; o dono o incluiu entre
    // as origens para onde o chefe devolve o processo. Sem API: registrado aqui.
    config(['esalvador.ligada' => true]);
    $demanda = concluida(['canal' => Demanda::CANAL_FALA_SALVADOR, 'entrada' => Demanda::ENTRADA_BALCAO, 'numero_origem' => '156-2026-000123']);

    $this->actingAs(pessoaCom('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'Ponto vistoriado e regularizado; responder ao cidadão no Fala Salvador.',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso', fn (string $r): bool => str_contains($r, 'Fala Salvador'));

    expect(RetornoAoCanal::tipoDe($demanda))->toBe(RetornoAoCanal::TRAMITE)
        ->and($demanda->fresh()->ultimoTramite()->acao)->toBe('Resposta registrada no Fala Salvador');

    Http::assertNothingSent();
});

it('o líder não responde ao canal: o ato é do chefe', function () {
    $demanda = concluida();

    $this->actingAs(pessoaCom('lider-de-equipe'))
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'O líder tentando falar direto com o e-Salvador.',
        ])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'Chefe de Setor'));

    expect($demanda->fresh()->respondida_ao_canal_em)->toBeNull();
});

it('lei: com a integração LIGADA a escrita é barrada com o motivo, e nada é gravado nem enviado', function () {
    config(['esalvador.ligada' => true]);
    $demanda = concluida();

    $this->actingAs(pessoaCom('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'Resposta com a integração ligada por engano.',
        ])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'não foi liberada'));

    expect($demanda->fresh()->respondida_ao_canal_em)->toBeNull()
        ->and($demanda->fresh()->tramites()->count())->toBe(0)
        ->and(fn () => app(ESalvador::class)->abrirProcesso([]))->toThrow(EscritaNaoLiberada::class);

    Http::assertNothingSent();
});

it('o detalhe da demanda leva à tela o tipo de retorno do canal e o que já foi registrado', function () {
    $demanda = concluida();
    $chefe = pessoaCom('chefe-de-setor');

    $this->actingAs($chefe)
        ->get(route('retaguarda.denuncias.e-salvador.index'))
        ->assertInertia(fn ($p) => $p
            ->where('denuncias.0.retorno_ao_canal', 'tramite')
            ->where('denuncias.0.resposta_ao_canal', null)
            ->where('decide', true));

    $this->actingAs($chefe)->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
        'texto' => 'Equipe esteve no local; situação regularizada.',
    ]);

    $this->actingAs($chefe)
        ->get(route('retaguarda.denuncias.e-salvador.index'))
        ->assertInertia(fn ($p) => $p
            ->where('denuncias.0.resposta_ao_canal.processo', '215.5382.000123/2026')
            ->where('denuncias.0.resposta_ao_canal.enviado', false)
            ->where('denuncias.0.resposta_ao_canal.por', $chefe->name));
});

it('a avulsa pode terminar só na fiscalização: o chefe encerra sem processo', function () {
    $demanda = concluida(['canal' => Demanda::CANAL_AVULSA, 'entrada' => Demanda::ENTRADA_BALCAO]);

    $this->actingAs(pessoaCom('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'Vistoria confirmou a regularização; não cabe processo no e-Salvador.',
            'sem_processo' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso', fn (string $r): bool => str_contains($r, 'sem processo'));

    $demanda->refresh();

    expect($demanda->respondida_ao_canal_em)->not->toBeNull()
        ->and($demanda->processo_esalvador)->toBeNull()
        ->and($demanda->situacaoResumida())->toBe('Encerrada')
        ->and($demanda->ultimoTramite()->acao)->toBe('Encerrada com a fiscalização, sem processo');

    Http::assertNothingSent();
});

it('encerrar sem processo é só da avulsa: a demanda de canal é respondida no canal', function () {
    $demanda = concluida();

    $this->actingAs(pessoaCom('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'Tentando encerrar sem responder ao requerente do e-Salvador.',
            'sem_processo' => true,
        ])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'avulsa'));

    expect($demanda->fresh()->respondida_ao_canal_em)->toBeNull();
});

it('o e-Protocolo é respondido e registrado aqui, sem chamar a API do e-Salvador', function () {
    config(['esalvador.ligada' => true]);
    $demanda = concluida(['canal' => Demanda::CANAL_E_PROTOCOLO, 'entrada' => Demanda::ENTRADA_BALCAO, 'numero_origem' => 'EP-2026-0042']);

    $this->actingAs(pessoaCom('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'Atendido: a equipe vistoriou e o ponto foi regularizado no local.',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso', fn (string $r): bool => str_contains($r, 'e-Protocolo'));

    // Mesmo com a integração do e-Salvador LIGADA: o e-Protocolo não passa por ela.
    expect($demanda->fresh()->ultimoTramite()->acao)->toBe('Resposta registrada no e-Protocolo');

    Http::assertNothingSent();
});

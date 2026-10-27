<?php

use App\Models\Area;
use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Setor;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;
use App\Support\Estrutura;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| As Caixas de Entrada por canal
|--------------------------------------------------------------------------
|
| Decisão do dono em 24/09/2026. A Caixa de Entrada ("Geral") e a pasta
| Denúncias eram dois cantos do menu para o mesmo trabalho. Viraram UMA pasta
| com as quatro frentes — e-Salvador (denúncias e licenças), Fala Salvador,
| e-Protocolo (presencial na sede) e Avulsas —, com o mesmo fluxo e a mesma
| grade: seleção, protocolo, recebida, bairro, situação resumida e prazo.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

function quemTem(string $slug): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', $slug)->firstOrFail());

    return $u->fresh();
}

function demandaDo(string $canal, array $extra = []): Demanda
{
    static $n = 0;
    $n++;

    return Demanda::create(array_merge([
        'protocolo' => sprintf('DEM-T%04d', $n),
        'canal' => $canal,
        'entrada' => Demanda::ENTRADA_BALCAO,
        'numero_origem' => "N-{$n}",
        'recebida_em' => Date::now()->subDays(3),
        'prazo_em' => Date::now()->addDays(7),
        'assunto' => 'Mesas na calçada',
        'bairro' => 'Costa Azul',
        'situacao' => Demanda::RECEBIDA,
    ], $extra));
}

it('o menu tem a pasta Caixa de Entrada com as quatro caixas, e nenhuma pasta Denúncias', function () {
    $secao = collect((array) config('retaguarda_menu.secoes'))->firstWhere('rotulo', 'Caixa de Entrada');

    $pasta = collect($secao['itens'])->firstWhere('rotulo', 'Caixa de Entrada');

    expect($pasta)->not->toBeNull()
        ->and(array_column($pasta['filhos'], 'rotulo'))->toBe(['e-Salvador', 'Fala Salvador', 'e-Protocolo', 'Avulsas'])
        ->and(collect($secao['itens'])->pluck('rotulo')->all())->not->toContain('Denúncias', 'Geral')
        // Uma permissão para as quatro — e o líder entra.
        ->and(CatalogoFuncionalidades::setoresSemente('caixa-de-entrada'))->toContain('lider-de-equipe')
        ->and(CatalogoFuncionalidades::contem('denuncias'))->toBeFalse();
});

it('a caixa do e-Salvador tem as abas Denúncias, Licenças e Respondidas, e a licença vem na aba dela', function () {
    demandaDo(Demanda::CANAL_E_SALVADOR);
    demandaDo(Demanda::CANAL_NOVA_LICENCA);

    $this->actingAs(quemTem('chefe-de-setor'))
        ->get(route('retaguarda.denuncias.e-salvador.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Retaguarda/Denuncias/ESalvador')
            ->where('abas', ['denuncias', 'licencas', 'respondidas'])
            ->has('denuncias', 1)
            ->has('licencas', 1)
            ->where('licencas.0.canal', Demanda::CANAL_NOVA_LICENCA)
            // O chefe cadastra os dois tipos nesta caixa.
            ->where('registroEm.0.slug', Demanda::CANAL_E_SALVADOR)
            ->where('registroEm.1.slug', Demanda::CANAL_NOVA_LICENCA));
});

it('Fala Salvador e e-Protocolo têm Denúncias e Respondidas; as Avulsas, lista única', function () {
    $chefe = quemTem('chefe-de-setor');

    $this->actingAs($chefe)->get(route('retaguarda.denuncias.e-protocolo.index'))
        ->assertOk()->assertInertia(fn ($p) => $p->component('Retaguarda/Denuncias/EProtocolo')->where('abas', ['denuncias', 'respondidas']));

    $this->actingAs($chefe)->get(route('retaguarda.denuncias.fala-salvador.index'))
        ->assertOk()->assertInertia(fn ($p) => $p->where('abas', ['denuncias', 'respondidas']));

    $this->actingAs($chefe)->get(route('retaguarda.denuncias.avulsas.index'))
        ->assertOk()->assertInertia(fn ($p) => $p->component('Retaguarda/Denuncias/Avulsas')->where('abas', []));
});

it('a situação vem RESUMIDA em três palavras, e o que fechou vai para Respondidas', function () {
    $area = Area::create(['nome' => 'Área 5', 'regiao' => 'Orla']);
    $equipe = Equipe::create(['codigo' => 'C1', 'nome' => 'Equipe C1', 'area_id' => $area->id]);
    Estrutura::esquecer();

    $recebida = demandaDo(Demanda::CANAL_E_SALVADOR);
    $comLider = demandaDo(Demanda::CANAL_E_SALVADOR, ['situacao' => Demanda::ENCAMINHADA_AO_LIDER, 'equipe_id' => $equipe->id]);
    $emCampo = demandaDo(Demanda::CANAL_E_SALVADOR, ['situacao' => Demanda::EM_CAMPO, 'equipe_id' => $equipe->id]);
    // Concluída, mas ainda sem resposta ao canal: a fiscalização não acabou para o chefe.
    $semResposta = demandaDo(Demanda::CANAL_E_SALVADOR, ['situacao' => Demanda::CONCLUIDA]);
    $respondida = demandaDo(Demanda::CANAL_E_SALVADOR, ['situacao' => Demanda::CONCLUIDA, 'respondida_ao_canal_em' => Date::now()]);
    $arquivada = demandaDo(Demanda::CANAL_E_SALVADOR, ['situacao' => Demanda::ARQUIVADA]);

    expect($recebida->situacaoResumida())->toBe('Recebida')
        ->and($comLider->situacaoResumida())->toBe('Encaminhada ao líder')
        ->and($emCampo->situacaoResumida())->toBe('Em fiscalização')
        ->and($semResposta->situacaoResumida())->toBe('Em fiscalização')
        ->and($semResposta->respondida())->toBeFalse()
        ->and($respondida->situacaoResumida())->toBe('Respondida')
        ->and($respondida->respondida())->toBeTrue()
        ->and($arquivada->respondida())->toBeTrue();

    // O Fala Salvador também volta à origem pelo chefe (24/09/2026): concluída não
    // basta; respondida é quando o retorno foi registrado.
    expect(demandaDo(Demanda::CANAL_FALA_SALVADOR, ['situacao' => Demanda::CONCLUIDA])->respondida())->toBeFalse()
        ->and(demandaDo(Demanda::CANAL_FALA_SALVADOR, ['situacao' => Demanda::CONCLUIDA, 'respondida_ao_canal_em' => Date::now()])->respondida())->toBeTrue();

    // A avulsa que fechou se chama Encerrada.
    expect(demandaDo(Demanda::CANAL_AVULSA, ['situacao' => Demanda::CONCLUIDA, 'respondida_ao_canal_em' => Date::now()])
        ->situacaoResumida())->toBe('Encerrada');

    $this->actingAs(quemTem('chefe-de-setor'))
        ->get(route('retaguarda.denuncias.e-salvador.index'))
        ->assertInertia(function ($p) use ($respondida) {
            $linha = collect($p->toArray()['props']['denuncias'])->firstWhere('id', $respondida->id);

            expect($linha['situacao_resumida'])->toBe('Respondida')->and($linha['respondida'])->toBeTrue();

            return $p;
        });
});

it('o chefe cadastra no e-Protocolo: a demanda nasce Recebida, esperando o encaminhamento', function () {
    $this->actingAs(quemTem('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.registrar', 'e-protocolo'), [
            'documento_origem' => 'EP-2026-00077',
            'recebida_em' => Date::now()->format('Y-m-d'),
            'anonima' => false,
            'requerente' => 'Maria das Graças Souza',
            'assunto' => 'Barraca fixa na calçada da escola',
            'endereco' => 'Rua da Paciência, 40',
            'bairro' => 'Rio Vermelho',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('demanda_registrada');

    $demanda = Demanda::firstOrFail();

    expect($demanda->canal)->toBe(Demanda::CANAL_E_PROTOCOLO)
        ->and($demanda->situacao)->toBe(Demanda::RECEBIDA)
        ->and($demanda->equipe_id)->toBeNull()
        ->and($demanda->ultimoTramite()->campos)->toMatchArray(['Origem do documento' => 'e-Protocolo']);
});

it('a avulsa pode entrar sem número — foi uma ligação —, e o protocolo ocupa o lugar', function () {
    $this->actingAs(quemTem('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.registrar', 'avulsa'), [
            'tipo_avulsa' => Demanda::AVULSA_SUPERIOR,
            'recebida_em' => Date::now()->format('Y-m-d'),
            'anonima' => false,
            'requerente' => 'Coordenadoria de Ordem Pública',
            'assunto' => 'Verificar ambulantes na saída do estádio',
            'endereco' => 'Av. Vasco da Gama, portão sul',
            'bairro' => 'Vasco da Gama',
        ])
        ->assertSessionHasNoErrors();

    $demanda = Demanda::firstOrFail();

    expect($demanda->canal)->toBe(Demanda::CANAL_AVULSA)
        ->and($demanda->numero_origem)->toBe($demanda->protocolo);
});

it('canal que não admite anônima recusa a denúncia anônima, e número repetido não entra duas vezes', function () {
    $chefe = quemTem('chefe-de-setor');
    $base = [
        'documento_origem' => 'EP-1',
        'recebida_em' => Date::now()->format('Y-m-d'),
        'assunto' => 'x',
        'endereco' => 'Rua A, 1',
        'bairro' => 'Barra',
    ];

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.registrar', 'e-protocolo'), [...$base, 'anonima' => true])
        ->assertSessionHasErrors('anonima');

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.registrar', 'e-protocolo'), [...$base, 'anonima' => false, 'requerente' => 'João da Silva'])
        ->assertSessionHasNoErrors();

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.registrar', 'e-protocolo'), [...$base, 'anonima' => false, 'requerente' => 'João da Silva'])
        ->assertSessionHasErrors('documento_origem');

    expect(Demanda::count())->toBe(1);
});

it('o líder não cadastra no e-Protocolo nem entra na mesa antiga do chefe', function () {
    $lider = quemTem('lider-de-equipe');

    $this->actingAs($lider)
        ->post(route('retaguarda.denuncias.registrar', 'e-protocolo'), [
            'documento_origem' => 'EP-9',
            'recebida_em' => Date::now()->format('Y-m-d'),
            'anonima' => false,
            'requerente' => 'João da Silva',
            'assunto' => 'x',
            'endereco' => 'Rua A, 1',
            'bairro' => 'Barra',
        ])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'Chefe de Setor'));

    // A mesa antiga (fora do menu) guarda a pré-triagem: é só do chefe.
    $this->actingAs($lider)
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertRedirect(route('retaguarda.denuncias.e-salvador.index'))
        ->assertSessionHas('flash.erro');

    expect(Demanda::count())->toBe(0);
});

it('a migration leva a concessão de denuncias para caixa-de-entrada, sem duplicar', function () {
    DB::table('permissoes_setor')->whereIn('slug', ['denuncias', 'caixa-de-entrada'])->delete();

    $linha = fn (string $setor, string $slug) => [
        'setor' => $setor, 'slug' => $slug, 'visivel' => true, 'habilitado' => true,
        'apenas_leitura' => false, 'incluir' => true, 'excluir' => false,
        'created_at' => now(), 'updated_at' => now(),
    ];

    // O chefe tinha as duas; o líder, só a antiga.
    DB::table('permissoes_setor')->insert([
        $linha('chefe-de-setor', 'caixa-de-entrada'),
        $linha('chefe-de-setor', 'denuncias'),
        $linha('lider-de-equipe', 'denuncias'),
    ]);

    $migration = require database_path('migrations/2026_09_24_090000_caixas_de_entrada_por_canal.php');
    $migration->up();
    $migration->up();

    expect(DB::table('permissoes_setor')->where('slug', 'denuncias')->exists())->toBeFalse()
        ->and(DB::table('permissoes_setor')->where('slug', 'caixa-de-entrada')->orderBy('setor')->pluck('setor')->all())
        ->toBe(['chefe-de-setor', 'lider-de-equipe']);
});

it('os endereços antigos das telas de canal levam às caixas novas', function () {
    $this->actingAs(quemTem('chefe-de-setor'))
        ->get('/retaguarda/denuncias/e-salvador')
        ->assertRedirect('/retaguarda/caixa-de-entrada/e-salvador');
});

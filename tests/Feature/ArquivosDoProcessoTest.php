<?php

use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaAnexo;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\FiscalizacaoFoto;
use App\Models\Setor;
use App\Models\User;
use App\Support\Apresentacao\ArquivoParaTela;
use App\Support\Estrutura;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Os arquivos do processo: ver e baixar (dono, 25/09/2026)
|--------------------------------------------------------------------------
|
| "Deve ser possível visualizar e baixar os arquivos da fiscalização/processo:
| fotos tiradas ou anexados vindo da demanda."
|
| O que só o servidor garante: o arquivo sai por uma rota que confere a
| permissão da tela E o recorte do líder (trocar o número na URL não abre a
| foto de outra equipe); abrir no navegador é só para imagem e PDF; o que falta
| no disco responde 404 e a tela já sabe disso; e o anexo do cadastro manual é
| guardado com nome gerado, barrando executável.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    Storage::fake('local');
});

function contaDosArquivos(string $setor, array $extra = []): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true, ...$extra]);
    $u->setores()->attach(Setor::where('slug', $setor)->firstOrFail());

    return $u->fresh();
}

/** Uma equipe com líder, e uma vistoria dela com uma foto no disco. */
function vistoriaComFoto(string $codigo, User $lider): FiscalizacaoFoto
{
    $area = Area::firstOrCreate(['nome' => 'Área '.$codigo], ['regiao' => 'Orla']);
    $equipe = Equipe::create(['codigo' => $codigo, 'nome' => 'Equipe '.$codigo, 'area_id' => $area->id, 'lider_id' => $lider->id]);
    Estrutura::esquecer();

    $vistoria = Fiscalizacao::create([
        'protocolo' => 'VST-'.$codigo, 'origem' => Fiscalizacao::ORIGEM_AVULSA, 'equipe_id' => $equipe->id,
        'fiscal_id' => $lider->id, 'aberta_em' => Date::now(), 'situacao' => Fiscalizacao::AGUARDANDO_LEITURA,
    ]);

    Storage::disk('local')->put("fiscalizacoes/{$vistoria->id}/ponto.jpg", 'conteudo-da-foto');

    return FiscalizacaoFoto::create(['fiscalizacao_id' => $vistoria->id, 'caminho' => "fiscalizacoes/{$vistoria->id}/ponto.jpg", 'legenda' => 'ponto.jpg']);
}

it('a foto abre no navegador e baixa como arquivo, para quem pode ver as Fiscalizações', function () {
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);
    $foto = vistoriaComFoto('C1', contaDosArquivos('lider-de-equipe'));

    $this->actingAs($admin)->get(route('retaguarda.fiscalizacoes.foto', $foto->id))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'inline; filename=ponto.jpg');

    $this->actingAs($admin)->get(route('retaguarda.fiscalizacoes.foto', $foto->id).'?baixar=1')
        ->assertOk()
        ->assertDownload('ponto.jpg');
});

it('o líder abre a foto da equipe dele, e é barrado na foto de outra equipe', function () {
    $liderC1 = contaDosArquivos('lider-de-equipe', ['login' => 'lider-c1-arq']);
    $liderA1 = contaDosArquivos('lider-de-equipe', ['login' => 'lider-a1-arq']);
    $daC1 = vistoriaComFoto('C1', $liderC1);
    vistoriaComFoto('A1', $liderA1);

    $this->actingAs($liderC1)->get(route('retaguarda.fiscalizacoes.foto', $daC1->id))->assertOk();
    $this->actingAs($liderA1)->get(route('retaguarda.fiscalizacoes.foto', $daC1->id))->assertForbidden();
});

it('arquivo que não está no disco responde 404, e a tela recebe a marca de indisponível', function () {
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);
    $foto = vistoriaComFoto('C1', contaDosArquivos('lider-de-equipe'));
    Storage::disk('local')->delete($foto->caminho);

    $this->actingAs($admin)->get(route('retaguarda.fiscalizacoes.foto', $foto->id))->assertNotFound();

    expect(ArquivoParaTela::foto($foto)['disponivel'])->toBeFalse();
});

it('o chefe registra a demanda com anexo; o arquivo fica no disco privado com nome gerado e abre pela rota', function () {
    $chefe = contaDosArquivos('chefe-de-setor');

    $this->actingAs($chefe)->post(route('retaguarda.denuncias.registrar', 'avulsa'), [
        'tipo_avulsa' => Demanda::AVULSA_OFICIO,
        'recebida_em' => Date::now()->format('Y-m-d'),
        'anonima' => false,
        'requerente' => 'Ministério Público da Bahia',
        'assunto' => 'Apurar barracas na calçada',
        'endereco' => 'Rua Chile, 10',
        'bairro' => 'Centro',
        'anexos' => [UploadedFile::fake()->create('oficio 123.pdf', 40, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $anexo = DemandaAnexo::firstOrFail();

    expect($anexo->nome)->toBe('oficio 123.pdf')
        ->and($anexo->caminho)->not->toContain('oficio 123')
        ->and(Storage::disk('local')->exists($anexo->caminho))->toBeTrue();

    $this->actingAs($chefe)->get(route('retaguarda.denuncias.anexo', $anexo->id).'?baixar=1')
        ->assertOk()
        ->assertDownload('oficio 123.pdf');

    $this->actingAs($chefe)->get(route('retaguarda.denuncias.avulsas.index'))
        ->assertInertia(fn ($p) => $p->where('denuncias.0.anexos_arquivos.0.nome', 'oficio 123.pdf')
            ->where('denuncias.0.anexos_arquivos.0.disponivel', true));
});

it('o anexo executável é recusado no cadastro', function () {
    $this->actingAs(contaDosArquivos('chefe-de-setor'))->post(route('retaguarda.denuncias.registrar', 'avulsa'), [
        'tipo_avulsa' => Demanda::AVULSA_SUPERIOR,
        'recebida_em' => Date::now()->format('Y-m-d'),
        'anonima' => false,
        'requerente' => 'Coordenadoria',
        'assunto' => 'x',
        'endereco' => 'Rua A, 1',
        'bairro' => 'Barra',
        'anexos' => [UploadedFile::fake()->create('programa.exe', 10)],
    ])->assertSessionHasErrors('anexos.0');

    expect(Demanda::count())->toBe(0);
});

it('o comando de demonstração cria o arquivo que falta, sem tocar no que existe', function () {
    $foto = vistoriaComFoto('C1', contaDosArquivos('lider-de-equipe'));
    $semArquivo = FiscalizacaoFoto::create(['fiscalizacao_id' => $foto->fiscalizacao_id, 'caminho' => 'fiscalizacoes/x/sumiu.jpg', 'legenda' => 'sumiu.jpg']);

    $this->artisan('sefal:arquivos-de-demonstracao')->assertSuccessful();

    expect(Storage::disk('local')->get($foto->caminho))->toBe('conteudo-da-foto')
        ->and(Storage::disk('local')->exists($semArquivo->caminho))->toBeTrue();
});

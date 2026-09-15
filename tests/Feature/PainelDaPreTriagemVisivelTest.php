<?php

use App\Models\Demanda;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| LEI — a Pré-Triagem é alcançável, mesmo com a mesa vazia
|--------------------------------------------------------------------------
|
| Reproduz um defeito real: o painel só era renderizado quando JÁ HAVIA
| sugestões, e o botão que roda a varredura mora dentro dele. Num banco limpo —
| que é exatamente o estado de quem abre o sistema pela primeira vez — a
| funcionalidade ficava inalcançável: sem propostas não havia painel, sem painel
| não havia botão, e sem botão nunca haveria propostas.
|
| O teste olha os PROPS que a tela recebe, porque são eles que decidem o que o
| React renderiza, e olha onde cada fila vive: a leva crua na pré-triagem, o que
| já foi entendido na Caixa.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

/** Uma denúncia que chegou por integração e ainda não foi entendida. */
function chegadaCrua(string $protocolo, string $situacao = Demanda::EM_PRE_TRIAGEM): Demanda
{
    return Demanda::create([
        'protocolo' => $protocolo,
        'canal' => Demanda::CANAL_E_SALVADOR,
        'entrada' => Demanda::ENTRADA_INTEGRACAO,
        'numero_origem' => 'ESL-'.$protocolo,
        'recebida_em' => Date::now()->subHours(6),
        'prazo_em' => Date::now()->addDays(10),
        'assunto' => 'Mesas e cadeiras ocupando a calçada',
        'relato' => 'O bar botou mesa na calçada inteira e ninguém passa.',
        'logradouro' => 'Rua Rio Grande do Sul',
        'numero' => '210',
        'bairro' => 'Pituba',
        'situacao' => $situacao,
    ]);
}

it('entrega os props da pré-triagem mesmo quando não há nenhuma proposta', function () {
    $coordenador = User::factory()->create(['admin' => false, 'ativo' => true]);
    $coordenador->setores()->syncWithoutDetaching([Setor::where('slug', 'coordenador')->firstOrFail()->id]);

    $this->actingAs($coordenador)
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            // Vazios, e PRESENTES: é a ausência da chave que quebraria a tela, e
            // é o valor vazio que ela precisa saber desenhar.
            ->where('sugestoesDeAgrupamento', [])
            ->where('preTriagem', []),
        );
});

it('separa as duas filas: a leva crua na pré-triagem, o resto na caixa', function () {
    chegadaCrua('DEN-9001');
    chegadaCrua('DEN-9002', Demanda::RECEBIDA);

    $this->actingAs(User::factory()->create(['admin' => true, 'ativo' => true]))
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->has('preTriagem', 1)
            ->where('preTriagem.0.protocolo', 'DEN-9001')
            // A que já foi entendida está na CAIXA, à espera do crivo do
            // coordenador — e não aparece duas vezes.
            ->has('demandas', 1)
            ->where('demandas.0.protocolo', 'DEN-9002'),
        );
});

it('liberar passa a denúncia para a caixa e deixa o passo registrado', function () {
    $crua = chegadaCrua('DEN-9003');

    $this->actingAs(User::factory()->create(['admin' => true, 'ativo' => true]))
        ->post(route('retaguarda.caixa-de-entrada.agrupamento.liberar'), ['demandas' => [$crua->id]])
        ->assertSessionHas('flash.sucesso');

    expect($crua->fresh()->situacao)->toBe(Demanda::RECEBIDA)
        // O passo existe: mudar a etapa sem deixar rastro apagaria a memória de
        // que alguém, em algum momento, olhou aquela leva.
        ->and($crua->tramites()->where('acao', 'Pré-triagem concluída')->count())->toBe(1);
});

it('liberar recusa o que não está em pré-triagem', function () {
    $jaTriada = chegadaCrua('DEN-9004', Demanda::ENCAMINHADA_A_AREA);

    $this->actingAs(User::factory()->create(['admin' => true, 'ativo' => true]))
        ->post(route('retaguarda.caixa-de-entrada.agrupamento.liberar'), ['demandas' => [$jaTriada->id]])
        ->assertSessionHas('flash.erro');

    // Nada mudou: a etapa dela já passou, e "liberar" de novo a devolveria à
    // triagem desfazendo um encaminhamento que ninguém pediu para desfazer.
    expect($jaTriada->fresh()->situacao)->toBe(Demanda::ENCAMINHADA_A_AREA);
});

it('liberar a principal não apaga as propostas do grupo', function () {
    // O caso que a varredura elegeu como principal, e uma denúncia proposta a ele.
    $principal = chegadaCrua('DEN-9010');
    $agregada = chegadaCrua('DEN-9011');

    $sugestao = App\Models\SugestaoAgrupamento::create([
        'demanda_id' => $agregada->id,
        'principal_id' => $principal->id,
        'confianca' => 0.9,
        'motivo' => 'mesmo bairro (Pituba); o mesmo logradouro (Rua Rio Grande do Sul)',
    ]);

    $this->actingAs(User::factory()->create(['admin' => true, 'ativo' => true]))
        ->post(route('retaguarda.caixa-de-entrada.agrupamento.liberar'), ['demandas' => [$principal->id]])
        ->assertSessionHas('flash.sucesso');

    // A proposta SOBREVIVE: quando a leva nova repete o que já saiu para a rua,
    // é o caso que saiu que responde pelas novas. Descartar os dois lados fazia
    // liberar a principal apagar o grupo inteiro da mesa do coordenador.
    expect($sugestao->fresh()->estado)->toBe(App\Models\SugestaoAgrupamento::SUGERIDA);
});

it('liberar a agregada descarta a proposta, que ninguém mais poderia decidir', function () {
    $principal = chegadaCrua('DEN-9020');
    $agregada = chegadaCrua('DEN-9021');

    $sugestao = App\Models\SugestaoAgrupamento::create([
        'demanda_id' => $agregada->id,
        'principal_id' => $principal->id,
        'confianca' => 0.9,
        'motivo' => 'mesmo bairro (Pituba); o mesmo logradouro (Rua Rio Grande do Sul)',
    ]);

    $this->actingAs(User::factory()->create(['admin' => true, 'ativo' => true]))
        ->post(route('retaguarda.caixa-de-entrada.agrupamento.liberar'), ['demandas' => [$agregada->id]]);

    // Agregar só vale para quem ainda está em pré-triagem: mantida, a proposta
    // seria uma decisão que o coordenador não tem como tomar.
    expect($sugestao->fresh()->estado)->toBe(App\Models\SugestaoAgrupamento::RECUSADA);
});

it('a fila da pré-triagem segue o mesmo recorte de área da varredura', function () {
    $pituba = App\Models\Area::create(['nome' => 'Área 1', 'regiao' => 'Orla']);
    $outra = App\Models\Area::create(['nome' => 'Área 9', 'regiao' => 'Miolo']);

    chegadaCrua('DEN-9030')->update(['area_id' => $pituba->id]);
    chegadaCrua('DEN-9031')->update(['area_id' => $outra->id]);

    // Um Chefe de Setor responde por UMA área.
    $chefe = User::factory()->create(['admin' => false, 'ativo' => true]);
    $chefe->setores()->syncWithoutDetaching([Setor::where('slug', 'chefe-de-setor')->firstOrFail()->id]);
    $pituba->update(['chefe_de_setor_id' => $chefe->id]);

    // A estrutura vive em memória dentro do container; sem esquecê-la, a
    // consulta enxergaria a árvore anterior ao vínculo que acabou de nascer.
    App\Support\Estrutura::esquecer();

    $this->actingAs($chefe)
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            // Só a área dele. A fila mostrar o universo enquanto a varredura
            // olha só a área fazia a tela se contradizer na mesma dobra:
            // "N denúncias aguardam pré-triagem" logo acima de "nenhuma
            // repetição entre as abertas".
            ->has('preTriagem', 1)
            ->where('preTriagem.0.protocolo', 'DEN-9030'),
        );
});

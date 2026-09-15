<?php

use App\Models\Demanda;
use App\Models\Setor;
use App\Models\SugestaoAgrupamento;
use App\Models\User;
use App\Support\Agrupamento\AnalisadorPorRegra;
use App\Support\Agrupamento\VarreduraDeAgrupamento;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A PRÉ-TRIAGEM: dez denúncias que são um fato
|--------------------------------------------------------------------------
|
| O e-Salvador não entrega casos organizados — entrega o que cada cidadão
| escreveu. O que estes testes protegem não é o acerto do algoritmo (esse o
| coordenador julga olhando), e sim as três promessas que sustentam a tela:
|
| 1. **a máquina PROPÕE, quem agrupa é gente.** Enquanto ninguém decide, nada
|    mudou de lugar — nem a situação, nem a fila;
| 2. **a proposta é de GRUPO, não de par.** Seis denúncias do mesmo bar viram
|    cinco propostas que convergem na mais antiga, e não quinze pares em cadeia
|    que, aceitos dois a dois, o próprio model recusaria;
| 3. **a recusa segura.** É a informação mais cara daqui — e um assistente que
|    insiste no que já foi negado faz o coordenador parar de ler a lista.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

function coordenadorDaTriagem(): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->syncWithoutDetaching([Setor::where('slug', 'coordenador')->firstOrFail()->id]);

    return $u->fresh();
}

/** Uma denúncia do mesmo bar, com as palavras de quem a escreveu. */
function relato(string $assunto, string $numero, int $horasAtras, array $extra = []): Demanda
{
    static $n = 0;
    $n++;

    return Demanda::create(array_merge([
        'protocolo' => sprintf('DEN-7%03d', $n),
        'canal' => Demanda::CANAL_E_SALVADOR,
        'entrada' => Demanda::ENTRADA_INTEGRACAO,
        'numero_origem' => 'ESL-2026-7'.$n,
        'recebida_em' => Date::now()->subHours($horasAtras),
        'prazo_em' => Date::now()->addDays(10),
        'assunto' => $assunto,
        'logradouro' => 'Rua Rio Grande do Sul',
        'numero' => $numero,
        'bairro' => 'Pituba',
        // EM PRÉ-TRIAGEM: é o estado em que a varredura propõe. Depois dessa
        // etapa a denúncia já foi entendida e encaminhada, e passar a ser
        // respondida por outra mudaria o que o cidadão recebe sem que ninguém
        // estivesse olhando aquela mesa.
        'situacao' => Demanda::EM_PRE_TRIAGEM,
    ], $extra));
}

function varrer(): array
{
    return (new VarreduraDeAgrupamento(new AnalisadorPorRegra))->executar();
}

it('propõe agrupar o que é o mesmo fato, e a proposta não muda nada sozinha', function () {
    $antiga = relato('Mesas e cadeiras ocupando a calçada', '212', 40);
    $nova = relato('Cadeiras impedindo a passagem de pedestres', '214', 10);

    $efeito = varrer();

    expect($efeito['propostas'])->toBe(1)
        ->and($efeito['grupos'])->toBe(1);

    $sugestao = SugestaoAgrupamento::firstOrFail();

    // A PRINCIPAL é a mais antiga: é a que espera há mais tempo e a que a
    // ouvidoria já cobrou.
    expect($sugestao->principal_id)->toBe($antiga->id)
        ->and($sugestao->demanda_id)->toBe($nova->id)
        ->and($sugestao->motivo)->toContain('mesmo logradouro')
        // E NADA foi agrupado: a máquina propôs, ninguém decidiu.
        ->and($nova->fresh()->agrupada_em_id)->toBeNull()
        ->and($nova->fresh()->situacao)->toBe(Demanda::EM_PRE_TRIAGEM);
});

it('não compara bairros diferentes, por mais parecido que seja o assunto', function () {
    relato('Mesas e cadeiras ocupando a calçada', '212', 40);
    relato('Mesas e cadeiras ocupando a calçada', '212', 10, ['bairro' => 'Cajazeiras II a XI']);

    /*
     * O comércio de rua repete assunto a cidade inteira. Sem o bairro como
     * pré-requisito, a fila de propostas viraria ruído — e o coordenador pararia
     * de ler a lista inteira.
     */
    expect(varrer()['propostas'])->toBe(0);
});

it('fecha o grupo na mais antiga: seis denúncias viram cinco propostas que convergem', function () {
    $primeira = relato('Mesas e cadeiras ocupando a calçada', '212', 90);

    foreach ([['Bar ocupando o passeio público', '214', 70],
        ['Cadeiras impedindo a passagem de pedestres', '218', 55],
        ['Mesas na calçada atrapalhando quem anda', '212', 40],
        ['Ocupação irregular da calçada', '210', 25],
        ['Mesas do bar bloqueando a calçada', '216', 8]] as [$assunto, $numero, $horas]) {
        relato($assunto, $numero, $horas);
    }

    $efeito = varrer();

    /*
     * ⚠️ É AQUI que a tela presta ou não presta. Seis denúncias produzem quinze
     * PARES; publicar os quinze faria o coordenador decidir quinze vezes para
     * juntar seis casos — e aceitar dois em cadeia (A←B, B←C) esbarraria na
     * recusa do model, depois de ele já ter clicado.
     */
    expect($efeito['grupos'])->toBe(1)
        ->and($efeito['propostas'])->toBe(5);

    $principais = SugestaoAgrupamento::pendentes()->pluck('principal_id')->unique();

    expect($principais)->toHaveCount(1)
        ->and($principais->first())->toBe($primeira->id);
});

it('aceitar agrupa, tira da fila de trabalho e deixa passo nos dois lados', function () {
    $antiga = relato('Mesas e cadeiras ocupando a calçada', '212', 40);
    $nova = relato('Cadeiras impedindo a passagem', '214', 10);
    varrer();

    $sugestao = SugestaoAgrupamento::firstOrFail();

    $this->actingAs(coordenadorDaTriagem())
        ->post(route('retaguarda.denuncias.agrupamento.aceitar', $sugestao), [
            'observacao' => 'Conferi no mapa: é o mesmo bar.',
        ])
        ->assertSessionHas('flash.sucesso');

    expect($nova->fresh()->agrupada_em_id)->toBe($antiga->id)
        ->and($nova->fresh()->situacao)->toBe(Demanda::AGRUPADA)
        ->and(Demanda::deTrabalho()->count())->toBe(1)
        ->and($sugestao->fresh()->estado)->toBe(SugestaoAgrupamento::ACEITA)
        // O motivo da MÁQUINA fica registrado junto com o que o humano escreveu:
        // guardar só a observação apagaria o raciocínio que ele aceitou.
        ->and($nova->fresh()->ultimoTramite()->detalhe)
        ->toContain('mesmo logradouro')
        ->toContain('Conferi no mapa');
});

it('recusar exige o porquê, e a recusa impede a próxima varredura de propor de novo', function () {
    relato('Mesas e cadeiras ocupando a calçada', '212', 40);
    relato('Mesas na calçada do restaurante', '214', 10);
    varrer();

    $sugestao = SugestaoAgrupamento::firstOrFail();
    $coordenador = coordenadorDaTriagem();

    // Sem motivo escrito não passa: é ele que ensina a próxima varredura.
    $this->actingAs($coordenador)
        ->post(route('retaguarda.denuncias.agrupamento.recusar', $sugestao), ['observacao' => 'não'])
        ->assertSessionHasErrors('observacao');

    $this->actingAs($coordenador)
        ->post(route('retaguarda.denuncias.agrupamento.recusar', $sugestao), [
            'observacao' => 'São dois estabelecimentos diferentes, a cinquenta metros um do outro.',
        ])
        ->assertSessionHas('flash.sucesso');

    expect($sugestao->fresh()->estado)->toBe(SugestaoAgrupamento::RECUSADA);

    // A segunda varredura não propõe o mesmo par: insistir no que já foi negado
    // faz o coordenador parar de ler a lista.
    expect(varrer()['propostas'])->toBe(0);
    expect(SugestaoAgrupamento::pendentes()->count())->toBe(0);
});

it('agrupar à mão vale, e exige o motivo que o cidadão vai ler', function () {
    $antiga = relato('Barraca na calçada', '212', 40);
    // Assunto e rua diferentes: a máquina não ligaria os dois, e o coordenador
    // conhece a rua melhor que qualquer regra.
    $outra = relato('Som alto depois das 22h', '900', 10, ['logradouro' => 'Rua Ceará']);

    $coordenador = coordenadorDaTriagem();

    $this->actingAs($coordenador)
        ->post(route('retaguarda.denuncias.agrupamento.agrupar', $outra), [
            'principal_id' => $antiga->id,
            'motivo' => 'curto',
        ])
        ->assertSessionHasErrors('motivo');

    $this->actingAs($coordenador)
        ->post(route('retaguarda.denuncias.agrupamento.agrupar', $outra), [
            'principal_id' => $antiga->id,
            'motivo' => 'É o mesmo estabelecimento: a entrada é pela Rua Ceará e as mesas ficam na outra face.',
        ])
        ->assertSessionHas('flash.sucesso');

    expect($outra->fresh()->agrupada_em_id)->toBe($antiga->id);
});

it('desagrupar devolve a denúncia à triagem, porque a associação pode estar errada', function () {
    $antiga = relato('Mesas e cadeiras ocupando a calçada', '212', 40);
    $nova = relato('Cadeiras impedindo a passagem', '214', 10);
    $coordenador = coordenadorDaTriagem();

    $nova->agruparEm($antiga, $coordenador, 'Pareceu o mesmo ponto.');

    $this->actingAs($coordenador)
        ->post(route('retaguarda.denuncias.agrupamento.desagrupar', $nova), [
            'motivo' => 'Fui ao mapa: são dois estabelecimentos, a cinquenta metros um do outro.',
        ])
        ->assertSessionHas('flash.sucesso');

    expect($nova->fresh()->agrupada_em_id)->toBeNull()
        // Volta para a PRÉ-TRIAGEM, de onde saiu: ninguém a entendeu ainda, e
        // devolvê-la à Caixa a faria pular a etapa.
        ->and($nova->fresh()->situacao)->toBe(Demanda::EM_PRE_TRIAGEM)
        ->and(Demanda::deTrabalho()->count())->toBe(2);
});

it('a denúncia que ganhou dono some das outras propostas pendentes', function () {
    relato('Mesas e cadeiras ocupando a calçada', '212', 90);
    relato('Bar ocupando o passeio', '214', 60);
    relato('Cadeiras na calçada', '216', 30);
    varrer();

    expect(SugestaoAgrupamento::pendentes()->count())->toBe(2);

    $sugestao = SugestaoAgrupamento::pendentes()->firstOrFail();

    $this->actingAs(coordenadorDaTriagem())
        ->post(route('retaguarda.denuncias.agrupamento.aceitar', $sugestao), []);

    /*
     * A proposta que sobrou continua de pé (é outra denúncia), mas nenhuma
     * proposta sobre a que acabou de ganhar dono permanece: oferecê-la de novo
     * seria oferecer agrupá-la uma segunda vez.
     */
    $sobre = SugestaoAgrupamento::pendentes()
        ->where(fn ($q) => $q->where('demanda_id', $sugestao->demanda_id)->orWhere('principal_id', $sugestao->demanda_id))
        ->count();

    expect($sobre)->toBe(0);
});

it('não propõe agrupamento em canal que não repete o mesmo fato', function () {
    /*
     * Pedido de licença é de UM requerente para UM ponto, e ofício tem um
     * remetente que espera resposta própria: agrupá-los não economizaria ida
     * nenhuma e perderia o caso de alguém.
     */
    relato('Pedido de licença para carrinho', '212', 40, [
        'canal' => Demanda::CANAL_NOVA_LICENCA,
        'entrada' => Demanda::ENTRADA_BALCAO,
    ]);
    relato('Pedido de licença para carrinho', '214', 10, [
        'canal' => Demanda::CANAL_NOVA_LICENCA,
        'entrada' => Demanda::ENTRADA_BALCAO,
    ]);

    expect(varrer()['propostas'])->toBe(0);
});

it('a varredura avisa quando não encontrou repetição, em vez de ficar calada', function () {
    relato('Barraca na calçada', '212', 40);

    $this->actingAs(coordenadorDaTriagem())
        ->post(route('retaguarda.denuncias.agrupamento.varrer'))
        ->assertSessionHas('flash.sucesso', fn (string $r): bool => str_contains($r, 'Nenhuma denúncia repetida'));
});

it('a tela entrega as propostas com os dois lados inteiros', function () {
    relato('Mesas e cadeiras ocupando a calçada', '212', 40, ['requerente' => 'Lúcia Bastos']);
    relato('Cadeiras impedindo a passagem', '214', 10);
    varrer();

    // Na CAIXA DE ENTRADA, aba Pré-Triagem: é a etapa anterior à tela de
    // Denúncias, e servir as propostas nos dois lugares criaria duas mesas para
    // a mesma decisão.
    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->has('sugestoesDeAgrupamento', 1)
            // Os dois lados inteiros: ninguém deve juntar casos de cidadãos
            // diferentes lendo dois protocolos e um número de confiança.
            ->has('sugestoesDeAgrupamento.0.agregada.assunto')
            ->has('sugestoesDeAgrupamento.0.principal.assunto')
            ->where('sugestoesDeAgrupamento.0.principal.requerente', 'Lúcia Bastos')
            ->has('sugestoesDeAgrupamento.0.motivo')
            ->has('sugestoesDeAgrupamento.0.confianca_pct'),
        );
});

it('não propõe agregar o que já saiu da pré-triagem — mas aceita que a principal seja um caso já triado', function () {
    // A mais antiga já foi entendida e encaminhada: é ela que está em campo.
    $emCampo = relato('Mesas e cadeiras ocupando a calçada', '212', 90);
    $emCampo->update(['situacao' => Demanda::ENCAMINHADA_A_AREA]);

    // Uma chegou agora, ainda crua; a outra também já foi triada.
    $crua = relato('Cadeiras impedindo a passagem', '214', 20);
    $jaTriada = relato('Bar ocupando o passeio', '216', 50);
    $jaTriada->update(['situacao' => Demanda::DIRECIONADA_A_EQUIPE]);

    varrer();

    $pendentes = SugestaoAgrupamento::pendentes()->get();

    // Só a CRUA é proposta, e ela é proposta PARA a que está em campo: quando a
    // leva nova repete o que a equipe já foi ver, é o caso em campo que responde
    // pelas novas. O contrário — pendurar numa denúncia já encaminhada uma
    // decisão nova — mudaria o que o cidadão recebe sem ninguém olhando a mesa.
    expect($pendentes)->toHaveCount(1)
        ->and($pendentes->first()->demanda_id)->toBe($crua->id)
        ->and($pendentes->first()->principal_id)->toBe($emCampo->id)
        ->and($jaTriada->fresh()->situacao)->toBe(Demanda::DIRECIONADA_A_EQUIPE);
});

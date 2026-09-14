<?php

use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\SugestaoAgrupamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

/**
 * Dez denúncias que são um fato — a regra que o dono descreveu em 14/09/2026.
 *
 * O que estes testes protegem não é a tela: é a promessa de que NADA se perde no
 * agrupamento. Cada denúncia continua inteira, com o protocolo pelo qual a
 * ouvidoria cobra, e a resposta da fiscalização volta para todas.
 */
function denuncia(array $atributos = []): Demanda
{
    static $sequencia = 0;
    $sequencia++;

    return Demanda::create(array_merge([
        'protocolo' => sprintf('DEN-9%03d', $sequencia),
        'canal' => Demanda::CANAL_E_SALVADOR,
        'entrada' => Demanda::ENTRADA_INTEGRACAO,
        'numero_origem' => 'ESL-2026-9'.$sequencia,
        'recebida_em' => Date::now()->subHours(3),
        'prazo_em' => Date::now()->addDays(10),
        'assunto' => 'Mesas e cadeiras ocupando a via',
        'bairro' => 'Pituba',
        'situacao' => Demanda::RECEBIDA,
    ], $atributos));
}

it('agrega a denúncia ao registro de trabalho sem apagar nada dela', function () {
    $coordenador = User::factory()->create();
    $principal = denuncia(['assunto' => 'Mesas na calçada em frente ao 212']);
    $agregada = denuncia(['numero_origem' => 'ESL-2026-OUTRO']);

    $agregada->agruparEm($principal, $coordenador, 'Mesmo ponto: o bar do térreo do 212.');

    $agregada->refresh();

    expect($agregada->agrupada_em_id)->toBe($principal->id)
        ->and($agregada->situacao)->toBe(Demanda::AGRUPADA)
        // O que a ouvidoria cobra continua de pé.
        ->and($agregada->protocolo)->not->toBeNull()
        ->and($agregada->numero_origem)->toBe('ESL-2026-OUTRO')
        ->and($principal->fresh()->agregadas)->toHaveCount(1);
});

it('deixa passo nos dois lados, porque os dois cidadãos precisam saber', function () {
    $coordenador = User::factory()->create();
    $principal = denuncia();
    $agregada = denuncia();

    $agregada->agruparEm($principal, $coordenador, 'Mesmo fato.');

    expect($agregada->ultimoTramite()->acao)->toBe('Agrupada a outro registro')
        ->and($principal->ultimoTramite()->acao)->toBe('Recebeu denúncia agregada');
});

it('some da fila de trabalho, para o coordenador não triar dez vezes o mesmo caso', function () {
    $coordenador = User::factory()->create();
    $principal = denuncia();
    denuncia()->agruparEm($principal, $coordenador, 'Mesmo fato.');
    denuncia()->agruparEm($principal, $coordenador, 'Mesmo fato.');

    expect(Demanda::count())->toBe(3)
        ->and(Demanda::deTrabalho()->count())->toBe(1);
});

it('recusa corrente: não se agrega a uma demanda que já é agregada', function () {
    $coordenador = User::factory()->create();
    $principal = denuncia();
    $agregada = denuncia();
    $agregada->agruparEm($principal, $coordenador, 'Mesmo fato.');

    denuncia()->agruparEm($agregada, $coordenador, 'Mesmo fato.');
})->throws(InvalidArgumentException::class, 'já está agregada');

it('desagrupa devolvendo a denúncia à fila, porque a associação pode estar errada', function () {
    $coordenador = User::factory()->create();
    $principal = denuncia();
    $agregada = denuncia();
    $agregada->agruparEm($principal, $coordenador, 'Pareceu o mesmo ponto.');

    $agregada->desagrupar($coordenador, 'São dois estabelecimentos, a cinquenta metros um do outro.');

    $agregada->refresh();

    expect($agregada->agrupada_em_id)->toBeNull()
        ->and($agregada->situacao)->toBe(Demanda::RECEBIDA)
        ->and(Demanda::deTrabalho()->count())->toBe(2)
        // O desfazer também é ato administrativo: fica registrado.
        ->and($agregada->ultimoTramite()->acao)->toBe('Desagrupada');
});

it('responde todas as agregadas com o desfecho da fiscalização que foi a campo', function () {
    $coordenador = User::factory()->create();
    $principal = denuncia();
    denuncia()->agruparEm($principal, $coordenador, 'Mesmo fato.');
    denuncia()->agruparEm($principal, $coordenador, 'Mesmo fato.');

    $respondidas = $principal->responderAgregadas('Regularizado no local', $coordenador);

    expect($respondidas)->toBe(2);

    foreach ($principal->agregadas()->get() as $agregada) {
        expect($agregada->situacao)->toBe(Demanda::CONCLUIDA)
            ->and($agregada->ultimoTramite()->campos)
            ->toMatchArray([
                'Registro que foi a campo' => $principal->protocolo,
                'Desfecho da fiscalização' => 'Regularizado no local',
            ]);
    }
});

it('não responde de novo a agregada que já foi encerrada', function () {
    $coordenador = User::factory()->create();
    $principal = denuncia();
    $agregada = denuncia();
    $agregada->agruparEm($principal, $coordenador, 'Mesmo fato.');
    $agregada->registrar('Arquivada', Demanda::ARQUIVADA, DemandaTramite::PAPEL_COORDENADOR, $coordenador);

    expect($principal->responderAgregadas('Regularizado no local', $coordenador))->toBe(0);
});

it('guarda a sugestão da máquina como proposta, com o porquê e a decisão de gente', function () {
    $coordenador = User::factory()->create();
    $principal = denuncia();
    $candidata = denuncia();

    $sugestao = SugestaoAgrupamento::create([
        'demanda_id' => $candidata->id,
        'principal_id' => $principal->id,
        'origem' => SugestaoAgrupamento::ORIGEM_IA,
        'confianca' => 0.87,
        'motivo' => 'Mesmo bairro, endereços a 30 m e os dois relatos falam de mesas na calçada.',
    ]);

    expect($sugestao->estado)->toBe(SugestaoAgrupamento::SUGERIDA)
        ->and(SugestaoAgrupamento::pendentes()->count())->toBe(1)
        // Enquanto ninguém decide, NADA foi agrupado: a máquina propõe, não age.
        ->and($candidata->fresh()->agrupada_em_id)->toBeNull();

    $sugestao->decidir(SugestaoAgrupamento::RECUSADA, $coordenador, 'São dois bares diferentes.');

    expect($sugestao->fresh()->decidida_por_id)->toBe($coordenador->id)
        ->and(SugestaoAgrupamento::pendentes()->count())->toBe(0)
        // A recusa não some: é ela que impede a máquina de propor o mesmo par amanhã.
        ->and(SugestaoAgrupamento::count())->toBe(1);
});

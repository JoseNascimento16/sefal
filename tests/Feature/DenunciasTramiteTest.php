<?php

use App\Models\Setor;
use App\Models\User;
use App\Support\Prototipo\DenunciasFicticias;
use App\Support\Prototipo\EstruturaFicticia;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;

/*
|--------------------------------------------------------------------------
| O trâmite avançado das denúncias — o dado semeado e o recorte de quem lê
|--------------------------------------------------------------------------
|
| O módulo de Denúncias é PROTÓTIPO: nada é gravado, e o dado de partida mora em
| `config/prototipo_denuncias.php`. Isso muda o QUE se testa, não SE se testa.
|
| Duas famílias de teste aqui:
|
| 1. **Coerência do dado semeado.** O trâmite avançado é escrito à mão, passo a
|    passo, num arquivo grande — e é exatamente o tipo de dado que se estraga em
|    silêncio: uma situação que não casa com o último passo, uma hora que anda
|    para trás, um motivo de notificação com a chave errada. Nada disso aparece
|    como erro: aparece como tela mostrando "Concluída" com o trâmite parando em
|    campo, ou como documento sem nenhuma caixa assinalada. A varredura é uma, e
|    cobre também o caso que alguém acrescentar amanhã.
|
| 2. **O recorte do gestor alcança o CONTEÚDO, não só a linha.** O trâmite
|    avançado carrega o relato do fiscal, as fotos e o documento lavrado dentro
|    da própria denúncia. Se o recorte por área falhasse, não vazaria um número
|    numa grade: vazaria o nome do notificado, o CPF e o número da notificação de
|    uma área que não é dele.
|
| A régua é o doc de regra (`docs/regras-de-negocio/fiscalizacao/denuncias.md`,
| RN-05b e RN-16 a RN-18) — não há HU escrita neste projeto.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

/** As denúncias do arquivo de dados que declaram o trâmite passo a passo. */
function denunciasComTramiteDeclarado(): array
{
    return array_values(array_filter(
        (array) config('prototipo_denuncias.denuncias', []),
        static fn (array $d): bool => is_array($d['tramites'] ?? null) && $d['tramites'] !== [],
    ));
}

/** Um gestor de verdade: a matrícula é o que o liga à área na estrutura. */
function gestorDeArea(string $matricula): User
{
    $u = User::factory()->create(['login' => $matricula, 'admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'gestor')->firstOrFail());

    return $u->fresh();
}

function administrativoDoFluxo(): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'administrativo')->firstOrFail());

    return $u->fresh();
}

/** As denúncias que o servidor entrega a esta pessoa, num canal. */
function denunciasServidas(User $u, string $canal): array
{
    return test()->actingAs($u)
        ->get("/retaguarda/denuncias/{$canal}")
        ->viewData('page')['props']['denuncias'];
}

test('lei: o ultimo passo do tramite declarado casa com a situacao da denuncia', function () {
    /*
     * As duas informações são vizinhas no arquivo e dizem a mesma coisa, então
     * uma delas é redundante — e redundância em dado escrito à mão diverge. Não
     * derivamos a situação do último passo porque quem LÊ a config precisa ver a
     * situação junto da denúncia; então o guarda é este teste.
     */
    $divergentes = [];

    foreach (denunciasComTramiteDeclarado() as $d) {
        $ultimo = end($d['tramites']);
        $doPasso = (string) ($ultimo['situacao'] ?? '');

        if ($doPasso !== (string) $d['situacao']) {
            $divergentes[] = "DEN-{$d['id']}: situação '{$d['situacao']}', último passo '{$doPasso}'";
        }
    }

    expect($divergentes)->toBe([], "A situação da denúncia é a do ÚLTIMO passo do trâmite.\n");
});

test('lei: as horas do tramite nunca andam para tras', function () {
    $foraDeOrdem = [];

    foreach (denunciasComTramiteDeclarado() as $d) {
        $anterior = -1;

        foreach ($d['tramites'] as $passo) {
            $horas = (int) ($passo['ha_horas'] ?? 0);

            if ($horas < $anterior) {
                $foraDeOrdem[] = "DEN-{$d['id']}: '{$passo['o_que']}' em +{$horas}h depois de +{$anterior}h";
            }

            $anterior = $horas;
        }
    }

    expect($foraDeOrdem)->toBe([], "Trâmite é percurso: a hora de um passo não é menor que a do anterior.\n");
});

test('lei: todo passo declara um papel conhecido, uma situacao do catalogo e um desfecho do catalogo', function () {
    $situacoes = (array) config('prototipo_denuncias.situacoes', []);
    $desfechos = (array) config('prototipo_denuncias.desfechos', []);
    $problemas = [];

    foreach (denunciasComTramiteDeclarado() as $d) {
        foreach ($d['tramites'] as $passo) {
            $papel = (string) ($passo['quem'] ?? '');

            if (! in_array($papel, DenunciasFicticias::PAPEIS_DO_TRAMITE, true)) {
                $problemas[] = "DEN-{$d['id']}: papel desconhecido '{$papel}'";
            }

            $situacao = (string) ($passo['situacao'] ?? '');

            if ($situacao !== '' && ! in_array($situacao, $situacoes, true)) {
                $problemas[] = "DEN-{$d['id']}: situação fora do catálogo '{$situacao}'";
            }

            $desfecho = (string) ($passo['desfecho'] ?? '');

            if ($desfecho !== '' && ! in_array($desfecho, $desfechos, true)) {
                $problemas[] = "DEN-{$d['id']}: desfecho fora do catálogo '{$desfecho}'";
            }
        }
    }

    expect($problemas)->toBe([], "Papel, situação e desfecho saem de catálogo — não são texto livre.\n");
});

test('lei: o documento semeado referencia caixas, sancoes e prazos que existem no impresso', function () {
    /*
     * O documento declara CHAVES (`puxada`, `autuacao`, `48h`) e a redação sai de
     * `config/prototipo_documentos_campo.php`, que é o único dono dela. Chave
     * errada não estoura: ela simplesmente desaparece da lista, e o documento
     * chega à tela com menos motivos do que o fiscal marcou — a pior forma de
     * defeito num papel que instrui uma defesa.
     */
    $problemas = [];

    foreach (denunciasComTramiteDeclarado() as $d) {
        foreach ($d['tramites'] as $passo) {
            $doc = $passo['documento'] ?? null;

            if (! is_array($doc)) {
                continue;
            }

            $onde = "DEN-{$d['id']} · documento {$doc['numero']}";

            expect((string) $doc['tipo'])->toBeIn(['np', 'aa'], $onde);

            if ((string) $doc['tipo'] === 'np') {
                foreach ((array) ($doc['motivos'] ?? []) as $chave) {
                    if (config('prototipo_documentos_campo.motivos_np.'.$chave) === null) {
                        $problemas[] = "{$onde}: motivo inexistente '{$chave}'";
                    }
                }

                foreach ((array) ($doc['sancoes'] ?? []) as $chave) {
                    if (config('prototipo_documentos_campo.sancoes_np.'.$chave) === null) {
                        $problemas[] = "{$onde}: penalidade inexistente '{$chave}'";
                    }
                }

                if (config('prototipo_documentos_campo.prazos_np.'.((string) ($doc['prazo'] ?? ''))) === null) {
                    $problemas[] = "{$onde}: prazo inexistente '".((string) ($doc['prazo'] ?? ''))."'";
                }

                continue;
            }

            if (config('prototipo_documentos_campo.prazos_guarda.'.((string) ($doc['prazo_guarda'] ?? ''))) === null) {
                $problemas[] = "{$onde}: prazo de guarda inexistente";
            }

            if (config('prototipo_documentos_campo.destinacoes_guarda.'.((string) ($doc['destinacao'] ?? ''))) === null) {
                $problemas[] = "{$onde}: destinação de bens inexistente";
            }
        }
    }

    expect($problemas)->toBe([], "O documento referencia a redação do impresso por chave.\n");
});

test('lei: situacao de pos-vistoria obriga tramite declarado', function () {
    /*
     * O trâmite DERIVADO só sabe montar recebimento, triagem e direcionamento —
     * é o que a situação implica, e nada mais. Uma denúncia marcada como
     * "Concluída" sem trâmite declarado chegaria à tela com a linha do tempo
     * parando no gestor: o registro diria que a vistoria terminou e o percurso
     * não mostraria vistoria nenhuma.
     */
    $posVistoria = ['Aguardando regularização', 'Retorno vencido', 'Concluída'];
    $semTramite = [];

    foreach ((array) config('prototipo_denuncias.denuncias', []) as $d) {
        if (! in_array((string) $d['situacao'], $posVistoria, true)) {
            continue;
        }

        if (! is_array($d['tramites'] ?? null) || $d['tramites'] === []) {
            $semTramite[] = "DEN-{$d['id']} ({$d['situacao']})";
        }
    }

    expect($semTramite)->toBe(
        [],
        "Denúncia que já teve desfecho de campo declara o trâmite passo a passo.\n",
    );
});

test('quem agiu em cada passo e gente da estrutura de areas e equipes', function () {
    /*
     * O passo declara o papel; o nome sai da estrutura. O que se prova aqui é o
     * efeito: nenhum passo chega sem autor, e o autor de vistoria é fiscal DAQUELA
     * equipe — não um nome escrito à mão que sobreviveria à saída da pessoa.
     */
    $problemas = [];

    foreach (DenunciasFicticias::todas() as $d) {
        $equipe = EstruturaFicticia::equipeDoCodigo($d['equipe'] ?? null);
        $daEquipe = array_map(
            static fn (array $f): string => (string) $f['nome'],
            array_values((array) ($equipe['fiscais'] ?? [])),
        );

        foreach ($d['tramites'] as $passo) {
            if (trim((string) $passo['quem']) === '') {
                $problemas[] = "{$d['protocolo']}: passo '{$passo['o_que']}' sem autor";
            }

            // O passo que registrou campo foi feito por fiscal da equipe, e o
            // texto tem de nomear a pessoa (não "Equipe C1" genérico).
            if ($passo['campo'] === null) {
                continue;
            }

            $nomeou = false;

            foreach ($daEquipe as $nome) {
                if (str_contains((string) $passo['quem'], $nome)) {
                    $nomeou = true;
                }
            }

            if (! $nomeou) {
                $problemas[] = "{$d['protocolo']}: vistoria assinada por '{$passo['quem']}', "
                    .'que não é fiscal da equipe '.((string) ($d['equipe'] ?? '—'));
            }
        }
    }

    expect($problemas)->toBe([], "Vistoria tem autor, e o autor sai da estrutura.\n");
});

test('a notificacao com prazo correndo vence no futuro, e o retorno vencido acontece depois do vencimento', function () {
    /*
     * As datas são RELATIVAS ao "agora" justamente para o caso não envelhecer.
     * Este teste é o que garante que a intenção de cada caso continua valendo
     * amanhã: a denúncia semeada como "prazo correndo" tem de ter prazo NO FUTURO,
     * e a semeada como "retorno vencido" tem de ter o retorno DEPOIS do
     * vencimento — senão a demonstração conta a história errada.
     */
    $correndo = DenunciasFicticias::denuncia(29);
    $notificacao = collect($correndo['tramites'])->firstWhere('documento', '!=', null);

    expect($correndo['situacao'])->toBe('Aguardando regularização')
        ->and($notificacao['documento']['vence_em'])->toBeGreaterThan(now()->format('Y-m-d'));

    $vencido = DenunciasFicticias::denuncia(30);
    $passos = collect($vencido['tramites']);
    $venceEm = $passos->firstWhere('documento', '!=', null)['documento']['vence_em'];
    $retorno = $passos->last();

    expect($vencido['situacao'])->toBe('Retorno vencido')
        ->and($retorno['campo'])->not->toBeNull()
        ->and(substr((string) $retorno['em'], 0, 10))->toBeGreaterThan((string) $venceEm);
});

test('a amostra continua majoritariamente educativa: mais casos de campo sem documento do que com', function () {
    /*
     * A fiscalização é educativa antes de punitiva — a maioria dos casos termina
     * com o ambulante desmontando na presença do fiscal. Este teste protege a
     * INTENÇÃO da amostra: sem ele, cada caso novo com documento entraria sem que
     * ninguém percebesse que a demonstração passou a desenhar um sistema
     * punitivo, que não é o do cliente.
     */
    $comDocumento = 0;
    $semDocumento = 0;

    foreach (DenunciasFicticias::todas() as $d) {
        if ($d['desfecho'] === null) {
            continue;
        }

        $temDocumento = collect($d['tramites'])->contains(fn (array $p): bool => $p['documento'] !== null);
        $temDocumento ? $comDocumento++ : $semDocumento++;
    }

    expect($semDocumento)->toBeGreaterThan(0)
        ->and($comDocumento)->toBeGreaterThan(0, 'a leitura de documento precisa de caso para demonstrar')
        ->and($comDocumento)->toBeLessThanOrEqual($semDocumento + 1);
});

test('cada gestor com conta de demonstracao tem caso avancado nos dois canais', function () {
    /*
     * Sem isto a demonstração abre VAZIA para dois dos três gestores, e quem está
     * mostrando o sistema conclui que a tela está quebrada. É requisito da
     * demonstração, e por isso é teste — não recado no doc.
     */
    $emCampo = ['Em campo', 'Aguardando regularização', 'Retorno vencido', 'Concluída'];

    foreach (['gestor1', 'gestor2', 'gestor3'] as $matricula) {
        $areas = EstruturaFicticia::areasDoGestor($matricula);

        expect($areas)->not->toBe([], "{$matricula} não é gestor de área nenhuma");

        foreach (['e-salvador', 'fala-salvador'] as $canal) {
            $avancadas = array_filter(
                DenunciasFicticias::doCanal($canal),
                static fn (array $d): bool => in_array($d['area'] ?? null, $areas, true)
                    && in_array((string) $d['situacao'], $emCampo, true),
            );

            expect($avancadas)->not->toBe(
                [],
                "{$matricula} ({$areas[0]}) não tem caso avançado no canal {$canal}",
            );
        }
    }
});

test('o gestor recebe o tramite avancado da area dele, com o documento lavrado', function () {
    $servidas = denunciasServidas(gestorDeArea('gestor1'), 'e-salvador');

    $notificada = collect($servidas)->firstWhere('id', 29);

    expect($notificada)->not->toBeNull()
        ->and($notificada['desfecho'])->toBe('Notificação Preliminar emitida');

    $documento = collect($notificada['tramites'])->firstWhere('documento', '!=', null)['documento'];

    expect($documento['numero'])->toBe('194903')
        ->and($documento['tipo'])->toBe('np')
        // O que a leitura na Retaguarda precisa mostrar: as caixas assinaladas e
        // as assinaturas com o estado de cada uma.
        ->and($documento['listas'][0]['itens'])->toContain('Retirar puxada, sanitário e depósito')
        ->and($documento['assinaturas'][0]['estado'])->toBe('assinada')
        ->and($documento['agente'])->toContain('matrícula F-2504');
});

test('o gestor de outra area nao recebe nem a linha nem o conteudo do tramite alheio', function () {
    /*
     * O vazamento que importa aqui não é o de uma linha de grade: é o do RELATO,
     * das fotos e do documento — nome do notificado, inscrição, número da
     * notificação. Tudo isso viaja DENTRO da denúncia, então provar que a linha
     * não aparece não basta: o teste procura o número do documento no corpo
     * inteiro da resposta.
     */
    $gestor2 = gestorDeArea('gestor2');

    $servidas = denunciasServidas($gestor2, 'e-salvador');
    $ids = array_column($servidas, 'id');

    // 29 é da Área 5 (gestor1); 13 é da Área 1, dele.
    expect($ids)->not->toContain(29)
        ->and($ids)->toContain(13);

    $this->actingAs($gestor2)
        ->get('/retaguarda/denuncias/e-salvador')
        ->assertDontSee('194903')
        ->assertDontSee('Jailson Pereira dos Santos');
});

test('quem tria ve o universo, com os casos avancados de todas as areas', function () {
    $ids = array_column(denunciasServidas(administrativoDoFluxo(), 'fala-salvador'), 'id');

    // 27 é da Área 4, que não tem gestor com conta: só o administrativo e o
    // administrador a enxergam, e é isso que faz dela a prova do recorte.
    expect($ids)->toContain(27, 30, 32, 33);
});

test('o catalogo de desfechos chega a tela, para a busca reconhecer a faceta', function () {
    $props = $this->actingAs(administrativoDoFluxo())
        ->get('/retaguarda/denuncias/e-salvador')
        ->viewData('page')['props'];

    expect($props['desfechos'])->toBe((array) config('prototipo_denuncias.desfechos'));
});

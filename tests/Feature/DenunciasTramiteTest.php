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
| 2. **O recorte do chefe de setor alcança o CONTEÚDO, não só a linha.** O trâmite
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

/** Um chefe de setor de verdade: a matrícula é o que o liga à área na estrutura. */
function chefeDeArea(string $matricula): User
{
    $u = User::factory()->create(['login' => $matricula, 'admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    return $u->fresh();
}

function coordenadorDoFluxo(): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'coordenador')->firstOrFail());

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

test('lei: o passo que fecha a vistoria traz a leitura do fiscal, e as recomendacoes saem do catalogo', function () {
    /*
     * O desfecho diz COMO a vistoria terminou; as considerações e as recomendações
     * dizem o que o fiscal está PEDINDO — e é por elas que o Chefe de Setor e o
     * Coordenador decidem o próximo ato. Passo de desfecho sem nenhuma das duas
     * chega à fila do Chefe de Setor mudo: ele lê "nada encontrado no local" e não
     * sabe se é caso de voltar em outro horário ou de encerrar.
     *
     * A recomendação sai de CATÁLOGO porque é o que o relatório soma ("quantas
     * vistorias pediram nova ida") e porque é o contrato com o aplicativo do
     * fiscal: atalho escrito à mão aqui seria uma recomendação que a Retaguarda
     * não sabe ler.
     */
    $catalogo = (array) config('prototipo_denuncias.recomendacoes_do_fiscal', []);
    $problemas = [];

    expect($catalogo)->not->toBe([], 'o catálogo de recomendações não pode estar vazio');

    foreach (denunciasComTramiteDeclarado() as $d) {
        foreach ($d['tramites'] as $passo) {
            $recomendacoes = array_values((array) ($passo['recomendacoes'] ?? []));

            foreach ($recomendacoes as $recomendacao) {
                if (! in_array((string) $recomendacao, $catalogo, true)) {
                    $problemas[] = "DEN-{$d['id']}: recomendação fora do catálogo '{$recomendacao}'";
                }
            }

            if (trim((string) ($passo['desfecho'] ?? '')) === '') {
                continue;
            }

            $consideracoes = trim((string) ($passo['consideracoes'] ?? ''));

            if ($consideracoes === '' && $recomendacoes === []) {
                $problemas[] = "DEN-{$d['id']}: o passo '{$passo['o_que']}' declara desfecho e "
                    .'não diz nada ao Chefe de Setor (sem considerações e sem recomendação)';
            }
        }
    }

    expect($problemas)->toBe([], "Todo desfecho vem com a leitura do fiscal, e a recomendação sai de catálogo.\n");
});

test('as consideracoes e as recomendacoes chegam a tela dentro do passo do tramite', function () {
    /*
     * O contrato com o aplicativo do fiscal são os NOMES `consideracoes` e
     * `recomendacoes`, e o que se prova aqui é o efeito: eles atravessam o
     * servidor e chegam ao passo, declarados em TODO passo (nulo e vazio nos que
     * não os produziram) para a tela não precisar de leitura defensiva.
     */
    $servidas = denunciasServidas(chefeDeArea('gestor1'), 'fala-salvador');
    $vencida = collect($servidas)->firstWhere('id', 30);

    expect($vencida)->not->toBeNull();

    $passos = collect($vencida['tramites']);

    // Todo passo declara as duas chaves — inclusive o de integração.
    foreach ($passos as $passo) {
        expect($passo)->toHaveKeys(['consideracoes', 'recomendacoes']);
    }

    $retorno = $passos->last();

    expect($retorno['desfecho'])->toBe('Retorno com a situação mantida')
        ->and($retorno['consideracoes'])->toContain('não vai tirar')
        ->and($retorno['recomendacoes'])->toContain('Encaminhar ao Chefe de Setor para a próxima medida')
        // O passo de recebimento não produziu leitura de fiscal nenhuma, e diz isso
        // com valor neutro em vez de chave ausente.
        ->and($passos->first()['consideracoes'])->toBeNull()
        ->and($passos->first()['recomendacoes'])->toBe([]);
});

test('a linha de tramite criada por uma decisao nasce com TODAS as chaves do passo', function () {
    /*
     * Reproduz um defeito real: a linha acrescentada por uma decisão da tela era
     * montada à mão, sem `campos`, `campo`, `documento`, `consideracoes` nem
     * `recomendacoes`. A leitura do trâmite testa `t.documento !== null` — que é
     * VERDADEIRO para chave ausente —, então abrir a denúncia recém-triada
     * derrubava a tela, logo depois de a pessoa decidir algo e sem erro nenhum no
     * servidor para investigar.
     */
    $coordenador = coordenadorDoFluxo();

    $this->actingAs($coordenador)
        ->post('/retaguarda/denuncias/encaminhar', [
            'destinos' => [['id' => 1, 'area' => 'Área 1']],
        ])
        ->assertRedirect();

    $ultimo = collect(DenunciasFicticias::denuncia(1)['tramites'])->last();

    expect($ultimo['o_que'])->toBe('Triada e encaminhada à área')
        ->and($ultimo)->toHaveKeys([
            'em', 'quem', 'o_que', 'detalhe', 'situacao', 'desfecho',
            'consideracoes', 'recomendacoes', 'campos', 'campo', 'documento',
        ])
        ->and($ultimo['documento'])->toBeNull()
        ->and($ultimo['campo'])->toBeNull()
        ->and($ultimo['campos'])->toBe([])
        ->and($ultimo['recomendacoes'])->toBe([])
        // E o passo novo carrega a situação em que a denúncia entrou: sem ela, o
        // único passo do percurso sem selo seria justamente o que acabou de
        // acontecer.
        ->and($ultimo['situacao'])->toBe('Encaminhada à área');
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
     * parando no chefe de setor: o registro diria que a vistoria terminou e o percurso
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

test('lei: a devolucao e o arquivamento declarados repetem o motivo de catalogo e a justificativa do resumo', function () {
    /*
     * O motivo e a justificativa de uma denúncia recusada vivem em DOIS lugares:
     * no resumo dela (campos `motivo`/`justificativa`, que a ficha lê) e no passo
     * do trâmite que os produziu (que a leitura do percurso mostra). É a mesma
     * informação com dois donos — o que este projeto proíbe —, e a saída aqui é a
     * mesma da situação × último passo: em vez de derivar um do outro (quem lê a
     * config precisa ver os dois), o guarda é este teste. Sem ele, corrigir a
     * justificativa no resumo deixaria o trâmite contando a versão antiga.
     */
    $catalogo = (array) config('prototipo_denuncias.motivos_de_devolucao', []);
    $problemas = [];

    foreach (denunciasComTramiteDeclarado() as $d) {
        if (! in_array((string) $d['situacao'], ['Devolvida', 'Arquivada'], true)) {
            continue;
        }

        $ultimo = end($d['tramites']);
        $valores = array_map(
            static fn (array $c): string => (string) $c['valor'],
            array_values((array) ($ultimo['campos'] ?? [])),
        );

        if (! in_array((string) $d['motivo'], $catalogo, true)) {
            $problemas[] = "DEN-{$d['id']}: motivo fora do catálogo — '{$d['motivo']}'";
        }

        if (! in_array((string) $d['motivo'], $valores, true)) {
            $problemas[] = "DEN-{$d['id']}: o passo da triagem não repete o motivo do resumo";
        }

        if (! in_array((string) $d['justificativa'], $valores, true)) {
            $problemas[] = "DEN-{$d['id']}: o passo da triagem não repete a justificativa do resumo";
        }
    }

    expect($problemas)->toBe([], "Motivo e justificativa do resumo e do passo são o MESMO texto.\n");
});

test('lei: a denuncia em campo e vistoria em andamento — sem desfecho e sem documento', function () {
    /*
     * "Em campo" é o estado em que a equipe está na rua e o desfecho ainda não
     * foi decidido. Uma denúncia nesse estado com desfecho preenchido diria à
     * tela duas coisas contraditórias — o selo cobrando vistoria e a ficha
     * mostrando como ela terminou —, e o mesmo vale para o passo: documento
     * lavrado num passo de vistoria em andamento prometeria papel que não existe.
     */
    $problemas = [];

    foreach (DenunciasFicticias::todas() as $d) {
        if ((string) $d['situacao'] === 'Em campo' && $d['desfecho'] !== null) {
            $problemas[] = "{$d['protocolo']}: em campo, mas já com desfecho '{$d['desfecho']}'";
        }
    }

    $emAndamento = 0;

    foreach (denunciasComTramiteDeclarado() as $d) {
        if ((string) $d['situacao'] !== 'Em campo') {
            continue;
        }

        $emAndamento++;
        $ultimo = end($d['tramites']);

        if (! is_array($ultimo['campo'] ?? null)) {
            $problemas[] = "DEN-{$d['id']}: em campo, mas o último passo não traz registro de campo";
        }

        if (($ultimo['desfecho'] ?? null) !== null || ($ultimo['documento'] ?? null) !== null) {
            $problemas[] = "DEN-{$d['id']}: a vistoria em andamento declarou desfecho ou documento";
        }
    }

    expect($problemas)->toBe([], "Vistoria começada e ainda sem conclusão.\n")
        // O estado precisa EXISTIR na amostra: é o que a demonstração usa para
        // mostrar a equipe na rua, e ele não aparece em nenhum outro caso.
        ->and($emAndamento)->toBeGreaterThan(0, 'a amostra precisa de vistoria em andamento para demonstrar');
});

test('lei: o numero do documento sai da faixa do bloco de papel e nao se repete', function () {
    /*
     * Os números vêm das faixas dos blocos que o cliente entregou — `1949xx` para
     * a Notificação Preliminar e `1600xx` para o Auto de Apreensão. Número
     * repetido é o defeito mais silencioso possível: dois documentos diferentes
     * com a mesma identidade, e a leitura de um deles apontando para o outro.
     */
    $vistos = [];
    $problemas = [];

    foreach (denunciasComTramiteDeclarado() as $d) {
        foreach ($d['tramites'] as $passo) {
            $doc = $passo['documento'] ?? null;

            if (! is_array($doc)) {
                continue;
            }

            $numero = (string) ($doc['numero'] ?? '');
            $faixa = (string) $doc['tipo'] === 'np' ? '/^1949\d{2}$/' : '/^1600\d{2}$/';

            if (preg_match($faixa, $numero) !== 1) {
                $problemas[] = "DEN-{$d['id']}: número '{$numero}' fora da faixa do bloco de {$doc['tipo']}";
            }

            if (isset($vistos[$numero])) {
                $problemas[] = "DEN-{$d['id']}: número '{$numero}' repetido (já usado em DEN-{$vistos[$numero]})";
            }

            $vistos[$numero] = (int) $d['id'];
        }
    }

    expect($problemas)->toBe([], "Cada documento tem o seu número, dentro da faixa do bloco.\n");
});

test('lei: o bairro da denuncia pertence a area que a recebeu', function () {
    /*
     * A área sai do BAIRRO (a derivação da estrutura sugere, e a triagem
     * confirma), então uma denúncia semeada com a área de outro bairro faz a tela
     * mostrar um encaminhamento que o sistema real não produziria — e o chefe de setor
     * daquela área receberia caso que não é dele. Bairro compartilhado continua
     * valendo: a área pode ser a sugerida OU qualquer das alternativas.
     */
    $problemas = [];

    foreach (DenunciasFicticias::todas() as $d) {
        $area = $d['area'] ?? null;

        if ($area === null) {
            continue;
        }

        $sugestao = EstruturaFicticia::sugerirPorBairro((string) $d['bairro']);
        $possiveis = $sugestao === null ? [] : [
            (string) $sugestao['area'],
            ...array_map(
                static fn (array $a): string => (string) $a['area'],
                array_values((array) $sugestao['alternativas']),
            ),
        ];

        if (! in_array((string) $area, $possiveis, true)) {
            $problemas[] = "{$d['protocolo']}: bairro '{$d['bairro']}' não pertence à {$area}";
        }
    }

    expect($problemas)->toBe([], "A área da denúncia é uma das que cobrem o bairro dela.\n");
});

test('lei: a operacao anexada existe no catalogo, e a equipe da denuncia e a da operacao', function () {
    /*
     * Anexar a uma operação faz a equipe DELA ser a responsável — é o que
     * `anexarAOperacao()` grava. Dado semeado com outra equipe mostraria dois
     * responsáveis na mesma denúncia, e o nome de operação escrito à mão que não
     * existe no catálogo simplesmente não casaria com nada.
     */
    $catalogo = [];

    foreach ((array) config('prototipo_denuncias.operacoes', []) as $operacao) {
        $catalogo[(string) $operacao['nome']] = (string) $operacao['equipe'];
    }

    $problemas = [];

    foreach (DenunciasFicticias::todas() as $d) {
        $operacao = $d['operacao'] ?? null;

        if ($operacao === null) {
            continue;
        }

        if (! array_key_exists((string) $operacao, $catalogo)) {
            $problemas[] = "{$d['protocolo']}: operação '{$operacao}' não existe no catálogo";

            continue;
        }

        if ((string) $d['equipe'] !== $catalogo[(string) $operacao]) {
            $problemas[] = "{$d['protocolo']}: equipe '{$d['equipe']}' não é a da operação "
                ."'{$operacao}' (Equipe {$catalogo[(string) $operacao]})";
        }
    }

    expect($problemas)->toBe([], "A denúncia em operação é executada pela equipe da operação.\n");
});

test('lei: toda notificacao em prazo corre para o futuro, e todo retorno vencido acontece depois do vencimento', function () {
    /*
     * A versão GERAL do que o teste dos dois casos de demonstração garante para a
     * DEN-0029 e a DEN-0030: vale para o caso que alguém acrescentar amanhã. As
     * horas do dado são relativas justamente para o caso não envelhecer, e é aqui
     * que se prova que a intenção de cada um continua valendo — uma notificação
     * semeada como "prazo correndo" com o prazo já vencido conta a história
     * errada, e ninguém percebe olhando o arquivo.
     */
    $hoje = now()->format('Y-m-d');
    $problemas = [];

    foreach (DenunciasFicticias::todas() as $d) {
        $situacao = (string) $d['situacao'];

        if (! in_array($situacao, ['Aguardando regularização', 'Retorno vencido'], true)) {
            continue;
        }

        $comDocumento = collect($d['tramites'])
            ->filter(static fn (array $p): bool => $p['documento'] !== null)
            ->last();

        if ($comDocumento === null) {
            $problemas[] = "{$d['protocolo']}: {$situacao} sem notificação lavrada no trâmite";

            continue;
        }

        $vence = (string) ($comDocumento['documento']['vence_em'] ?? '');

        if ($vence === '') {
            $problemas[] = "{$d['protocolo']}: a notificação não tem vencimento";

            continue;
        }

        if ($situacao === 'Aguardando regularização' && $vence <= $hoje) {
            $problemas[] = "{$d['protocolo']}: prazo correndo, mas o vencimento {$vence} já passou";
        }

        if ($situacao === 'Retorno vencido') {
            $retorno = substr((string) collect($d['tramites'])->last()['em'], 0, 10);

            if ($retorno <= $vence) {
                $problemas[] = "{$d['protocolo']}: o retorno ({$retorno}) não é posterior ao vencimento ({$vence})";
            }
        }
    }

    expect($problemas)->toBe([], "Prazo correndo vence no futuro; retorno vencido acontece depois do vencimento.\n");
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

test('cada chefe de setor com conta de demonstracao tem caso avancado nos dois canais', function () {
    /*
     * Sem isto a demonstração abre VAZIA para dois dos três chefes de setor, e quem está
     * mostrando o sistema conclui que a tela está quebrada. É requisito da
     * demonstração, e por isso é teste — não recado no doc.
     */
    $emCampo = ['Em campo', 'Aguardando regularização', 'Retorno vencido', 'Concluída'];

    foreach (['gestor1', 'gestor2', 'gestor3'] as $matricula) {
        $areas = EstruturaFicticia::areasDoChefe($matricula);

        expect($areas)->not->toBe([], "{$matricula} não é chefe de setor de área nenhuma");

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

test('o chefe de setor recebe o tramite avancado da area dele, com o documento lavrado', function () {
    $servidas = denunciasServidas(chefeDeArea('gestor1'), 'e-salvador');

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

test('o chefe de setor de outra area nao recebe nem a linha nem o conteudo do tramite alheio', function () {
    /*
     * O vazamento que importa aqui não é o de uma linha de grade: é o do RELATO,
     * das fotos e do documento — nome do notificado, inscrição, número da
     * notificação. Tudo isso viaja DENTRO da denúncia, então provar que a linha
     * não aparece não basta: o teste procura o número do documento no corpo
     * inteiro da resposta.
     */
    $chefe2 = chefeDeArea('gestor2');

    $servidas = denunciasServidas($chefe2, 'e-salvador');
    $ids = array_column($servidas, 'id');

    // 29 é da Área 5 (gestor1); 13 é da Área 1, dele.
    expect($ids)->not->toContain(29)
        ->and($ids)->toContain(13);

    $this->actingAs($chefe2)
        ->get('/retaguarda/denuncias/e-salvador')
        ->assertDontSee('194903')
        ->assertDontSee('Jailson Pereira dos Santos');
});

test('quem tria ve o universo, com os casos avancados de todas as areas', function () {
    $ids = array_column(denunciasServidas(coordenadorDoFluxo(), 'fala-salvador'), 'id');

    // 27 é da Área 4, que não tem chefe de setor com conta: só o coordenador e o
    // administrador a enxergam, e é isso que faz dela a prova do recorte.
    expect($ids)->toContain(27, 30, 32, 33);
});

test('o catalogo de desfechos chega a tela, para a busca reconhecer a faceta', function () {
    $props = $this->actingAs(coordenadorDoFluxo())
        ->get('/retaguarda/denuncias/e-salvador')
        ->viewData('page')['props'];

    expect($props['desfechos'])->toBe((array) config('prototipo_denuncias.desfechos'));
});

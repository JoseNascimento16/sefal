<?php

use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Higiene do repositório
|--------------------------------------------------------------------------
|
| Este repositório é ENTREGUE ao cliente e AUDITADO pela Qualidade. O ferramental de trabalho
| (instruções de IA, skills, scripts de máquina), a documentação interna e o backlog vivem na
| branch `ferramental` — não aqui.
|
| A regra em prosa não segura nada: `git add -A` leva o que estiver no disco. Por isso são TRÊS
| guardas sobre a MESMA lista (`.higiene-proibidos`, fonte única), cada um pegando num momento
| diferente:
|   1. `.githooks/pre-commit` → barra antes de o arquivo entrar no commit (o mais barato)
|   2. `.githooks/pre-push`   → barra antes de subir, inclusive o que veio de outra máquina
|   3. este teste            → entra na validação junto com os demais, e no CI
|
*/

/** Lista os caminhos que estão no ÍNDICE do git (não o que está no disco). */
$rastreados = function (): array {
    $processo = new Process(['git', 'ls-files'], base_path());
    $processo->run();

    if (! $processo->isSuccessful()) {
        test()->markTestSkipped('git indisponível neste ambiente: '.trim($processo->getErrorOutput()));
    }

    return array_values(array_filter(array_map('trim', explode("\n", $processo->getOutput()))));
};

/**
 * Lê `.higiene-proibidos` — a MESMA lista dos hooks. Formato: `<caminho> | <motivo>`.
 *
 * @return array<string, string> caminho => motivo
 */
$proibidos = function (): array {
    $arquivo = base_path('.higiene-proibidos');

    expect($arquivo)->toBeFile('A lista de caminhos proibidos é a fonte única dos guardas de higiene.');

    $regras = [];

    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
        $linha = trim($linha);

        if ($linha === '' || str_starts_with($linha, '#')) {
            continue;
        }

        [$caminho, $motivo] = array_pad(explode('|', $linha, 2), 2, '');
        $caminho = rtrim(trim($caminho), '/');

        if ($caminho !== '') {
            $regras[$caminho] = trim($motivo);
        }
    }

    expect($regras)->not->toBeEmpty('A lista está vazia — algum guarda foi esvaziado por engano.');

    return $regras;
};

/** O caminho proibido pode ser um arquivo, uma pasta inteira ou um glob (`database/*.sqlite`). */
$casa = fn (string $arquivo, string $padrao): bool => $arquivo === $padrao
    || str_starts_with($arquivo, $padrao.'/')
    || fnmatch($padrao, $arquivo);

test('nenhum caminho proibido esta rastreado no git', function () use ($rastreados, $proibidos, $casa) {
    $arquivos = $rastreados();

    foreach ($proibidos() as $padrao => $motivo) {
        $violacoes = array_values(array_filter($arquivos, fn (string $a): bool => $casa($a, $padrao)));

        expect($violacoes)->toBe([], sprintf(
            "`%s` não pode ser versionado: %s.\n".
            "Para tirar do commit mantendo o arquivo no disco: `git rm --cached <arquivo>`.\n".
            'Se a decisão MUDOU, ajuste `.higiene-proibidos` no MESMO commit, com o motivo.',
            $padrao,
            $motivo !== '' ? $motivo : 'sem motivo declarado na lista'
        ));
    }
})->group('higiene');

test('todo caminho proibido tambem esta no gitignore', function () use ($proibidos) {
    // Sem estar no `.gitignore`, o arquivo aparece como não rastreado em todo `git status` e um
    // `git add -A` distraído o coloca no índice — o hook reprova, mas depois do susto.
    $gitignore = array_map(
        fn (string $l): string => rtrim(ltrim(trim($l), '/'), '/'),
        file(base_path('.gitignore'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
    );

    foreach (array_keys($proibidos()) as $padrao) {
        $this->assertContains($padrao, $gitignore, "O caminho proibido `{$padrao}` precisa estar no .gitignore também.");
    }
})->group('higiene');

test('os guardas de higiene continuam no lugar', function () {
    // Os guardas só protegem enquanto existirem: se um sair, a lista deixa de ser aplicada.
    foreach (['.githooks/checar-higiene.sh', '.githooks/pre-commit', '.githooks/pre-push'] as $guarda) {
        expect(base_path($guarda))->toBeFile("O guarda `{$guarda}` é o que bloqueia na máquina, antes do push.");
    }

    $this->assertStringContainsString(
        '.higiene-proibidos',
        (string) file_get_contents(base_path('.githooks/checar-higiene.sh')),
        'A checagem precisa ler a lista, e não uma cópia própria.'
    );

    foreach (['pre-commit', 'pre-push'] as $hook) {
        $this->assertStringContainsString(
            'checar-higiene.sh',
            (string) file_get_contents(base_path(".githooks/{$hook}")),
            "O hook `{$hook}` precisa chamar a mesma checagem — fonte única."
        );
    }
})->group('higiene');

test('o pre-push bloqueia as branches protegidas', function () {
    // Não basta a palavra aparecer no arquivo (um comentário contaria): a lista que o hook
    // percorre é a variável `protegidas`, e é ela que precisa nomear as três branches.
    $hook = (string) file_get_contents(base_path('.githooks/pre-push'));

    expect($hook)->toMatch('/^protegidas="([^"]+)"$/m');
    preg_match('/^protegidas="([^"]+)"$/m', $hook, $achado);
    $protegidas = preg_split('/\s+/', trim($achado[1])) ?: [];

    foreach (['main', 'develop', 'homolog'] as $branch) {
        $this->assertContains($branch, $protegidas, "O pre-push precisa bloquear push direto para `{$branch}`.");
    }
})->group('higiene');

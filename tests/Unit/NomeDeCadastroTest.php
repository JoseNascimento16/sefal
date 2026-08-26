<?php

use App\Rules\NomeDeCadastro;

/*
|--------------------------------------------------------------------------
| NomeDeCadastro — nome de gente e razão social, sem markup
|--------------------------------------------------------------------------
|
| A regra é uma ALLOWLIST, e o equilíbrio dela é o assunto destes testes: larga
| o bastante para caber um nome real (que tem acento, apóstrofo, hífen, vírgula
| e E comercial) e estreita o bastante para não deixar passar o que o valor
| carrega para fora da tela — relatório, planilha, documento, nome de arquivo e
| a URL de download, que o WAF da Prefeitura inspeciona.
|
| Recusar demais não é o lado seguro: obriga quem cadastra a adulterar o nome
| para o formulário aceitar, e aí o cadastro deixa de bater com o documento.
|
*/

/** A regra aceita este texto? */
function nomeAceito(string $nome): bool
{
    $recusas = [];

    (new NomeDeCadastro)->validate('nome', $nome, function (string $mensagem) use (&$recusas): void {
        $recusas[] = $mensagem;
    });

    return $recusas === [];
}

test('nome de gente passa — acento, apostrofo, hifen, ponto e numero', function () {
    expect(nomeAceito('João da Silva'))->toBeTrue()
        ->and(nomeAceito('Ana D\'Ávila'))->toBeTrue()
        ->and(nomeAceito('Maria-José'))->toBeTrue()
        ->and(nomeAceito('J. Carlos'))->toBeTrue()
        // Apelido com número é comum em rua.
        ->and(nomeAceito('Zé 2'))->toBeTrue();
});

test('razao social passa — o campo que usa esta regra aceita CNPJ', function () {
    /*
     * Pessoa jurídica também é permissionário, e razão social usa vírgula e E
     * comercial o tempo todo. Recusá-las obrigava a alterar a razão social para
     * o cadastro passar — o nome deixava de bater com o documento.
     *
     * Nenhum dos dois sinais é assinatura de SQL para o WAF nem abre marcação
     * HTML, então admiti-los não afrouxa nada do que a regra existe para barrar
     * (ver o teste seguinte).
     */
    expect(nomeAceito('Silva & Filhos Ltda'))->toBeTrue()
        ->and(nomeAceito('José da Silva, ME'))->toBeTrue()
        ->and(nomeAceito('Comercio de Alimentos Boa Vista, EIRELI'))->toBeTrue();
});

test('markup nao passa — o valor sai por portas que nao escapam nada', function () {
    // Hoje não executa, porque o React escapa o que renderiza. Mas o valor fica
    // GRAVADO e sai por outras portas; confiar na renderização é apostar que
    // nenhum consumidor futuro será menos cuidadoso.
    expect(nomeAceito('<img src=x onerror=alert(1)>'))->toBeFalse()
        ->and(nomeAceito('Maria <b>Silva</b>'))->toBeFalse();
});

test('a assinatura que o WAF barra na URL nao passa', function () {
    // O nome vai para a URL de download. Um `--` grava sem reclamar e depois faz
    // a requisição voltar como erro de CORS, sem ninguém entender por quê.
    expect(nomeAceito('Silva -- Filhos'))->toBeFalse()
        ->and(nomeAceito('Maria "Preta" Silva'))->toBeFalse()
        ->and(nomeAceito('Silva; DROP'))->toBeFalse()
        ->and(nomeAceito('Silva/Souza'))->toBeFalse();

    // A APÓSTROFO fica de fora desta lista de propósito: ela é sinal de nome
    // próprio (`Ana D'Ávila`, `Sant'Ana`) e recusá-la deixaria gente de fora do
    // cadastro. Quem o WAF barra é a aspa dupla, acima.
    expect(nomeAceito("Maria 'Preta' Silva"))->toBeTrue();
});

test('hifen simples passa, hifen duplo nao — a diferenca e a assinatura', function () {
    expect(nomeAceito('Maria-José'))->toBeTrue()
        ->and(nomeAceito('Maria--José'))->toBeFalse();
});

test('caractere invisivel nao passa — faz dois cadastros parecerem o mesmo nome', function () {
    expect(nomeAceito("João\tda Silva"))->toBeFalse()
        ->and(nomeAceito("João\nda Silva"))->toBeFalse()
        ->and(nomeAceito("João\u{200B}da Silva"))->toBeFalse();
});

test('a recusa DIZ o que e aceito, em vez de "formato invalido"', function () {
    // Quem digitou o nome da pessoa precisa saber o que corrigir.
    $recusas = [];

    (new NomeDeCadastro)->validate('nome', 'Maria <b>Silva</b>', function (string $mensagem) use (&$recusas): void {
        $recusas[] = $mensagem;
    });

    expect($recusas)->toHaveCount(1)
        ->and($recusas[0])->toContain('letras')
        ->and($recusas[0])->toContain('vírgula');
});

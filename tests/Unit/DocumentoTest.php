<?php

use App\Support\Documento;

/**
 * CNPJ ALFANUMÉRICO (formato da Receita vigente desde 2026) — as 12 primeiras posições aceitam
 * letras; só os 2 dígitos verificadores continuam numéricos. O CPF não mudou.
 *
 * A lei do projeto: nenhum campo que possa conter CNPJ passa por `preg_replace('/\D/','')` — o
 * strip apaga as letras e corrompe o documento em silêncio. Estes vetores são a prova viva disso.
 * `12ABC34501DE35` é o exemplo oficial Receita/Serpro, com DV correto; `11222333000181` é o
 * numérico antigo, que tem de continuar valendo.
 */
test('normaliza CNPJ alfanumerico sem apagar letras', function () {
    expect(Documento::normalizar('12.ABC.345/01DE-35'))->toBe('12ABC34501DE35');
});

test('cpf continua so digitos', function () {
    expect(Documento::normalizar('123.456.789-09'))->toBe('12345678909');
});

test('normalizar sobe as letras para maiusculo', function () {
    expect(Documento::normalizar('12.abc.345/01de-35'))->toBe('12ABC34501DE35')
        ->and(Documento::normalizar('12ABC34501DE35'))->toBe('12ABC34501DE35')
        ->and(Documento::normalizar(null))->toBe('');
});

test('valida o CNPJ alfanumerico oficial com e sem mascara', function () {
    expect(Documento::cnpjValido('12ABC34501DE35'))->toBeTrue()
        ->and(Documento::cnpjValido('12.ABC.345/01DE-35'))->toBeTrue()
        ->and(Documento::cnpjValido('12abc34501de35'))->toBeTrue();
});

test('recusa CNPJ alfanumerico com DV errado ou DV nao numerico', function () {
    expect(Documento::cnpjValido('12ABC34501DE36'))->toBeFalse()
        ->and(Documento::cnpjValido('12ABC34501DEAB'))->toBeFalse();
});

test('segue compativel com o CNPJ numerico antigo', function () {
    expect(Documento::cnpjValido('11222333000181'))->toBeTrue()
        ->and(Documento::cnpjValido('11.222.333/0001-81'))->toBeTrue()
        ->and(Documento::cnpjValido('11222333000182'))->toBeFalse()
        ->and(Documento::cnpjValido('11111111111111'))->toBeFalse();
});

test('cpf permanece intacto', function () {
    expect(Documento::cpfValido('52998224725'))->toBeTrue()
        ->and(Documento::valido('529.982.247-25'))->toBeTrue()
        ->and(Documento::cpfValido('52998224726'))->toBeFalse()
        ->and(Documento::ehCpf('52998224725'))->toBeTrue()
        ->and(Documento::ehCnpj('52998224725'))->toBeFalse();
});

test('ehCnpj e tipoPessoa reconhecem o alfanumerico', function () {
    expect(Documento::ehCnpj('12ABC34501DE35'))->toBeTrue()
        ->and(Documento::ehCnpj('12.ABC.345/01DE-35'))->toBeTrue()
        ->and(Documento::tipoPessoa('12ABC34501DE35'))->toBe('PJ')
        ->and(Documento::tipoPessoa('52998224725'))->toBe('PF');
});

test('formatar posiciona os separadores aceitando letras', function () {
    expect(Documento::formatar('12ABC34501DE35'))->toBe('12.ABC.345/01DE-35')
        ->and(Documento::formatar('11222333000181'))->toBe('11.222.333/0001-81')
        ->and(Documento::formatar('52998224725'))->toBe('529.982.247-25')
        ->and(Documento::formatar('123'))->toBe('123');
});

test('valido roteia pelo tamanho normalizado', function () {
    expect(Documento::valido('12ABC34501DE35'))->toBeTrue()
        ->and(Documento::valido('11222333000181'))->toBeTrue()
        ->and(Documento::valido('52998224725'))->toBeTrue()
        ->and(Documento::valido('123'))->toBeFalse();
});

test('pareceDocumento distingue documento de nome digitado', function () {
    expect(Documento::pareceDocumento('12ABC34501DE35'))->toBeTrue()
        ->and(Documento::pareceDocumento('12.ABC.345/01DE-35'))->toBeTrue()
        ->and(Documento::pareceDocumento('52998224725'))->toBeTrue()
        ->and(Documento::pareceDocumento('Padaria Central'))->toBeFalse()
        ->and(Documento::pareceDocumento('ABCDEFGHIJKLMN'))->toBeFalse();
});

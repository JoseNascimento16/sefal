<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Um nome de cadastro — nome de pessoa, apelido, nome de guerra ou razão social.
 *
 * Aceita **letras** (com acento, de qualquer alfabeto), **números** (há apelido
 * com número: "Zé 2"), **espaço** e a pontuação que nome de cadastro realmente
 * usa: ponto, apóstrofo e hífen (`Ana D'Ávila`, `Maria-José`, `J. Carlos`) mais
 * **vírgula e E comercial**.
 *
 * ⚠️ A vírgula e o `&` estão aqui porque o campo que usa esta regra aceita
 * **CNPJ**, e portanto guarda PESSOA JURÍDICA. Razão social é escrita com eles o
 * tempo todo — `SILVA & FILHOS LTDA`, `JOSÉ DA SILVA, ME` —, e recusá-los
 * obrigava quem cadastrava a alterar a razão social para o cadastro passar: o
 * nome deixava de bater com o documento que ele representa. Nenhum dos dois é
 * assinatura de SQL para o WAF nem abre marcação HTML, então admiti-los não
 * afrouxa nada do que a regra existe para barrar.
 *
 * Recusa o resto — e isso não é purismo:
 *
 *  - **markup** (`<img src=x onerror=…>`). Hoje não executa, porque o React
 *    escapa tudo o que renderiza, mas o valor fica GRAVADO e sai por outras
 *    portas: relatório, planilha, documento, nome de arquivo. Confiar na
 *    renderização é apostar que nenhum consumidor futuro será menos cuidadoso;
 *  - **`--` e aspas**. As apps da Prefeitura ficam atrás de um WAF que inspeciona
 *    a URL e barra assinatura de SQL. Um nome com `--` grava sem reclamar e
 *    depois faz a requisição que o carregue voltar como erro de CORS, sem
 *    ninguém entender por quê;
 *  - **caractere invisível** (controle, tabulação, nova linha), que faz dois
 *    cadastros parecerem o mesmo nome e não casarem em busca nenhuma.
 *
 * A recusa DIZ o que é aceito, em vez de "formato inválido": quem digitou o nome
 * da pessoa precisa saber o que corrigir.
 */
class NomeDeCadastro implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nome = (string) $value;

        if (preg_match('/[\p{C}\t\r\n]/u', $nome) === 1) {
            $fail('Este nome tem caractere invisível. Digite-o de novo, sem colar de outro programa.');

            return;
        }

        // Dois hífens seguidos: são hífen permitido, mas juntos formam a
        // assinatura que o WAF barra na URL.
        if (str_contains($nome, '--')) {
            $fail('Não use dois hífens seguidos no nome.');

            return;
        }

        if (preg_match("/^[\p{L}\p{N} .,&'’\\-]+$/u", $nome) !== 1) {
            $fail(
                'Use apenas letras, números, espaços e os sinais de nome ou razão social '
                .'(ponto, vírgula, apóstrofo, hífen e &). '
                .'Sinais como <, >, aspas, ponto e vírgula ou barras não entram num nome.',
            );
        }
    }
}

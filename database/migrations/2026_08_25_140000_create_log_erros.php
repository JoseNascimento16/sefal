<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O registro central das exceções da aplicação.
 *
 * Existe para uma pergunta muito concreta: o usuário liga dizendo "deu erro", e
 * alguém precisa saber QUAL erro, sem entrar no servidor caçar arquivo de log. A
 * ponte entre as duas pontas é o `request_id` — o mesmo código que a página de
 * erro mostra ao usuário está gravado aqui.
 *
 * Sem chave estrangeira dura para `users`: o registro é histórico e tem de
 * sobreviver ao desligamento da conta que o gerou. `user_id` é nulo quando o erro
 * aconteceu sem ninguém autenticado — ausência de usuário, não um usuário chamado
 * "anônimo".
 *
 * `mensagem` e `stack` são texto longo (CLOB no Oracle) e por isso ficam FORA da
 * consulta da listagem quando pesam: ver `LogsController`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_erros', function (Blueprint $table) {
            $table->id();

            // O código que o usuário vê na página de erro. Curto e em hexadecimal
            // de propósito: ele é ditado por telefone e viaja em URL — e o WAF da
            // Prefeitura barra qualquer coisa com cara de injeção de SQL.
            $table->string('request_id', 40)->nullable()->index();

            $table->string('classe', 200);
            $table->text('mensagem');
            $table->text('stack')->nullable();

            /*
             * O CAMINHO da requisição — nunca o endereço completo, e é por isso
             * que a coluna se chama `caminho` e não `url`: o nome do campo tem de
             * dizer o que ele guarda. Com "url", o próximo dev grava o endereço
             * inteiro achando que está cumprindo o contrato da coluna.
             *
             * A consulta (o que vem depois do `?`) fica de fora por decisão de
             * segurança, e o último trecho de caminhos sensíveis entra mascarado:
             * o token de redefinição de senha viaja no próprio caminho, e quem o
             * tivesse trocaria a senha da conta alheia. Ver `LogErro`.
             */
            $table->string('caminho', 500)->nullable();
            $table->string('metodo', 10)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();

            // A tela abre sempre pela janela de tempo ("o que quebrou hoje"), e a
            // tabela é das que mais crescem no sistema.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_erros');
    }
};

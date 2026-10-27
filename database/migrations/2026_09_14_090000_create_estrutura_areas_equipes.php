<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ESTRUTURA de trabalho: área → equipe → fiscal.
 *
 * É a espinha do direcionamento. Uma demanda chega com um BAIRRO; o bairro diz
 * a ÁREA; a área tem um Chefe de Setor que responde por ela e equipes que vão à
 * rua. Sem estas quatro tabelas, "encaminhar à área" e "direcionar à equipe" não
 * têm a quem apontar — que é exatamente o que o protótipo resolvia lendo
 * `config/prototipo_estrutura.php`.
 *
 * ## Por que o bairro é tabela, e não texto na área
 *
 * O bairro é a CHAVE de roteamento: é por ele que o sistema sugere a área ao
 * coordenador. Guardado como lista dentro da área (texto separado por vírgula,
 * JSON), a pergunta inversa — "de que área é o Rio Vermelho?" — viraria varredura
 * de string. Com tabela, é índice.
 *
 * ⚠️ **O vínculo bairro↔área NÃO é 1:1, e isso é de propósito.** Mussurunga,
 * Patamares e Jardim das Margaridas pertencem a duas áreas — a divisa passa
 * dentro deles. Por isso o índice único é do PAR (área + bairro), e não do
 * bairro: um único bairro por área faria o sistema escolher sozinho uma das duas
 * respostas certas, escondendo a decisão de quem tem de tomá-la. A sugestão
 * devolve a primeira E as alternativas; quem confirma é o coordenador.
 *
 * ## Chefe de Setor é UM usuário, encarregado é UM nome
 *
 * `chefe_de_setor_id` aponta para `users` porque é quem ENTRA no sistema e decide
 * — o recorte por área do Modo Gerente depende dessa ligação. O `encarregado` é o
 * responsável de campo da equipe: hoje ele não tem conta (não usa a Retaguarda,
 * e o aplicativo é do fiscal), então é texto. No dia em que tiver, vira coluna
 * de usuário — e esta é a razão de o nome estar na EQUIPE, não na área.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();

            // "Área 1", "Área 5" — como o cliente as chama.
            $table->string('nome', 60)->unique();
            // A região que ela cobre, em linguagem de gente ("Centro", "Orla").
            $table->string('regiao', 120)->nullable();

            /*
             * Quem responde pela área DENTRO do sistema. Nulo é estado legítimo:
             * área recém-criada ainda não tem chefe, e recusar o cadastro por
             * isso obrigaria a inventar um responsável. Sem chefe, a demanda
             * encaminhada à área fica visível ao Coordenador e ao administrador
             * — nunca invisível (a lei de não barrar em silêncio vale para o
             * trabalho também: registro sem dono aparece, não some).
             */
            $table->foreignId('chefe_de_setor_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Como a área é recortada: por `bairros` (o caso de hoje) ou por
             * `poligono` (o desenho no mapa, Fase 3). A coluna existe agora
             * porque é ela que diz qual das duas fontes o roteamento deve ler —
             * e um sistema que descobre isso pela presença de dados escolhe
             * errado no dia em que a área tiver os dois.
             */
            $table->string('recorte', 20)->default('bairros');
            // GeoJSON do polígono, quando o recorte for por desenho.
            $table->text('poligono')->nullable();

            $table->string('turno', 20)->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });

        Schema::create('area_bairros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('bairro', 120);
            $table->timestamps();

            // O mesmo bairro não entra duas vezes na MESMA área; entrar em duas
            // áreas diferentes é legítimo (ver o cabeçalho).
            $table->unique(['area_id', 'bairro']);
            $table->index('bairro');
        });

        Schema::create('equipes', function (Blueprint $table) {
            $table->id();

            // "C1", "B2", "N1" — a sigla que a rua usa.
            $table->string('codigo', 10)->unique();
            $table->string('nome', 80)->nullable();

            $table->foreignId('area_id')->constrained('areas');

            // Responsável de campo. Texto por ora: não tem conta no sistema.
            $table->string('encarregado', 120)->nullable();
            $table->string('turno', 20)->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->index('area_id');
        });

        Schema::create('equipe_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            /*
             * Um fiscal numa equipe uma vez só. Ele PODE estar em mais de uma
             * equipe (escala muda, reforço de operação), e por isso a unicidade
             * é do par — não do fiscal.
             */
            $table->unique(['equipe_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipe_fiscais');
        Schema::dropIfExists('equipes');
        Schema::dropIfExists('area_bairros');
        Schema::dropIfExists('areas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O permissionário — quem é fiscalizado.
 *
 * É a tabela-núcleo do sistema: sem ela não há a quem ligar uma vistoria. O
 * desenho dela responde a uma realidade concreta da rua, e não a um cadastro de
 * escritório: **o alvo muitas vezes não tem documento à mão**, não tem endereço
 * fixo e às vezes não quer dizer o nome.
 *
 * ## Por que quase tudo é opcional
 *
 * Obrigatórios são três: `nome` (alguma coisa a chamar a pessoa), `atividade_id`
 * (o ramo, que é o que a fiscalização confere) e `situacao`. Todo o resto —
 * documento, RG, telefone, número e validade da permissão — é opcional de
 * propósito: exigi-los faria o cadastro em campo simplesmente não acontecer, e a
 * pessoa continuaria trabalhando sem constar de lugar nenhum. A identidade
 * prática, aqui, é **foto + apelido** (o nome de guerra pelo qual o fiscal a
 * conhece).
 *
 * ## Os três índices únicos, e o que cada um evita
 *
 *  - `codigo` — a identidade do cadastro no papel, vinda do gerador de protocolo;
 *  - `documento` — **único quando existir**. Nem o Oracle nem o SQLite contam
 *    NULO como valor repetido num índice único, então "vários sem documento"
 *    convive com "nunca dois com o mesmo". É o que faz a normalização valer:
 *    gravado só em `[0-9A-Z]`, o mesmo CPF digitado com e sem máscara colide aqui;
 *  - `client_id` — o UUID que o aparelho gera antes de haver rede. É ele que faz
 *    o reenvio da fila do PWA (Fase 4) reconhecer o cadastro que já subiu em vez
 *    de criar um segundo. Nasce nulo para quem é cadastrado pela Retaguarda.
 *
 * `nome` e `apelido` ganham índice comum porque são por onde se procura alguém
 * — e a busca da tela cresce junto com a base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissionarios', function (Blueprint $table) {
            $table->id();

            // `PER` + data + sequencial do dia (App\Support\Protocolo).
            $table->string('codigo', 20)->unique();

            $table->string('nome', 150);
            // Nome de guerra: em rua, é por ele que a pessoa é conhecida.
            $table->string('apelido', 100)->nullable();

            /*
             * CPF ou CNPJ, guardado NORMALIZADO (só `[0-9A-Z]`, maiúsculo). O
             * tamanho 14 é o do CNPJ — que desde 2026 aceita LETRAS nas 12
             * primeiras posições, e por isso a coluna é texto, nunca número.
             */
            $table->string('documento', 14)->nullable()->unique();
            $table->string('rg', 20)->nullable();

            // Caminho no disco PRIVADO (`permissionarios/...`), não a imagem. A
            // foto só sai pela rota autenticada do cadastro — retrato de cidadão
            // fiscalizado não fica atrás de URL adivinhável.
            $table->string('foto', 255)->nullable();
            $table->string('telefone', 20)->nullable();

            $table->string('numero_permissao', 30)->nullable();
            $table->date('validade_permissao')->nullable();

            /*
             * O ramo autorizado. Sem `onDelete`, o banco recusa apagar a
             * atividade apontada — e a tela de Parametrização recusa ANTES,
             * dizendo quantos cadastros dependem dela (barrar em silêncio, ou
             * com erro de banco, é o que a lei do projeto proíbe).
             */
            $table->foreignId('atividade_id')->constrained('atividades_ambulante');

            /*
             * `Regular` / `Irregular` / `Cadastrado em campo`. O último é
             * QUARENTENA: o cadastro nascido em rua espera a validação do
             * Chefe de Setor. Por isso é o padrão da coluna — o que chega sem situação
             * declarada nunca entra como regular.
             */
            $table->string('situacao', 30)->default('Cadastrado em campo');

            $table->string('client_id', 40)->nullable()->unique();

            $table->timestamps();

            $table->index('nome');
            $table->index('apelido');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissionarios');
    }
};

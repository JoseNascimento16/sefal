<?php

namespace Database\Seeders;

use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use App\Models\LocalizacaoAmbulante;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use RuntimeException;

/**
 * Os ambulantes da DEMONSTRAÇÃO — gente inventada em endereços reais.
 *
 * ## Por que este seeder existe, e o que ele NÃO é
 *
 * O mapa da Retaguarda desenha o que o banco tem. Com quatro cadastros ele
 * mostraria quatro pontos — verdade, e inútil para o dono avaliar a tela. Este
 * seeder povoa a cidade para a demonstração, e faz isso criando **linhas de
 * verdade**: cada ponto do mapa é um `ambulante` com trilha, que se pode abrir,
 * fiscalizar e corrigir.
 *
 * É o oposto do que havia antes: um gerador desenhava pontos na tela sem que
 * existisse cadastro nenhum atrás deles. O ponto era um desenho — clicar nele
 * não levava a lugar nenhum, e nenhuma fiscalização podia apontar para ele.
 *
 * ⚠️ **Só roda em ambiente de desenvolvimento/demonstração.** Em produção os
 * ambulantes chegam do SGCI e do cadastro de campo; semear gente inventada num
 * banco real seria plantar cadastro falso na base que a fiscalização consulta.
 *
 * ## As coordenadas são REAIS, as pessoas não
 *
 * O bairro tem centroide de verdade (`config/geografia.php`), e cada ambulante
 * nasce disperso em algumas centenas de metros em volta dele. Um mapa pode
 * inventar quem está no ponto; não pode inventar onde o ponto fica.
 *
 * ## O sorteio é DETERMINÍSTICO
 *
 * Semente fixa: semear duas vezes produz a mesma cidade. Com `rand()`, o "foco
 * do dia" mudaria de bairro a cada nova carga — e a primeira conclusão de quem
 * visse isso é que o sistema está errado.
 */
class AmbulantesSeeder extends Seeder
{
    /**
     * Quem aparece no mapa: nome, apelido, o que vende e o emoji do pino.
     *
     * O APELIDO não é enfeite: é assim que o ponto é conhecido na rua e é por
     * ele que o fiscal procura — "o João do Acarajé da Barra" acha o cadastro; o
     * nome de batismo, muitas vezes, não.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const PESSOAS = [
        ['Maria de Lourdes Santana', 'Mari da Água', 'Água e refrigerante', 'Bebidas'],
        ['João Batista de Assis', 'João do Acarajé', 'Acarajé e abará', 'Alimentos preparados'],
        ['Antônia Ferreira Lima', 'Dona Tonha', 'Frutas e verduras', 'Frutas e verduras'],
        ['Severino Ramos da Cruz', 'Biu do Milho', 'Milho cozido', 'Alimentos preparados'],
        ['Cleonice Barreto Nunes', 'Cléo das Flores', 'Flores e plantas', 'Artesanato'],
        ['Raimundo Nonato Alves', 'Raí do Coco', 'Água de coco', 'Bebidas'],
        ['Josefa Maria da Conceição', 'Zefa do Mingau', 'Mingau e canjica', 'Alimentos preparados'],
        ['Edvaldo Pereira Matos', 'Val do Churrasco', 'Churrasquinho', 'Alimentos preparados'],
        ['Luzia Andrade Rocha', 'Lu da Tapioca', 'Tapioca e cuscuz', 'Alimentos preparados'],
        ['Gilmar Souza Teixeira', 'Gil do Cafezinho', 'Café e bolo', 'Bebidas'],
        ['Marinalva Costa Reis', 'Nalva das Roupas', 'Roupas e acessórios', 'Vestuário e acessórios'],
        ['Adenilson Gomes Prado', 'Deni do Celular', 'Capa e película', 'Vestuário e acessórios'],
        ['Terezinha Lopes Vieira', 'Tê do Caldinho', 'Caldinho e sopa', 'Alimentos preparados'],
        ['Manoel Ribeiro Cardoso', 'Mané do Sorvete', 'Sorvete e geladinho', 'Alimentos preparados'],
        ['Rosângela Martins Dias', 'Rosa do Artesanato', 'Peças de barro e palha', 'Artesanato'],
        ['Jailson Pereira dos Santos', 'Jal da Barraca', 'Barraca de praia', 'Bebidas'],
        ['Creuza Almeida Farias', 'Creu do Pastel', 'Pastel e caldo de cana', 'Alimentos preparados'],
        ['Domingos Sávio Neves', 'Dominguinhos', 'Frutas da estação', 'Frutas e verduras'],
        ['Iracema Batista Moura', 'Ira das Bijuterias', 'Bijuteria e cabelo', 'Vestuário e acessórios'],
        ['Valdomiro Santos Brito', 'Val do Amendoim', 'Amendoim e castanha', 'Alimentos preparados'],
    ];

    /** Semente fixa do sorteio — ver o cabeçalho. */
    private int $estado = 20260914;

    public function run(): void
    {
        $atividades = AtividadeAmbulante::pluck('id', 'nome');

        if ($atividades->isEmpty()) {
            // Recusa dizendo o que fazer. Sem isto, o erro era "argumento não
            // pode ser nulo" cinco quadros abaixo — que não aponta a causa.
            throw new RuntimeException(
                'Não há atividades de ambulante cadastradas. Rode o ParametrizacaoFiscalizacaoSeeder antes: '
                .'todo ambulante precisa de um ramo, e é ele que a fiscalização confere.',
            );
        }
        $bairros = (array) config('geografia.bairros', []);
        $indice = 0;

        foreach ($bairros as $bairro => $lugar) {
            /*
             * Quantos ambulantes o bairro recebe. O peso vem da concentração que
             * a SEMOP relata (Centro, Calçada e Barra puxam a fila), e serve só
             * para a distribuição ficar plausível — nenhuma tela lê o peso.
             */
            $quantos = max(1, (int) round(((int) ($lugar['peso'] ?? 1)) / 2.2));

            for ($i = 0; $i < $quantos; $i++) {
                $pessoa = self::PESSOAS[$indice % count(self::PESSOAS)];
                $indice++;

                $this->criar(
                    $pessoa,
                    (string) $bairro,
                    (float) $lugar['lat'],
                    (float) $lugar['lng'],
                    $atividades[$pessoa[3]] ?? $atividades->first(),
                    $indice,
                );
            }
        }
    }

    /**
     * Um ambulante e o ponto onde ele foi visto.
     *
     * @param  array{0: string, 1: string, 2: string, 3: string}  $pessoa
     */
    private function criar(array $pessoa, string $bairro, float $lat, float $lng, int $atividadeId, int $sequencia): void
    {
        /*
         * Três situações, e a proporção conta uma história: a maioria é REGULAR
         * (o comércio de rua licenciado é a regra), uma parte é irregular, e uma
         * minoria está em quarentena — o cadastro que nasceu na rua e espera a
         * validação do Chefe de Setor.
         */
        $sorte = $this->proximo(100);
        $situacao = match (true) {
            $sorte < 12 => Ambulante::SITUACAO_CAMPO,
            $sorte < 42 => Ambulante::SITUACAO_IRREGULAR,
            default => Ambulante::SITUACAO_REGULAR,
        };

        // Permissão só de quem está regular: é exatamente o que a fiscalização
        // confere na calçada.
        $permissionario = $situacao === Ambulante::SITUACAO_REGULAR;

        $ambulante = Ambulante::updateOrCreate(
            // O apelido MAIS o bairro: a mesma pessoa aparece em bairros
            // diferentes nesta amostra, e são cadastros diferentes de propósito —
            // é o que dá ao mapa gente espalhada pela cidade sem repetir o mesmo
            // registro em dois pinos.
            ['codigo' => sprintf('AMB-%04d', $sequencia)],
            [
                'nome' => $pessoa[0],
                'apelido' => $pessoa[1],
                'permissionario' => $permissionario,
                'numero_permissao' => $permissionario
                    ? 'PM-'.(2023 + $this->proximo(3)).'/'.str_pad((string) (100 + $this->proximo(800)), 4, '0', STR_PAD_LEFT)
                    : null,
                'validade_permissao' => $permissionario
                    ? Date::now()->addDays(30 + $this->proximo(500))->startOfDay()
                    : null,
                'atividade_id' => $atividadeId,
                'situacao' => $situacao,
            ],
        );

        $quando = Date::now()->subDays(1 + $this->proximo(120))->setTime(9 + $this->proximo(9), $this->proximo(60));

        LocalizacaoAmbulante::updateOrCreate(
            ['ambulante_id' => $ambulante->id, 'fonte' => LocalizacaoAmbulante::FONTE_CADASTRO],
            [
                ...$this->disperso($lat, $lng),
                'bairro' => $bairro,
                'registrada_em' => $quando,
            ],
        );
    }

    /**
     * Um ponto disperso em volta do centroide do bairro.
     *
     * Sem dispersão todos os ambulantes do bairro cairiam na MESMA coordenada, e
     * o mapa mostraria um pino onde há doze — o agrupamento esconderia justamente
     * a concentração que ele deveria revelar.
     *
     * @return array{latitude: float, longitude: float}
     */
    private function disperso(float $lat, float $lng, float $raio = 0.006): array
    {
        return [
            'latitude' => round($lat + ($this->proximo(2000) - 1000) / 1000 * $raio, 7),
            'longitude' => round($lng + ($this->proximo(2000) - 1000) / 1000 * $raio, 7),
        ];
    }

    /**
     * O próximo número do sorteio determinístico — congruente linear simples.
     *
     * Determinístico de propósito: semear duas vezes produz a mesma cidade. Com
     * `rand()`, o foco do dia mudaria de bairro a cada carga, e quem visse isso
     * concluiria que o sistema está errado.
     */
    private function proximo(int $teto): int
    {
        $this->estado = ($this->estado * 1103515245 + 12345) & 0x7FFFFFFF;

        return intdiv($this->estado, 65536) % $teto;
    }
}

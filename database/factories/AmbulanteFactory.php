<?php

namespace Database\Factories;

use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use App\Support\Protocolo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Ambulantes realistas — como eles chegam da rua, não como um cadastro de
 * escritório gostaria que fossem.
 *
 * Por isso o padrão é **sem documento e sem permissão**: é o caso comum, e é o
 * que os testes precisam exercitar. Quem quiser um documento pede
 * `comDocumento()`, que gera um CPF com dígitos verificadores válidos — CPF
 * inventado seria recusado pela validação, e o teste passaria a provar a coisa
 * errada. Quem quiser um permissionário pede `permissionario()`, e aí o número
 * da permissão vem junto: ele é o que sustenta a afirmação.
 *
 * @extends Factory<Ambulante>
 */
class AmbulanteFactory extends Factory
{
    protected $model = Ambulante::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $apelidos = ['Zé do Coco', 'Nina da Barraca', 'Seu Bila', 'Dona Rita', 'Tim do Isopor'];

        return [
            // Pelo gerador de protocolo, com a rede do model: é a fonte única da
            // numeração, e um código inventado aqui divergiria dela no formato.
            'codigo' => Protocolo::proximo('AMB', null, Ambulante::class, 'codigo'),
            'nome' => fake()->name(),
            'apelido' => fake()->randomElement($apelidos),
            'documento' => null,
            'rg' => null,
            'foto' => null,
            'telefone' => fake()->numerify('(71) 9####-####'),
            /*
             * SEM permissão, e por isso sem número nem validade: é o retrato de
             * quem a fiscalização mais encontra. Antes o padrão trazia número e
             * validade em todo mundo, o que era exatamente a mentira que o rename
             * veio desfazer — todo cadastro parecia permissionário.
             */
            'permissionario' => false,
            'numero_permissao' => null,
            'validade_permissao' => null,
            // Reaproveita o que a semeadura já criou; só inventa uma atividade
            // se a base estiver de fato vazia.
            'atividade_id' => fn (): int => (int) (AtividadeAmbulante::query()->value('id')
                ?? AtividadeAmbulante::create(['nome' => 'Atividade '.fake()->unique()->word(), 'ativo' => true])->id),
            'situacao' => Ambulante::SITUACAO_REGULAR,
            'client_id' => null,
        ];
    }

    /** Com CPF válido — para exercitar unicidade e formatação. */
    public function comDocumento(?string $documento = null): static
    {
        return $this->state(fn (): array => ['documento' => $documento ?? self::cpfValido()]);
    }

    /**
     * Com permissão da SEMOP — o número vem junto porque é ele que a sustenta.
     *
     * A validade fica opcional de propósito (é assim que a regra a trata): quem
     * quiser uma data passa por cima com `->permissionario(validade: '...')`.
     */
    public function permissionario(?string $numero = null, ?string $validade = null): static
    {
        return $this->state(fn (): array => [
            'permissionario' => true,
            'numero_permissao' => $numero ?? fake()->numerify('SEMOP-#####'),
            'validade_permissao' => $validade,
        ]);
    }

    /** Nascido em rua, esperando a validação do Chefe de Setor. */
    public function emQuarentena(): static
    {
        return $this->state(fn (): array => [
            'situacao' => Ambulante::SITUACAO_CAMPO,
            'client_id' => fake()->uuid(),
        ]);
    }

    /** Um CPF com os dois dígitos verificadores corretos. */
    private static function cpfValido(): string
    {
        $digitos = [];

        for ($i = 0; $i < 9; $i++) {
            $digitos[] = random_int(0, 9);
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;

            for ($i = 0; $i < $t; $i++) {
                $soma += $digitos[$i] * ($t + 1 - $i);
            }

            $digitos[] = ((10 * $soma) % 11) % 10;
        }

        return implode('', $digitos);
    }
}

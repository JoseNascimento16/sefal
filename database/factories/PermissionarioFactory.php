<?php

namespace Database\Factories;

use App\Models\AtividadeAmbulante;
use App\Models\Permissionario;
use App\Support\Protocolo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Permissionários realistas — como eles chegam da rua, não como um cadastro
 * de escritório gostaria que fossem.
 *
 * Por isso o padrão é **sem documento**: é o caso comum, e é o que os testes
 * precisam exercitar. Quem quiser um documento pede `comDocumento()`, que gera
 * um CPF com dígitos verificadores válidos — CPF inventado seria recusado pela
 * validação, e o teste passaria a provar a coisa errada.
 *
 * @extends Factory<Permissionario>
 */
class PermissionarioFactory extends Factory
{
    protected $model = Permissionario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $apelidos = ['Zé do Coco', 'Nina da Barraca', 'Seu Bila', 'Dona Rita', 'Tim do Isopor'];

        return [
            // Pelo gerador de protocolo, com a rede do model: é a fonte única da
            // numeração, e um código inventado aqui divergiria dela no formato.
            'codigo' => Protocolo::proximo('PER', null, Permissionario::class, 'codigo'),
            'nome' => fake()->name(),
            'apelido' => fake()->randomElement($apelidos),
            'documento' => null,
            'rg' => null,
            'foto' => null,
            'telefone' => fake()->numerify('(71) 9####-####'),
            'numero_permissao' => fake()->numerify('SEMOP-#####'),
            'validade_permissao' => fake()->dateTimeBetween('-6 months', '+2 years')->format('Y-m-d'),
            // Reaproveita o que a semeadura já criou; só inventa uma atividade
            // se a base estiver de fato vazia.
            'atividade_id' => fn (): int => (int) (AtividadeAmbulante::query()->value('id')
                ?? AtividadeAmbulante::create(['nome' => 'Atividade '.fake()->unique()->word(), 'ativo' => true])->id),
            'situacao' => Permissionario::SITUACAO_REGULAR,
            'client_id' => null,
        ];
    }

    /** Com CPF válido — para exercitar unicidade e formatação. */
    public function comDocumento(?string $documento = null): static
    {
        return $this->state(fn (): array => ['documento' => $documento ?? self::cpfValido()]);
    }

    /** Nascido em rua, esperando a validação do Gestor. */
    public function emQuarentena(): static
    {
        return $this->state(fn (): array => [
            'situacao' => Permissionario::SITUACAO_CAMPO,
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

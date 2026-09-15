<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * O CENÁRIO DA PRÉ-TRIAGEM: seis pessoas relatando o mesmo bar.
 *
 * É o caso que o dono descreveu em 14/09/2026 e que a amostra do protótipo não
 * tinha: "dez denúncias de mesas e cadeiras atrapalhando a via, mas quando a
 * gente vai olhar o local, se trata do mesmo ambulante".
 *
 * Sem ele, a tela de pré-triagem abre vazia e ninguém consegue avaliar se a
 * mecânica presta — e uma tela que não se consegue avaliar é uma tela que vai
 * para produção sem ter sido olhada.
 *
 * ## O que este cenário precisa ter para valer alguma coisa
 *
 * **Palavras diferentes para o mesmo fato.** Seis cópias do mesmo texto tornariam
 * o agrupamento trivial e provariam nada: cada cidadão escreve à sua maneira —
 * "mesas na calçada", "bar ocupando o passeio", "cadeiras impedindo passagem".
 * É essa variação que o analisador precisa atravessar.
 *
 * **Os dois canais.** O e-Salvador traz requerente identificado e endereço
 * estruturado; o Salvador Digital pode ser anônimo e traz a transcrição do que o
 * atendente ouviu, às vezes sem número. Um agrupamento que só funcionasse dentro
 * do mesmo canal deixaria de fora metade da repetição real.
 *
 * **Números de porta próximos, não idênticos.** O cidadão informa o número do
 * prédio de onde olha, e não o do ponto — 212, 214, 218 são a mesma calçada.
 *
 * **E uma ARMADILHA.** A última denúncia é da mesma rua e do mesmo assunto, mas
 * a trezentos metros: é outro estabelecimento. Ela existe para que a tela seja
 * julgada pelo que ela faz com o caso difícil — se a máquina propuser essa
 * junto, o coordenador tem de poder recusar, e a recusa tem de segurar.
 */
class DenunciasRepetidasSeeder extends Seeder
{
    /**
     * O mesmo bar, visto por seis pessoas.
     *
     * ── Por que o nome de FACHADA varia entre os relatos ────────────────────
     *
     * Cinco escrevem "Bar do Zeca" e um escreve "Bar do Zéca" — porque é assim
     * que chega: cada cidadão escreve o que leu, de memória, sem conferir grafia.
     * A demonstração seria fácil demais se todos digitassem igual, e a régua do
     * agrupamento precisa ser julgada pelo caso real.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: int, 5: bool, 6: string, 7: string}>
     */
    private const RELATOS = [
        [
            'e-salvador', 'Mesas e cadeiras ocupando a calçada',
            'O bar do térreo espalhou mesas por toda a largura da calçada. Quem passa com carrinho de bebê tem de descer para a rua.',
            '212', 9, false, 'Bar do Zeca', 'José Carlos Andrade Lima',
        ],
        [
            'salvador-digital', 'Bar ocupando o passeio público',
            'Munícipe informa que o estabelecimento coloca mesas e cadeiras no passeio todas as noites, de quinta a domingo, e que já reclamou no local sem sucesso.',
            '214', 26, true, 'Bar do Zeca', '',
        ],
        [
            'e-salvador', 'Cadeiras impedindo a passagem de pedestres',
            'Não sobra espaço para andar na calçada por causa das cadeiras do bar. À noite fica pior, com gente em pé ocupando o resto.',
            '218', 41, false, 'Bar do Zéca', '',
        ],
        [
            'salvador-digital', 'Mesas na calçada atrapalhando quem anda',
            'Denunciante relata mesas na calçada em frente ao bar, obrigando pedestres a caminhar pela via, em rua de movimento.',
            '212', 58, true, 'Bar do Zeca', '',
        ],
        [
            'e-salvador', 'Ocupação irregular da calçada por estabelecimento',
            'O bar avançou com mesas sobre a calçada e sobre a faixa de estacionamento. Cadeirante não consegue passar.',
            '210', 73, false, 'Bar do Zeca', 'José Carlos Andrade Lima',
        ],
        [
            'e-salvador', 'Mesas do bar bloqueando a calçada',
            'Todo fim de semana as mesas tomam a calçada inteira. Já vi idoso descendo para a rua para conseguir passar.',
            '216', 95, false, 'Bar do Zeca', 'José Carlos Andrade Lima',
        ],
    ];

    /**
     * A ARMADILHA: mesma rua, mesmo assunto, OUTRO estabelecimento.
     *
     * A trezentos metros, do outro lado do quarteirão. Ela existe para que a
     * pré-triagem seja julgada pelo caso difícil — e para que a recusa do
     * coordenador tenha o que segurar.
     */
    private const ARMADILHA = [
        'e-salvador', 'Mesas na calçada em frente ao restaurante',
        'O restaurante da esquina põe mesas na calçada no horário do almoço e não sobra passagem.',
        '640', 33, false, 'Restaurante Maré Alta', 'Maré Alta Refeições Ltda.',
    ];

    public function run(): void
    {
        $bairro = 'Pituba';
        $rua = 'Rua Rio Grande do Sul';
        $area = Area::sugeridaParaBairro($bairro);

        foreach (self::RELATOS as $i => $relato) {
            $this->criar($relato, $rua, $bairro, $area?->id, $i + 1, -12.9931000, -38.4587000);
        }

        /*
         * A armadilha ganha coordenada a ~300 m: é essa distância que o
         * analisador tem de enxergar, já que a rua e o assunto são os mesmos.
         */
        $this->criar(self::ARMADILHA, $rua, $bairro, $area?->id, 90, -12.9958000, -38.4562000);
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string, 4: int, 5: bool}  $relato
     */
    private function criar(array $relato, string $rua, string $bairro, ?int $areaId, int $sequencia, float $lat, float $lng): void
    {
        [$canal, $assunto, $texto, $numero, $horas, $anonima, $estabelecimento, $denunciado] = $relato;

        $recebida = Date::now()->subHours($horas);
        $prefixo = $canal === Demanda::CANAL_E_SALVADOR ? 'ESL' : '156';

        $demanda = Demanda::updateOrCreate(
            ['protocolo' => sprintf('DEN-8%03d', $sequencia)],
            [
                'canal' => $canal,
                'entrada' => Demanda::ENTRADA_INTEGRACAO,
                'numero_origem' => $prefixo.'-2026-88'.str_pad((string) $sequencia, 3, '0', STR_PAD_LEFT),
                'recebida_em' => $recebida,
                'prazo_em' => $recebida->copy()->addDays(10)->startOfDay(),
                'anonima' => $anonima,
                'requerente' => $anonima ? null : $this->nome($sequencia),
                'assunto' => $assunto,
                'relato' => $texto,
                // O nome da FACHADA é o que o cidadão escreve porque é o que ele
                // leu na rua; o responsável, quase sempre, ninguém sabe.
                'estabelecimento' => $estabelecimento,
                'denunciado' => $denunciado === '' ? null : $denunciado,
                'logradouro' => $rua,
                'numero' => $numero,
                'bairro' => $bairro,
                'latitude' => $lat,
                'longitude' => $lng,
                // Chega EM PRÉ-TRIAGEM: é a leva crua, e é exatamente o estado
                // em que o coordenador precisa encontrá-la para decidir quantos
                // fatos ela contém.
                'situacao' => Demanda::EM_PRE_TRIAGEM,
                'area_id' => $areaId,
            ],
        );

        // O passo de recebimento, como em qualquer denúncia de integração.
        if ($demanda->tramites()->count() === 0) {
            $demanda->tramites()->create([
                'ordem' => 1,
                'ocorrida_em' => $recebida,
                'papel' => DemandaTramite::PAPEL_INTEGRACAO,
                'acao' => 'Recebida por integração',
                'detalhe' => 'Entrou pelo canal com o número '.$demanda->numero_origem
                    .'. Nenhum dado foi digitado no SEFAL. Aguarda a pré-triagem.',
                'situacao' => Demanda::EM_PRE_TRIAGEM,
            ]);
        }
    }

    /** Quem relatou. Nome de demonstração — o fato é que são pessoas diferentes. */
    private function nome(int $sequencia): string
    {
        $nomes = [
            'Lúcia Bastos de Almeida',
            'Renato Correia Pinto',
            'Simone Aguiar Fontes',
            'Paulo Henrique Nóbrega',
            'Vanda Oliveira Serra',
            'Cristiane Mota Belém',
            'Hélio Barbosa Tavares',
        ];

        return $nomes[($sequencia - 1) % count($nomes)];
    }
}

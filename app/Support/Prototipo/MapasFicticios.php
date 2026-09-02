<?php

namespace App\Support\Prototipo;

/**
 * PROTÓTIPO — os dados das duas telas de mapa (Mapa ao Vivo e Mapa de Calor).
 *
 * ⚠️ Nada aqui toca o banco: pessoas, atividades e horários são INVENTADOS. O que
 * NÃO é inventado são duas coisas, e é por elas que este arquivo existe:
 *
 *  1. **as coordenadas** — Centro Histórico, Barra, Calçada, Itapuã, Sussuarana,
 *     Cajazeiras… são pontos reais de Salvador (aproximados ao centro do bairro).
 *     Um mapa pode mentir sobre quem está no ponto; não pode mentir sobre ONDE o
 *     ponto fica, senão a única coisa que o mapa faz deixa de valer;
 *  2. **a estrutura** — a área, a equipe e o encarregado de cada bairro NÃO estão
 *     escritos aqui: saem de {@see EstruturaFicticia}, a mesma fonte que a tela de
 *     Áreas e Equipes mostra e que a Caixa de Entrada usa para sugerir o destino de
 *     uma demanda. Duas listas de bairro→equipe discordariam no primeiro ajuste, e
 *     o mapa passaria a mandar o gestor cobrar a equipe errada.
 *
 * ── Por que os números NÃO vêm daqui ────────────────────────────────────────
 *
 * "Registros hoje", "retornos vencidos", "42% no Centro Histórico" e o ranking de
 * regiões são CONTAS sobre os pontos, e a conta mora na tela (ver
 * `resources/js/dados-prototipo/mapas.ts`). É a RN-06 do desenho da Retaguarda
 * aplicada ao mapa: número de cabeçalho sai da mesma lista que o mapa desenha, e
 * não de um segundo cálculo — senão o painel discorda do que está desenhado ao
 * lado dele, e é sempre o painel que ganha a discussão.
 *
 * ── Determinismo ────────────────────────────────────────────────────────────
 *
 * Os pontos são sorteados, mas por um gerador de semente FIXA: o dono recarrega a
 * tela e vê a mesma cidade, e as duas telas concordam entre si. Sorteio com
 * `rand()` faria o "foco do dia" mudar de bairro a cada F5 — e a primeira coisa
 * que se conclui, ao ver isso, é que o sistema está errado.
 */
class MapasFicticios
{
    /**
     * Os bairros que entram no mapa: coordenada real e o PESO da incidência.
     *
     * O peso é o que faz a cidade ter relevo — sem ele o mapa de calor vira um
     * borrão uniforme e não decide operação nenhuma. Ele reflete o que a SEMOP
     * relata em reunião (comércio de rua concentrado no Centro, Calçada, Barra e
     * nos corredores), e é número de protótipo: quando houver registro de verdade,
     * o relevo passa a ser o do próprio dado.
     *
     * Os nomes casam com os blocos de `config/prototipo_estrutura.php` — é isso
     * que permite a derivação bairro → equipe funcionar. Bairro escrito com outra
     * grafia sairia sem equipe no mapa.
     *
     * @var array<string, array{0: float, 1: float, 2: int}>
     */
    private const BAIRROS = [
        // Área 1 · Centro · Equipe C2
        'Centro Histórico' => [-12.9718, -38.5089, 10],
        'Comércio' => [-12.9740, -38.5137, 8],
        'Barra' => [-13.0106, -38.5325, 7],
        'Barris' => [-12.9820, -38.5140, 4],
        'Rio Vermelho' => [-13.0104, -38.4906, 5],
        'Nazaré' => [-12.9760, -38.5060, 3],
        'Ondina' => [-13.0086, -38.5065, 3],
        'Federação' => [-13.0000, -38.5100, 3],
        'Graça' => [-13.0000, -38.5220, 2],
        'Vitória' => [-12.9930, -38.5210, 2],

        // Área 2 · Itapagipe · Equipe A1
        'Calçada' => [-12.9350, -38.5040, 6],
        'Bonfim' => [-12.9200, -38.5070, 5],
        'Ribeira' => [-12.9210, -38.4980, 4],
        'Uruguai' => [-12.9280, -38.5010, 4],
        'Periperi' => [-12.8600, -38.4830, 4],
        'Paripe' => [-12.8330, -38.4880, 3],

        // Área 3 · Brotas · Equipe A2
        'Pituba' => [-12.9930, -38.4560, 6],
        'Amaralina' => [-13.0030, -38.4680, 5],
        'Cabula' => [-12.9560, -38.4530, 4],
        'Pernambués' => [-12.9670, -38.4600, 4],
        'Engenho Velho de Brotas' => [-12.9800, -38.4930, 3],
        'Itaigara' => [-12.9880, -38.4640, 2],

        // Área 4 · Liberdade · Equipe B2
        'São Caetano' => [-12.9330, -38.4750, 6],
        'Curuzu' => [-12.9450, -38.4900, 5],
        'IAPI' => [-12.9490, -38.4830, 4],
        'Cidade Nova' => [-12.9420, -38.4670, 3],
        'Pirajá' => [-12.9130, -38.4560, 2],

        // Área 5 · Boca do Rio · Equipe C1
        'Itapuã' => [-12.9469, -38.3628, 5],
        'Costa Azul' => [-12.9880, -38.4400, 4],
        'Mussurunga' => [-12.9300, -38.3820, 4],
        'Stiep' => [-12.9830, -38.4340, 3],
        'Imbuí' => [-12.9750, -38.4230, 3],
        'Jardim Armação' => [-12.9880, -38.4270, 3],
        'Patamares' => [-12.9660, -38.3970, 2],
        'Stella Maris' => [-12.9370, -38.3480, 2],

        // Área 6 · Pau da Lima · Equipe B1
        'Tancredo Neves' => [-12.9450, -38.4390, 5],
        'Sussuarana' => [-12.9420, -38.4230, 4],
        'Cajazeiras II a XI' => [-12.8900, -38.4130, 4],
        'Castelo Branco' => [-12.9080, -38.4230, 3],
        'Sete de Abril' => [-12.9130, -38.4310, 3],
        'Arenoso' => [-12.9530, -38.4290, 3],
        'Águas Claras' => [-12.8900, -38.4290, 2],

        // Itinerante · Equipe I1 — corredor, não bairro fechado.
        'Avenida Sete de Setembro' => [-12.9790, -38.5140, 7],
        'Avenida Joana Angélica' => [-12.9800, -38.5060, 4],
    ];

    /** O centro do mapa da cidade: a Praça Municipal, no Centro Histórico. */
    public const CENTRO = ['lat' => -12.9730, 'lng' => -38.5140];

    /**
     * Nome, apelido e atividade de quem aparece no mapa — gente INVENTADA.
     *
     * Apelido porque é assim que o ponto é conhecido na rua, e é assim que o
     * fiscal o procura: "o João do acarajé da Barra" acha o cadastro; o nome de
     * batismo, muitas vezes, não.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const PESSOAS = [
        ['Maria de Lourdes Santana', 'Mari da Água', 'Água e refrigerante', '🥤'],
        ['João Batista de Assis', 'João do Acarajé', 'Acarajé e abará', '🍢'],
        ['Antônia Ferreira Lima', 'Dona Tonha', 'Frutas e verduras', '🍉'],
        ['Severino Ramos da Cruz', 'Biu do Milho', 'Milho cozido', '🌽'],
        ['Cleonice Barreto Nunes', 'Cléo das Flores', 'Flores e plantas', '🌺'],
        ['Raimundo Nonato Alves', 'Raí do Coco', 'Água de coco', '🥥'],
        ['Josefa Maria da Conceição', 'Zefa do Mingau', 'Mingau e canjica', '🥣'],
        ['Edvaldo Pereira Matos', 'Val do Churrasco', 'Churrasquinho', '🍖'],
        ['Luzia Andrade Rocha', 'Lu da Tapioca', 'Tapioca e cuscuz', '🫓'],
        ['Gilmar Souza Teixeira', 'Gil do Cafezinho', 'Café e bolo', '☕'],
        ['Marinalva Costa Reis', 'Nalva das Roupas', 'Roupas e acessórios', '👕'],
        ['Adenilson Gomes Prado', 'Deni do Celular', 'Capa e película', '📱'],
        ['Terezinha Lopes Vieira', 'Tê do Caldinho', 'Caldinho e sopa', '🍲'],
        ['Manoel Ribeiro Cardoso', 'Mané do Sorvete', 'Sorvete e geladinho', '🍦'],
        ['Solange Dias Bastos', 'Sol da Cerveja', 'Bebidas', '🍺'],
        ['Elenilson Freitas Braga', 'Nilson do Isopor', 'Bebidas geladas', '🧊'],
        ['Rosana Aguiar Cerqueira', 'Rosa do Salgado', 'Salgados fritos', '🥟'],
        ['Domingos Bispo Farias', 'Dominguinhos', 'Amendoim e pipoca', '🥜'],
        ['Cristina Peixoto Nery', 'Cris do Artesanato', 'Artesanato', '🧶'],
        ['Ubirajara Neves Pinto', 'Bira do Pastel', 'Pastel', '🥠'],
        ['Vanda Lúcia Sampaio', 'Vandinha do Lanche', 'Lanches', '🥪'],
        ['Jailson Moura Correia', 'Jaí do Guarda-Sol', 'Aluguel de cadeira', '⛱️'],
        ['Neide Almeida Torres', 'Neidinha do Doce', 'Doces e cocada', '🍬'],
        ['Aloísio Campos Sena', 'Aló do Ferro-Velho', 'Recicláveis', '♻️'],
        ['Marlene Pinho Xavier', 'Marlene do Peixe', 'Peixe e marisco', '🐟'],
        ['Genivaldo Brito Serra', 'Geno do Queijo', 'Queijo coalho', '🧀'],
        ['Iracema Fonseca Duarte', 'Ira do Bolo', 'Bolo caseiro', '🍰'],
        ['Petrônio Lacerda Viana', 'Petinho da Bala', 'Balas e chicletes', '🍭'],
        ['Sueli Menezes Barros', 'Su do Beiju', 'Beiju e pé de moleque', '🥮'],
        ['Hamilton Rezende Paiva', 'Miltinho do Suco', 'Sucos naturais', '🧃'],
        ['Benedita Carvalho Alencar', 'Dita da Feira', 'Temperos e ervas', '🌿'],
        ['Wagner Nascimento Lira', 'Wag do Carrinho', 'Hot dog', '🌭'],
        ['Áurea Machado Quirino', 'Aurinha do Bijou', 'Bijuteria', '💍'],
        ['Fabiano Estrela Guedes', 'Fabi do Óculos', 'Óculos de sol', '🕶️'],
        ['Marta Siqueira Bonfim', 'Martinha do Café', 'Café da manhã', '🥐'],
        ['Cícero Andrade Melo', 'Cicinho do Espeto', 'Espetinho', '🍡'],
        ['Railda Passos Coelho', 'Rai da Cocada', 'Cocada e tapioca', '🥧'],
        ['Osvaldo Tavares Mendes', 'Vado do Amendoim', 'Amendoim torrado', '🌰'],
        ['Lindaura Souto Braga', 'Dona Linda', 'Mungunzá', '🍚'],
        ['Sebastião Rocha Pimentel', 'Tião do Caldo', 'Caldo de cana', '🎋'],
    ];

    /** Fiscais em campo — nomes inventados; o encarregado é o real da equipe. */
    private const FISCAIS_EM_CAMPO = [
        ['Souza', 'F-2088', 'Cláudio Ferreira Lima'],
        ['Lima', 'F-2131', 'Rita de Cássia Andrade'],
        ['Barreto', 'F-2247', 'Jussara Nunes Barreto'],
        ['Prado', 'F-2301', 'Solange Ribeiro Prado'],
        ['Amorim', 'F-2436', 'Vera Lúcia Amorim'],
        ['Queiroz', 'F-2529', 'Renato Queiroz Bastos'],
        ['Sampaio', 'F-2677', 'Anderson Luz Sampaio'],
        ['Macedo', 'F-2801', 'Paulo Sérgio Macedo'],
    ];

    /**
     * O que um fiscal escreve no registro rápido — a mesma língua do aplicativo.
     *
     * Separadas por DESFECHO, e não numa lista só: sorteadas juntas, saía
     * "Situação: Regular · Ocorrência: Sem permissão" na mesma ficha, e uma tela
     * que se contradiz na própria linha custa a confiança de quem a lê — mesmo
     * sendo protótipo, e mesmo sendo por acidente do sorteio.
     */
    private const OCORRENCIAS_REGULARES = [
        'Orientado no local',
        'Desarmou e saiu',
        'Local vazio na chegada',
        'Conferido, sem pendência',
        'Documento apresentado',
    ];

    private const OCORRENCIAS_IRREGULARES = [
        'Obstrução de calçada',
        'Sem permissão',
        'Venda de bebida',
        'Reincidente',
        'Manipulação de alimento',
        'Recusou sair',
        'Produto fora do equipamento',
    ];

    /** Semente do sorteio — ver o cabeçalho da classe (determinismo). */
    private static int $estado = 0;

    /**
     * Mapa ao Vivo: os pontos da cidade, os fiscais em campo e a estrutura.
     *
     * A tela recebe a LISTA e faz as contas — os painéis de vidro ("a cidade
     * agora", o foco do dia, os últimos registros) são agregações do que está
     * desenhado, e mudam junto com o filtro do gestor.
     *
     * @return array<string, mixed>
     */
    public static function aoVivo(): array
    {
        self::$estado = 20260902;

        $bairros = self::bairrosComEquipe();
        $pontos = [];
        $indice = 0;

        foreach ($bairros as $bairro) {
            // Quantos pontos conhecidos há no bairro: o peso manda, com um piso de
            // 1 para nenhum bairro do recorte aparecer vazio no mapa.
            $quantos = max(1, (int) round($bairro['peso'] / 2.2));

            for ($i = 0; $i < $quantos; $i++) {
                $pessoa = self::PESSOAS[$indice % count(self::PESSOAS)];
                $indice++;

                [$lat, $lng] = self::disperso($bairro['lat'], $bairro['lng']);

                /*
                 * Três estados de ponto, e a proporção conta uma história: a
                 * maioria é REGULAR (o comércio de rua licenciado é a regra), uma
                 * parte é irregular, e uma minoria tem RETORNO VENCIDO — a única
                 * que grita na tela, porque é a única em que alguém já prometeu
                 * voltar e não voltou.
                 */
                $sorte = self::proximo(100);
                $vencido = $sorte < 12;
                $irregular = ! $vencido && $sorte < 42;

                $pontos[] = [
                    'id' => 'P'.str_pad((string) (count($pontos) + 1), 3, '0', STR_PAD_LEFT),
                    'nome' => $pessoa[0],
                    'apelido' => $pessoa[1],
                    'atividade' => $pessoa[2],
                    'emoji' => $pessoa[3],
                    'situacao' => $irregular || $vencido ? 'irregular' : 'regular',
                    'retorno_ha_dias' => $vencido ? 1 + self::proximo(9) : null,
                    // Permissão só de quem é regular: é exatamente o que a
                    // fiscalização confere na calçada.
                    'permissao' => $irregular || $vencido
                        ? null
                        : 'PM-'.(2023 + self::proximo(3)).'/'.str_pad((string) (100 + self::proximo(800)), 4, '0', STR_PAD_LEFT),
                    'bairro' => $bairro['bairro'],
                    'area' => $bairro['area'],
                    'regiao' => $bairro['regiao'],
                    'equipe' => $bairro['equipe'],
                    'encarregado' => $bairro['encarregado'],
                    'tambem_de' => $bairro['tambem_de'],
                    'lat' => $lat,
                    'lng' => $lng,
                    'turno' => self::proximo(100) < 22 ? 'Noturno' : 'Diurno',
                    'ultima_em' => self::dataBrAtras(1 + self::proximo(120)),
                ];
            }
        }

        return [
            'pontos' => $pontos,
            'registros' => self::registrosDeHoje($bairros),
            'fiscais' => self::fiscaisEmCampo($bairros),
            'equipes' => self::equipes(),
            'centro' => self::CENTRO,
            // A hora do "agora" da tela. Protótipo não tem tempo real: o que ele
            // tem é uma cidade parada num instante, e é honesto dizer qual.
            'momento' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * Mapa de Calor: os pontos de incidência dos últimos 180 dias.
     *
     * Vão 180 e não 90 de propósito: o ranking mostra a VARIAÇÃO contra o período
     * anterior, e para comparar 90 dias com os 90 de antes é preciso ter os 180.
     * Sem isso a coluna de variação seria invenção.
     *
     * O ponto viaja como tupla (`[bairro, lat, lng, dias, noturno]`), e não como
     * objeto com o bairro repetido setecentas vezes: são ~700 pontos, e o nome do
     * bairro, a área e o encarregado vêm uma vez só na lista de bairros.
     *
     * @return array<string, mixed>
     */
    public static function calor(): array
    {
        self::$estado = 20260930;

        $bairros = self::bairrosComEquipe();
        $pontos = [];

        foreach ($bairros as $indice => $bairro) {
            /*
             * Quantos registros o bairro produziu em 180 dias. O peso manda, e a
             * TENDÊNCIA (subindo, estável, caindo) é o que faz a coluna de
             * variação do ranking ter conteúdo: sem tendência, todo bairro
             * apareceria com 0% e a comparação não diria nada.
             *
             * O volume é generoso de propósito. Com poucos registros por bairro,
             * a janela de 30 dias caía para dois ou três, e aí a coluna de
             * variação virava ruído: "+300%" para uma ocorrência que passou de 1
             * para 4 é verdade aritmética e mentira de leitura. Com volume, a
             * variação passa a dizer algo sobre a rua.
             */
            $quantos = 8 + (int) round($bairro['peso'] * 9);
            $tendencia = [1.85, 1.25, 1.0, 0.72, 0.5][self::proximo(5)];

            for ($i = 0; $i < $quantos; $i++) {
                /*
                 * O dia do registro. `$viés` empurra o sorteio para a metade
                 * recente (bairro em alta) ou para a antiga (bairro esfriando) —
                 * é isso, e só isso, que a "tendência" significa aqui.
                 */
                $bruto = self::proximo(180) + 1;
                $dias = $tendencia >= 1.0
                    ? (int) max(1, round($bruto / $tendencia))
                    : (int) min(180, round($bruto / $tendencia));

                [$lat, $lng] = self::disperso($bairro['lat'], $bairro['lng'], 0.0075);

                $pontos[] = [$indice, $lat, $lng, $dias, self::proximo(100) < 22 ? 1 : 0];
            }
        }

        return [
            'bairros' => $bairros,
            'pontos' => $pontos,
            'equipes' => self::equipes(),
            'centro' => self::CENTRO,
            'momento' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * Os bairros do mapa, cada um já com a área, a equipe e o encarregado que a
     * ESTRUTURA diz — e com as outras equipes que também cobrem o bairro.
     *
     * O bairro compartilhado (Mussurunga, Patamares, e o Comércio, que é bairro da
     * Área 1 e corredor da Itinerante) não é erro: o vínculo bairro↔equipe não é
     * 1:1. O mapa mostra a equipe sugerida e diz quem mais cobre — a mesma
     * conversa que a Caixa de Entrada tem com o administrativo.
     *
     * @return list<array<string, mixed>>
     */
    private static function bairrosComEquipe(): array
    {
        $lista = [];

        foreach (self::BAIRROS as $nome => [$lat, $lng, $peso]) {
            $sugestao = EstruturaFicticia::sugerirPorBairro($nome);

            $lista[] = [
                'bairro' => $nome,
                'lat' => $lat,
                'lng' => $lng,
                'peso' => $peso,
                // Bairro sem equipe seria buraco na estrutura, e o mapa tem de
                // dizer isso em palavras em vez de fingir cobertura.
                'area' => $sugestao['area'] ?? 'Sem área definida',
                'regiao' => $sugestao['regiao'] ?? '—',
                'equipe' => $sugestao['equipe'] ?? '—',
                'encarregado' => $sugestao['encarregado'] ?? '—',
                'tambem_de' => array_values(array_map(
                    static fn (array $a): string => $a['equipe'].' · '.$a['area'],
                    (array) ($sugestao['alternativas'] ?? []),
                )),
            ];
        }

        return $lista;
    }

    /**
     * O que entrou hoje: os registros que o aplicativo do fiscal mandou.
     *
     * São pontos de OCORRÊNCIA, e não de cadastro — é o que o mapa ao vivo tem de
     * "vivo". Cada um carrega o fiscal, o bairro e há quanto tempo chegou, que é o
     * que o painel "últimos registros" mostra.
     *
     * @param  list<array<string, mixed>>  $bairros
     * @return list<array<string, mixed>>
     */
    private static function registrosDeHoje(array $bairros): array
    {
        $registros = [];

        // Quanto tempo faz que cada um chegou, em minutos, do mais recente ao mais
        // antigo: o painel dos últimos registros lê nessa ordem.
        $minutos = 6;

        for ($i = 0; $i < 23; $i++) {
            // Os registros de hoje seguem o peso do bairro: onde há mais comércio
            // de rua, há mais registro. Sorteio uniforme espalharia igual e
            // apagaria justamente o relevo que a tela existe para mostrar.
            $bairro = self::bairroPorPeso($bairros);
            $pessoa = self::PESSOAS[self::proximo(count(self::PESSOAS))];
            $fiscal = self::FISCAIS_EM_CAMPO[self::proximo(count(self::FISCAIS_EM_CAMPO))];

            [$lat, $lng] = self::disperso($bairro['lat'], $bairro['lng']);

            $irregular = self::proximo(100) < 58;
            $ocorrencias = $irregular ? self::OCORRENCIAS_IRREGULARES : self::OCORRENCIAS_REGULARES;

            $registros[] = [
                'id' => 'R'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'protocolo' => 'FSC-20260902'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'apelido' => $pessoa[1],
                'atividade' => $pessoa[2],
                'emoji' => $pessoa[3],
                'situacao' => $irregular ? 'irregular' : 'regular',
                'ocorrencia' => $ocorrencias[self::proximo(count($ocorrencias))],
                'fiscal' => $fiscal[0],
                'bairro' => $bairro['bairro'],
                'area' => $bairro['area'],
                'regiao' => $bairro['regiao'],
                'equipe' => $bairro['equipe'],
                'lat' => $lat,
                'lng' => $lng,
                'turno' => self::proximo(100) < 18 ? 'Noturno' : 'Diurno',
                'ha_minutos' => $minutos,
            ];

            $minutos += 8 + self::proximo(26);
        }

        return $registros;
    }

    /**
     * Os fiscais em campo agora, com o último ponto conhecido.
     *
     * @param  list<array<string, mixed>>  $bairros
     * @return list<array<string, mixed>>
     */
    private static function fiscaisEmCampo(array $bairros): array
    {
        $fiscais = [];

        foreach (self::FISCAIS_EM_CAMPO as $i => [$sobrenome, $matricula, $nome]) {
            // Só parte da equipe está na rua num instante qualquer — quatro dos
            // oito. Desenhar os oito faria o painel prometer uma cobertura que a
            // escala não tem.
            if ($i % 2 === 1) {
                continue;
            }

            $bairro = self::bairroPorPeso($bairros);
            [$lat, $lng] = self::disperso($bairro['lat'], $bairro['lng'], 0.004);

            $partes = explode(' ', $nome);

            $fiscais[] = [
                'id' => $matricula,
                'nome' => $nome,
                'curto' => 'fiscal '.$sobrenome,
                'matricula' => $matricula,
                'iniciais' => mb_substr($partes[0], 0, 1).mb_substr(end($partes), 0, 1),
                'bairro' => $bairro['bairro'],
                'area' => $bairro['area'],
                'equipe' => $bairro['equipe'],
                'lat' => $lat,
                'lng' => $lng,
                'turno' => 'Diurno',
                'em_campo_ha' => 40 + self::proximo(230),
                'registros_hoje' => 1 + self::proximo(6),
            ];
        }

        return $fiscais;
    }

    /**
     * As equipes, como o filtro do gestor precisa delas.
     *
     * O `recorte` viaja porque ele MUDA o significado do filtro: equipe de bairros
     * seleciona por geografia; a Noturna, cujo recorte é o TURNO, seleciona o que
     * foi registrado à noite em qualquer bairro. Sem o recorte, filtrar pela
     * Noturna devolveria uma cidade vazia — leitura exatamente invertida, já que
     * ela cobre Salvador inteira.
     *
     * @return list<array<string, string>>
     */
    private static function equipes(): array
    {
        return array_values(array_map(static fn (array $e): array => [
            'equipe' => $e['equipe'],
            'area' => $e['area'],
            'regiao' => $e['regiao'],
            'encarregado' => $e['encarregado'],
            'recorte' => $e['recorte'],
            'turno' => $e['turno'],
        ], EstruturaFicticia::equipes()));
    }

    /**
     * Um bairro sorteado com PESO — o de mais comércio de rua sai mais vezes.
     *
     * @param  list<array<string, mixed>>  $bairros
     * @return array<string, mixed>
     */
    private static function bairroPorPeso(array $bairros): array
    {
        $total = array_sum(array_column($bairros, 'peso'));
        $alvo = self::proximo($total);

        foreach ($bairros as $bairro) {
            $alvo -= (int) $bairro['peso'];

            if ($alvo < 0) {
                return $bairro;
            }
        }

        return $bairros[0];
    }

    /**
     * Espalha um ponto ao redor do centro do bairro.
     *
     * A coordenada do bairro é uma só, e cinquenta pinos empilhados nela seriam um
     * pino. O raio (~0,005° ≈ 550 m) é a ordem de grandeza de um bairro de
     * Salvador: menor, os pinos colam; maior, eles caem no bairro vizinho e o mapa
     * passa a mentir sobre a área da equipe.
     *
     * @return array{0: float, 1: float}
     */
    private static function disperso(float $lat, float $lng, float $raio = 0.005): array
    {
        return [
            round($lat + ($raio * (self::proximo(2001) - 1000) / 1000), 6),
            round($lng + ($raio * (self::proximo(2001) - 1000) / 1000), 6),
        ];
    }

    /** Uma data no formato do sistema (dd/mm/aaaa), N dias atrás. */
    private static function dataBrAtras(int $dias): string
    {
        return now()->subDays($dias)->format('d/m/Y');
    }

    /**
     * O sorteio de semente fixa — ver o cabeçalho da classe.
     *
     * É um gerador congruente linear escrito à mão, e não `mt_rand()` com semente:
     * `mt_srand()` mexe no gerador GLOBAL do processo, e nossa cidade determinista
     * passaria a alterar o sorteio de qualquer outra coisa na mesma requisição.
     */
    private static function proximo(int $limite): int
    {
        self::$estado = (self::$estado * 1103515245 + 12345) & 0x7FFFFFFF;

        return intdiv(self::$estado, 65536) % max(1, $limite);
    }
}

<?php

namespace App\Relatorios;

use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use App\Relatorios\Contracts\Relatorio;
use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\FiltroDef;
use App\Relatorios\Suporte\ResultadoRelatorio;
use App\Support\Texto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ambulantes cadastrados — quem está na base, em que ramo e em que situação.
 *
 * Responde a três perguntas de gestão que ninguém consegue responder de cabeça:
 * "quantos cadastros temos, e quantos ainda esperam conferência?", "quem foi
 * cadastrado neste período, por ramo?" e — desde que ser permissionário virou
 * atributo — "**quantos têm permissão da SEMOP, e quantos não têm?**". A segunda
 * é a que justifica o período: o cadastro em campo cresce por mutirão, e o número
 * por si só não conta a história. A terceira é a que dimensiona o trabalho
 * educativo, que é justamente com quem não tem permissão.
 *
 * Convive com o relatório de contas do sistema — são coisas diferentes: aquele é
 * sobre QUEM USA o sistema, este é sobre QUEM É FISCALIZADO.
 */
class RelatorioAmbulantes implements Relatorio
{
    /** Os dois recortes do filtro de permissão — texto, porque filtro viaja como texto. */
    private const COM_PERMISSAO = 'sim';

    private const SEM_PERMISSAO = 'nao';

    public function chave(): string
    {
        return 'ambulantes';
    }

    public function titulo(): string
    {
        return 'Ambulantes cadastrados';
    }

    public function grupo(): string
    {
        return 'Fiscalização';
    }

    public function descricao(): string
    {
        return 'Cadastros por período, atividade, situação e permissão da SEMOP, com o quanto ainda espera a validação do Chefe de Setor. Responde "quem está na base, quem tem permissão e o que falta conferir".';
    }

    public function filtros(): array
    {
        $situacoes = [['valor' => '', 'rotulo' => 'Todas']];

        foreach (Ambulante::SITUACOES as $situacao) {
            $situacoes[] = ['valor' => $situacao, 'rotulo' => $situacao];
        }

        $atividades = [['valor' => '', 'rotulo' => 'Todas as atividades']];

        foreach (AtividadeAmbulante::query()->orderBy('nome')->get() as $atividade) {
            $atividades[] = [
                'valor' => (string) $atividade->getKey(),
                'rotulo' => (string) $atividade->nome,
            ];
        }

        return [
            FiltroDef::data('data_inicial', 'Cadastrado a partir de'),
            FiltroDef::data('data_final', 'Cadastrado até'),
            FiltroDef::select('situacao', 'Situação', $situacoes),
            FiltroDef::select('atividade_id', 'Atividade', $atividades),
            // Permissão e situação são perguntas DIFERENTES (um ambulante sem
            // permissão pode estar regular), então são dois filtros — juntá-los
            // num só obrigaria a inventar combinações que não existem.
            FiltroDef::select('permissionario', 'Permissão da SEMOP', [
                ['valor' => '', 'rotulo' => 'Todos'],
                ['valor' => self::COM_PERMISSAO, 'rotulo' => 'Permissionários'],
                ['valor' => self::SEM_PERMISSAO, 'rotulo' => 'Sem permissão'],
            ]),
        ];
    }

    public function modos(): array
    {
        return [ContextoRelatorio::MODO_ANALITICO, ContextoRelatorio::MODO_GERENCIAL];
    }

    public function gerar(ContextoRelatorio $contexto): ResultadoRelatorio
    {
        $consulta = Ambulante::query()->with('atividade')->orderBy('nome');

        $inicio = self::dataFiltro($contexto->filtro('data_inicial'));
        $fim = self::dataFiltro($contexto->filtro('data_final'));
        $situacao = $contexto->filtro('situacao');
        $atividadeId = $contexto->filtro('atividade_id');
        $permissao = $contexto->filtro('permissionario');

        if ($inicio !== null) {
            $consulta->where('created_at', '>=', $inicio->startOfDay());
        }

        if ($fim !== null) {
            // Fim de dia INCLUSIVE: "até 20/08" tem de conter quem foi cadastrado
            // às 15h de 20/08.
            $consulta->where('created_at', '<=', $fim->endOfDay());
        }

        if (is_string($situacao) && in_array($situacao, Ambulante::SITUACOES, true)) {
            $consulta->where('situacao', $situacao);
        }

        if (is_string($atividadeId) && $atividadeId !== '') {
            $consulta->where('atividade_id', (int) $atividadeId);
        }

        if ($permissao === self::COM_PERMISSAO || $permissao === self::SEM_PERMISSAO) {
            $consulta->where('permissionario', $permissao === self::COM_PERMISSAO);
        }

        $ambulantes = $consulta->get();

        $resultado = new ResultadoRelatorio;
        $resultado->metadados = [
            'titulo' => mb_strtoupper($this->titulo()),
            'filtros_resumo' => $this->resumo($inicio, $fim, $situacao, $atividadeId, $permissao, $ambulantes->count()),
            // Nove colunas não cabem em pé.
            'orientacao' => 'landscape',
        ];

        // O gerencial responde "quanto"; o analítico, "quem". A relação nominal
        // inteira afogaria o número num documento de gestão.
        if (! $contexto->ehGerencial()) {
            $relacao = $resultado->secao('Relação de ambulantes');
            $relacao->coluna('codigo', 'Código');
            $relacao->coluna('nome', 'Nome');
            $relacao->coluna('apelido', 'Apelido');
            $relacao->coluna('documento', 'Documento');
            $relacao->coluna('atividade', 'Atividade');
            // A coluna que o rename tornou indispensável: sem ela o documento
            // não diz quem tem permissão, e é essa a diferença que a gestão pede.
            $relacao->coluna('permissionario', 'Permissionário', 'texto', 'center');
            $relacao->coluna('permissao', 'Nº permissão');
            $relacao->coluna('validade', 'Validade', 'texto', 'center');
            $relacao->coluna('situacao', 'Situação', 'texto', 'center');

            foreach ($ambulantes as $p) {
                $relacao->linha([
                    'codigo' => $p->codigo,
                    'nome' => $p->nome,
                    'apelido' => $p->apelido ?? '—',
                    // Sem documento é o caso NORMAL aqui, e o documento tem de
                    // sair legível — não na forma crua da coluna.
                    'documento' => $p->documentoFormatado() ?: '—',
                    'atividade' => $p->atividade->nome,
                    // "Sim"/"Não", e não um traço: aqui a resposta negativa é
                    // informação, não ausência de informação.
                    'permissionario' => $p->permissionario ? 'Sim' : 'Não',
                    'permissao' => $p->numero_permissao ?? '—',
                    // Data SEMPRE em BR, como em todo o sistema.
                    'validade' => $p->validade_permissao?->format('d/m/Y') ?? '—',
                    'situacao' => $p->situacao,
                ]);
            }

            $relacao->total('TOTAL DE CADASTROS', $ambulantes->count(), [
                'nome' => Texto::contar($ambulantes->count(), 'cadastro', 'cadastros'),
            ]);
        }

        $porSituacao = $this->contarPorSituacao($ambulantes);

        $quadro = $resultado->secao('Cadastros por situação');
        $quadro->coluna('situacao', 'Situação');
        $quadro->coluna('cadastros', 'Cadastros', 'numero', 'right');

        foreach ($porSituacao as $nome => $quantidade) {
            $quadro->linha(['situacao' => $nome, 'cadastros' => (string) $quantidade]);
        }

        $quadro->total('TOTAL', $ambulantes->count(), ['cadastros' => $ambulantes->count()]);

        /*
         * Com permissão × sem permissão. É o quadro que responde a pergunta nova
         * do cenário: quem não tem permissão é a maior parte do trabalho de
         * campo, e antes do rename esse número simplesmente não existia — a base
         * inteira se chamava "permissionários".
         */
        $comPermissao = $ambulantes->filter(fn (Ambulante $a): bool => (bool) $a->permissionario)->count();

        $quadroPermissao = $resultado->secao('Cadastros por permissão da SEMOP');
        $quadroPermissao->coluna('permissao', 'Permissão');
        $quadroPermissao->coluna('cadastros', 'Cadastros', 'numero', 'right');
        $quadroPermissao->linha(['permissao' => 'Permissionários', 'cadastros' => (string) $comPermissao]);
        $quadroPermissao->linha(['permissao' => 'Sem permissão', 'cadastros' => (string) ($ambulantes->count() - $comPermissao)]);
        $quadroPermissao->total('TOTAL', $ambulantes->count(), ['cadastros' => $ambulantes->count()]);

        $porAtividade = $this->contarPorAtividade($ambulantes);

        $ramos = $resultado->secao('Cadastros por atividade');
        $ramos->coluna('atividade', 'Atividade');
        $ramos->coluna('cadastros', 'Cadastros', 'numero', 'right');

        foreach ($porAtividade as $nome => $quantidade) {
            $ramos->linha(['atividade' => $nome, 'cadastros' => (string) $quantidade]);
        }

        $ramos->total('TOTAL', $ambulantes->count(), ['cadastros' => $ambulantes->count()]);

        if ($contexto->querGraficos() && $porSituacao !== []) {
            $resultado->grafico(
                'bar',
                'Cadastros por situação',
                array_map('strval', array_keys($porSituacao)),
                [['nome' => 'Cadastros', 'valores' => array_values($porSituacao)]],
            );
        }

        return $resultado;
    }

    /**
     * Quantos por situação — as TRÊS sempre presentes, mesmo zeradas.
     *
     * O zero é o dado mais útil deste quadro: "nenhum aguardando validação" é
     * uma resposta, e uma linha ausente seria lida como "não sei".
     *
     * @param  Collection<int, Ambulante>  $ambulantes
     * @return array<string, int>
     */
    private function contarPorSituacao(Collection $ambulantes): array
    {
        $contagem = array_fill_keys(Ambulante::SITUACOES, 0);

        foreach ($ambulantes as $p) {
            $contagem[$p->situacao] = ($contagem[$p->situacao] ?? 0) + 1;
        }

        return $contagem;
    }

    /**
     * Quantos por ramo — só os ramos que aparecem, do maior para o menor.
     *
     * Aqui o zero não ajuda: a lista de atividades é aberta e cresce, e dezenas
     * de linhas zeradas esconderiam as que têm gente.
     *
     * @param  Collection<int, Ambulante>  $ambulantes
     * @return array<string, int>
     */
    private function contarPorAtividade(Collection $ambulantes): array
    {
        $contagem = [];

        foreach ($ambulantes as $p) {
            $rotulo = $p->atividade->nome;
            $contagem[$rotulo] = ($contagem[$rotulo] ?? 0) + 1;
        }

        arsort($contagem);

        return $contagem;
    }

    /** O recorte por escrito — é o que o documento imprime para se explicar depois. */
    private function resumo(?Carbon $inicio, ?Carbon $fim, mixed $situacao, mixed $atividadeId, mixed $permissao, int $total): string
    {
        $partes = [];

        if ($inicio !== null || $fim !== null) {
            $partes[] = 'Cadastro: '
                .($inicio?->format('d/m/Y') ?? 'início')
                .' a '.($fim?->format('d/m/Y') ?? 'hoje');
        }

        if (is_string($situacao) && in_array($situacao, Ambulante::SITUACOES, true)) {
            $partes[] = 'Situação: '.$situacao;
        }

        if ($permissao === self::COM_PERMISSAO || $permissao === self::SEM_PERMISSAO) {
            $partes[] = 'Permissão: '.($permissao === self::COM_PERMISSAO ? 'permissionários' : 'sem permissão');
        }

        if (is_string($atividadeId) && $atividadeId !== '') {
            // Pelo nome, e não pelo número: o documento é lido meses depois, por
            // quem não faz ideia de que atividade é a "3".
            $nome = (string) AtividadeAmbulante::query()->whereKey((int) $atividadeId)->value('nome');
            $partes[] = 'Atividade: '.($nome === '' ? $atividadeId : $nome);
        }

        if ($partes === []) {
            $partes[] = 'Todos os cadastros';
        }

        $partes[] = Texto::contar($total, 'cadastro', 'cadastros');

        return implode(' · ', $partes);
    }

    /** A data de um filtro (ISO, como o `<input type="date">` a manda), ou null. */
    private static function dataFiltro(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}

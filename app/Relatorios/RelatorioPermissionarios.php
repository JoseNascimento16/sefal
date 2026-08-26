<?php

namespace App\Relatorios;

use App\Models\AtividadeAmbulante;
use App\Models\Permissionario;
use App\Relatorios\Contracts\Relatorio;
use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\FiltroDef;
use App\Relatorios\Suporte\ResultadoRelatorio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Permissionários cadastrados — quem está na base, em que ramo e em que situação.
 *
 * Responde a duas perguntas de gestão que ninguém consegue responder de cabeça:
 * "quantos cadastros temos, e quantos ainda esperam conferência?" e "quem foi
 * cadastrado neste período, por ramo?". A segunda é a que justifica o período: o
 * cadastro em campo cresce por mutirão, e o número por si só não conta a história.
 *
 * Convive com o relatório de contas do sistema — são coisas diferentes: aquele é
 * sobre QUEM USA o sistema, este é sobre QUEM É FISCALIZADO.
 */
class RelatorioPermissionarios implements Relatorio
{
    public function chave(): string
    {
        return 'permissionarios';
    }

    public function titulo(): string
    {
        return 'Permissionários cadastrados';
    }

    public function grupo(): string
    {
        return 'Fiscalização';
    }

    public function descricao(): string
    {
        return 'Cadastros por período, atividade e situação, com o quanto ainda espera a validação do gestor. Responde "quem está na base, e o que falta conferir".';
    }

    public function filtros(): array
    {
        $situacoes = [['valor' => '', 'rotulo' => 'Todas']];

        foreach (Permissionario::SITUACOES as $situacao) {
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
        ];
    }

    public function modos(): array
    {
        return [ContextoRelatorio::MODO_ANALITICO, ContextoRelatorio::MODO_GERENCIAL];
    }

    public function gerar(ContextoRelatorio $contexto): ResultadoRelatorio
    {
        $consulta = Permissionario::query()->with('atividade')->orderBy('nome');

        $inicio = self::dataFiltro($contexto->filtro('data_inicial'));
        $fim = self::dataFiltro($contexto->filtro('data_final'));
        $situacao = $contexto->filtro('situacao');
        $atividadeId = $contexto->filtro('atividade_id');

        if ($inicio !== null) {
            $consulta->where('created_at', '>=', $inicio->startOfDay());
        }

        if ($fim !== null) {
            // Fim de dia INCLUSIVE: "até 20/08" tem de conter quem foi cadastrado
            // às 15h de 20/08.
            $consulta->where('created_at', '<=', $fim->endOfDay());
        }

        if (is_string($situacao) && in_array($situacao, Permissionario::SITUACOES, true)) {
            $consulta->where('situacao', $situacao);
        }

        if (is_string($atividadeId) && $atividadeId !== '') {
            $consulta->where('atividade_id', (int) $atividadeId);
        }

        $permissionarios = $consulta->get();

        $resultado = new ResultadoRelatorio;
        $resultado->metadados = [
            'titulo' => mb_strtoupper($this->titulo()),
            'filtros_resumo' => $this->resumo($inicio, $fim, $situacao, $atividadeId, $permissionarios->count()),
            // Oito colunas não cabem em pé.
            'orientacao' => 'landscape',
        ];

        // O gerencial responde "quanto"; o analítico, "quem". A relação nominal
        // inteira afogaria o número num documento de gestão.
        if (! $contexto->ehGerencial()) {
            $relacao = $resultado->secao('Relação de permissionários');
            $relacao->coluna('codigo', 'Código');
            $relacao->coluna('nome', 'Nome');
            $relacao->coluna('apelido', 'Apelido');
            $relacao->coluna('documento', 'Documento');
            $relacao->coluna('atividade', 'Atividade');
            $relacao->coluna('permissao', 'Nº permissão');
            $relacao->coluna('validade', 'Validade', 'texto', 'center');
            $relacao->coluna('situacao', 'Situação', 'texto', 'center');

            foreach ($permissionarios as $p) {
                $relacao->linha([
                    'codigo' => $p->codigo,
                    'nome' => $p->nome,
                    'apelido' => $p->apelido ?? '—',
                    // Sem documento é o caso NORMAL aqui, e o documento tem de
                    // sair legível — não na forma crua da coluna.
                    'documento' => $p->documentoFormatado() ?: '—',
                    'atividade' => $p->atividade->nome,
                    'permissao' => $p->numero_permissao ?? '—',
                    // Data SEMPRE em BR, como em todo o sistema.
                    'validade' => $p->validade_permissao?->format('d/m/Y') ?? '—',
                    'situacao' => $p->situacao,
                ]);
            }

            $relacao->total('TOTAL DE CADASTROS', $permissionarios->count(), [
                'nome' => $permissionarios->count().' cadastro(s)',
            ]);
        }

        $porSituacao = $this->contarPorSituacao($permissionarios);

        $quadro = $resultado->secao('Cadastros por situação');
        $quadro->coluna('situacao', 'Situação');
        $quadro->coluna('cadastros', 'Cadastros', 'numero', 'right');

        foreach ($porSituacao as $nome => $quantidade) {
            $quadro->linha(['situacao' => $nome, 'cadastros' => (string) $quantidade]);
        }

        $quadro->total('TOTAL', $permissionarios->count(), ['cadastros' => $permissionarios->count()]);

        $porAtividade = $this->contarPorAtividade($permissionarios);

        $ramos = $resultado->secao('Cadastros por atividade');
        $ramos->coluna('atividade', 'Atividade');
        $ramos->coluna('cadastros', 'Cadastros', 'numero', 'right');

        foreach ($porAtividade as $nome => $quantidade) {
            $ramos->linha(['atividade' => $nome, 'cadastros' => (string) $quantidade]);
        }

        $ramos->total('TOTAL', $permissionarios->count(), ['cadastros' => $permissionarios->count()]);

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
     * @param  Collection<int, Permissionario>  $permissionarios
     * @return array<string, int>
     */
    private function contarPorSituacao(Collection $permissionarios): array
    {
        $contagem = array_fill_keys(Permissionario::SITUACOES, 0);

        foreach ($permissionarios as $p) {
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
     * @param  Collection<int, Permissionario>  $permissionarios
     * @return array<string, int>
     */
    private function contarPorAtividade(Collection $permissionarios): array
    {
        $contagem = [];

        foreach ($permissionarios as $p) {
            $rotulo = $p->atividade->nome;
            $contagem[$rotulo] = ($contagem[$rotulo] ?? 0) + 1;
        }

        arsort($contagem);

        return $contagem;
    }

    /** O recorte por escrito — é o que o documento imprime para se explicar depois. */
    private function resumo(?Carbon $inicio, ?Carbon $fim, mixed $situacao, mixed $atividadeId, int $total): string
    {
        $partes = [];

        if ($inicio !== null || $fim !== null) {
            $partes[] = 'Cadastro: '
                .($inicio?->format('d/m/Y') ?? 'início')
                .' a '.($fim?->format('d/m/Y') ?? 'hoje');
        }

        if (is_string($situacao) && in_array($situacao, Permissionario::SITUACOES, true)) {
            $partes[] = 'Situação: '.$situacao;
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

        $partes[] = $total.' cadastro(s)';

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

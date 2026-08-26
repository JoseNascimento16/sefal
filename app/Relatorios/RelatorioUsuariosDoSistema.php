<?php

namespace App\Relatorios;

use App\Models\User;
use App\Relatorios\Contracts\Relatorio;
use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\FiltroDef;
use App\Relatorios\Suporte\ResultadoRelatorio;
use App\Support\Texto;
use Illuminate\Support\Carbon;

/**
 * Usuários do sistema — quem tem conta, em que setor e desde quando.
 *
 * É o primeiro relatório do SEFAL, e é sobre a única base que já existe nesta
 * fase (as contas da Retaguarda). Serve a uma pergunta real de gestão — "quem
 * tem acesso a quê, e desde quando?" —, que é justamente a que se faz ao revisar
 * o Modo Gerente.
 *
 * Vale como a referência de como se escreve um relatório aqui: declarar filtros,
 * montar um {@see ResultadoRelatorio} neutro e não saber nada de PDF, planilha
 * ou documento — quem sabe formato são os exportadores.
 */
class RelatorioUsuariosDoSistema implements Relatorio
{
    public function chave(): string
    {
        return 'usuarios-do-sistema';
    }

    public function titulo(): string
    {
        return 'Usuários do sistema';
    }

    public function grupo(): string
    {
        return 'Sistema';
    }

    public function descricao(): string
    {
        return 'Contas da Retaguarda por setor e situação, com o período de cadastro. Responde "quem tem acesso, e desde quando".';
    }

    public function filtros(): array
    {
        $setores = [['valor' => '', 'rotulo' => 'Todos os setores']];

        foreach ((array) config('retaguarda.setores', []) as $slug => $nome) {
            $setores[] = ['valor' => (string) $slug, 'rotulo' => (string) $nome];
        }

        return [
            FiltroDef::data('data_inicial', 'Cadastrado a partir de'),
            FiltroDef::data('data_final', 'Cadastrado até'),
            FiltroDef::select('setor', 'Setor', $setores),
            FiltroDef::select('situacao', 'Situação', [
                ['valor' => '', 'rotulo' => 'Todas'],
                ['valor' => 'ativo', 'rotulo' => 'Ativo'],
                ['valor' => 'inativo', 'rotulo' => 'Inativo'],
            ]),
        ];
    }

    public function modos(): array
    {
        return [ContextoRelatorio::MODO_ANALITICO, ContextoRelatorio::MODO_GERENCIAL];
    }

    public function gerar(ContextoRelatorio $contexto): ResultadoRelatorio
    {
        $consulta = User::query()->with('setores')->orderBy('name');

        $inicio = self::dataFiltro($contexto->filtro('data_inicial'));
        $fim = self::dataFiltro($contexto->filtro('data_final'));
        $setor = $contexto->filtro('setor');
        $situacao = $contexto->filtro('situacao');

        if ($inicio !== null) {
            $consulta->where('created_at', '>=', $inicio->startOfDay());
        }

        if ($fim !== null) {
            // Fim de dia INCLUSIVE: "até 20/08" tem de conter quem foi cadastrado
            // às 15h de 20/08 — comparar contra a meia-noite excluiria o dia todo.
            $consulta->where('created_at', '<=', $fim->endOfDay());
        }

        if (is_string($setor) && $setor !== '') {
            $consulta->whereHas('setores', fn ($q) => $q->where('slug', $setor));
        }

        if ($situacao === 'ativo' || $situacao === 'inativo') {
            $consulta->where('ativo', $situacao === 'ativo');
        }

        $usuarios = $consulta->get();

        $resultado = new ResultadoRelatorio;
        $resultado->metadados = [
            'titulo' => mb_strtoupper($this->titulo()),
            'filtros_resumo' => $this->resumo($inicio, $fim, $setor, $situacao, $usuarios->count()),
            'orientacao' => 'portrait',
        ];

        // O modo gerencial responde "quanto", o analítico responde "quem". Só o
        // analítico traz a relação nominal: num documento de gestão, a lista
        // inteira de contas afoga o número que se foi buscar.
        if (! $contexto->ehGerencial()) {
            $relacao = $resultado->secao('Relação de contas');
            $relacao->coluna('matricula', 'Matrícula');
            $relacao->coluna('nome', 'Nome');
            $relacao->coluna('setores', 'Setores');
            $relacao->coluna('situacao', 'Situação', 'texto', 'center');
            $relacao->coluna('cadastro', 'Cadastro', 'texto', 'center');

            foreach ($usuarios as $usuario) {
                $relacao->linha([
                    'matricula' => $usuario->login,
                    'nome' => $usuario->name,
                    'setores' => self::setoresDe($usuario),
                    'situacao' => $usuario->ativo ? 'Ativo' : 'Inativo',
                    // Data em BR, como em todo o sistema — nunca a forma do banco.
                    'cadastro' => $usuario->created_at?->format('d/m/Y') ?? '—',
                ]);
            }

            $relacao->total('TOTAL DE CONTAS', $usuarios->count(), ['nome' => Texto::contar($usuarios->count(), 'conta', 'contas')]);
        }

        $porSetor = $this->contarPorSetor($usuarios);

        $quadro = $resultado->secao('Contas por setor');
        $quadro->coluna('setor', 'Setor');
        $quadro->coluna('contas', 'Contas', 'numero', 'right');

        foreach ($porSetor as $nome => $quantidade) {
            $quadro->linha(['setor' => $nome, 'contas' => (string) $quantidade]);
        }

        $quadro->total('TOTAL', $usuarios->count(), ['contas' => $usuarios->count()]);

        if ($contexto->querGraficos() && $porSetor !== []) {
            $resultado->grafico(
                'bar',
                'Contas por setor',
                array_map('strval', array_keys($porSetor)),
                [['nome' => 'Contas', 'valores' => array_values($porSetor)]],
            );
        }

        return $resultado;
    }

    /**
     * Quantas contas por setor.
     *
     * Uma conta com dois setores é contada nos DOIS: a pergunta é "quantas contas
     * este setor alcança", e ela não se responde dividindo gente pela metade. Por
     * isso a soma das linhas pode passar do total — que é o número de contas, e
     * vem do próprio total do quadro.
     *
     * @param  iterable<int, User>  $usuarios
     * @return array<string, int>
     */
    private function contarPorSetor(iterable $usuarios): array
    {
        $nomes = (array) config('retaguarda.setores', []);
        $contagem = [];

        foreach ($usuarios as $usuario) {
            $slugs = $usuario->setores->pluck('slug')->all();

            if ($slugs === []) {
                // Conta sem setor é o caso que mais importa nesta leitura: ela não
                // enxerga tela controlada nenhuma, e some se a linha não existir.
                $contagem['Sem setor'] = ($contagem['Sem setor'] ?? 0) + 1;

                continue;
            }

            foreach ($slugs as $slug) {
                $rotulo = (string) ($nomes[$slug] ?? $slug);
                $contagem[$rotulo] = ($contagem[$rotulo] ?? 0) + 1;
            }
        }

        arsort($contagem);

        return $contagem;
    }

    private static function setoresDe(User $usuario): string
    {
        $nomes = (array) config('retaguarda.setores', []);

        $rotulos = $usuario->setores
            ->map(fn ($setor): string => (string) ($nomes[$setor->slug] ?? $setor->nome))
            ->all();

        return $rotulos === [] ? 'Sem setor' : implode(', ', $rotulos);
    }

    /** O recorte por escrito — é o que o documento imprime para se explicar depois. */
    private function resumo(?Carbon $inicio, ?Carbon $fim, mixed $setor, mixed $situacao, int $total): string
    {
        $nomes = (array) config('retaguarda.setores', []);

        $partes = [];

        if ($inicio !== null || $fim !== null) {
            $partes[] = 'Cadastro: '
                .($inicio?->format('d/m/Y') ?? 'início')
                .' a '.($fim?->format('d/m/Y') ?? 'hoje');
        }

        if (is_string($setor) && $setor !== '') {
            $partes[] = 'Setor: '.(string) ($nomes[$setor] ?? $setor);
        }

        if ($situacao === 'ativo' || $situacao === 'inativo') {
            $partes[] = 'Situação: '.($situacao === 'ativo' ? 'Ativo' : 'Inativo');
        }

        if ($partes === []) {
            $partes[] = 'Todas as contas';
        }

        $partes[] = Texto::contar($total, 'conta', 'contas');

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

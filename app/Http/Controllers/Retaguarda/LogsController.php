<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\LogErro;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Logs — as exceções que o sistema capturou, consultáveis em tela.
 *
 * É SÓ LEITURA, e isso é regra, não economia de esforço: log de erro é a prova do
 * que aconteceu. Uma tela que permitisse editar ou apagar linha daqui apagaria a
 * única trilha de um defeito de produção — e ninguém apaga o que não incomoda.
 *
 * Regra de negócio em `docs/regras-de-negocio/observabilidade-e-monitoramento.md`.
 */
class LogsController extends Controller
{
    /**
     * Teto de linhas carregadas. A tabela é das que mais crescem no sistema, e um
     * surto de erros repetidos traria dezenas de milhares de linhas para o
     * navegador — a tela de diagnóstico cairia justamente no dia em que fosse
     * mais necessária.
     */
    private const LIMITE = 500;

    /** Tamanho da janela quando ninguém escolhe o período. */
    private const DIAS_PADRAO = 7;

    /**
     * As colunas da LISTAGEM. O `stack` fica FORA de propósito: é campo longo
     * (CLOB no Oracle) e cada um custa uma ida ao banco por linha — no sistema
     * irmão, trazê-lo na lista derrubou esta mesma tela por tempo esgotado com
     * apenas setenta registros. Ele é carregado em {@see self::detalhe()}, uma
     * ocorrência de cada vez, que não pesa.
     *
     * @var list<string>
     */
    private const COLUNAS_DA_LISTA = ['id', 'request_id', 'classe', 'mensagem', 'url', 'metodo', 'user_id', 'created_at'];

    public function index(Request $request): Response
    {
        $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        /*
         * O período é a JANELA dos dados, não um filtro paralelo à busca: ele
         * decide o que o servidor traz, e a busca inteligente da tela é quem
         * recorta o que veio. Sem a janela, o teto de linhas transformaria o
         * período numa promessa falsa — a pessoa pediria "o mês passado" e
         * receberia só o que coubesse dos últimos dias.
         */
        $de = $this->data($request->query('de')) ?? Carbon::now()->subDays(self::DIAS_PADRAO)->startOfDay();
        $ate = $this->data($request->query('ate')) ?? Carbon::now();

        $logs = LogErro::query()
            ->select(self::COLUNAS_DA_LISTA)
            ->with('usuario:id,name,login')
            ->whereBetween('created_at', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (LogErro $log): array => [
                'id' => $log->id,
                'requestId' => $log->request_id,
                // ISO na ponte, BR na tela: a conversão é da tela (`dataHoraBR`),
                // para ordenar e comparar continuar sendo possível aqui.
                'ocorridoEm' => $log->created_at?->format('Y-m-d\TH:i:s'),
                'classe' => $log->classe,
                'mensagem' => $log->mensagem,
                // Já gravado como CAMINHO, sem a consulta e com os trechos
                // sensíveis mascarados — ver `LogErro::caminhoSeguro()`.
                'caminho' => $log->url,
                'metodo' => $log->metodo,
                'usuario' => $log->usuario?->name,
            ]);

        return Inertia::render('Retaguarda/Sistema/Logs', [
            'logs' => $logs,
            'janela' => [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
            ],
            'limite' => self::LIMITE,
            // Dito em voz alta na tela: sem isto, quem vê 500 linhas acha que são
            // todas, e conclui que o surto parou quando ele só transbordou.
            'truncado' => $logs->count() >= self::LIMITE,
        ]);
    }

    /**
     * O rastro de UMA ocorrência — o campo longo que a listagem não carrega.
     *
     * É leitura, e por isso um GET: nada aqui altera o registro. O endereço leva
     * só o número da ocorrência, que não tem como carregar assinatura de injeção
     * de SQL e ser barrado pelo WAF a caminho.
     *
     * @return array{stack: string}
     */
    public function detalhe(LogErro $log): array
    {
        return ['stack' => (string) ($log->stack ?? '')];
    }

    /** Uma data do pedido, ou nulo quando ela não veio (ou veio ilegível). */
    private function data(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Services\Monitoramento\MonitorParametrizacoes;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoramento — o painel de "tudo verde, sistema operacional".
 *
 * A tela é só leitura: ela não conserta nada, ela DIZ o que quebrou e leva para
 * onde se corrige. Por isso não há mutação nenhuma sob este caminho.
 *
 * As verificações baratas rodam ao abrir; as profundas (escrita real em disco,
 * conversa com serviço externo) só pelo botão — a tela de diagnóstico não pode
 * depender do que está diagnosticando para conseguir aparecer.
 *
 * Regra de negócio em `docs/regras-de-negocio/observabilidade-e-monitoramento.md`.
 */
class MonitoramentoParametrizacoesController extends Controller
{
    public function __construct(private readonly MonitorParametrizacoes $monitor) {}

    public function index(): Response
    {
        return Inertia::render('Retaguarda/Sistema/MonitoramentoDeParametrizacoes', [
            'modulos' => $this->monitor->executarTodos(),
            // Data em BR, como tudo que o usuário lê.
            'verificadoEm' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * As verificações profundas, sob demanda.
     *
     * Devolve JSON, e não uma nova renderização da página: o resultado SUBSTITUI
     * o estado dos checks correspondentes na tela que já está aberta — recarregar
     * a página inteira jogaria fora o que a pessoa acabou de ler.
     */
    public function profundo(): JsonResponse
    {
        return response()->json([
            'resultados' => $this->monitor->executarProfundos(),
            'verificadoEm' => now()->format('d/m/Y H:i'),
        ]);
    }
}

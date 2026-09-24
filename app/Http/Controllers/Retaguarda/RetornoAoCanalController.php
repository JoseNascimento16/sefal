<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Demanda;
use App\Services\ESalvador\EscritaNaoLiberada;
use App\Support\Papel;
use App\Support\RetornoAoCanal;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * O retorno ao canal — o Chefe de Setor fecha o ciclo de uma demanda concluída.
 *
 * Um controller, duas portas: a mesma ação vive sob `denuncias/` (a demanda do
 * e-Salvador, respondida no processo de origem) e sob `caixa-de-entrada/` (a
 * avulsa, que vira processo novo). É de propósito: a guarda de acesso deduz a
 * tela do primeiro trecho do caminho, e cada porta herda a permissão da tela em
 * que o chefe já está.
 *
 * O ato é do CHEFE (e do administrador, que cobre a ausência dele): é ele quem
 * responde ao canal, como é ele quem recebe dele. O líder devolve o resultado ao
 * chefe pelo fluxo; não fala com o e-Salvador. A regra do que pode ser
 * respondido — canal, situação, uma vez só — mora em {@see RetornoAoCanal}.
 */
class RetornoAoCanalController extends Controller
{
    public function __construct(private readonly RetornoAoCanal $retorno) {}

    public function store(Request $request, Demanda $demanda): RedirectResponse
    {
        $usuario = $request->user();

        if (! Papel::ehChefe($usuario) && ! ($usuario?->ehAdmin() ?? false)) {
            return back()->with(
                'flash.erro',
                'O retorno ao canal é do Chefe de Setor: é ele quem responde ao e-Salvador, como é ele quem '
                .'recebe dele. Devolva o resultado a ele pelo fluxo.',
            );
        }

        $dados = $request->validate([
            'texto' => ['required', 'string', 'min:15', 'max:4000'],
            'processo' => ['nullable', 'string', 'max:40', 'regex:/^[0-9.\/-]+$/'],
        ], [
            'texto.required' => 'Escreva o que a fiscalização apurou: é isso que o requerente vai ler.',
            'texto.min' => 'A resposta está curta demais para dizer ao requerente o que foi feito.',
            'processo.regex' => 'O número do processo tem só dígitos, ponto, barra e hífen (ex.: 215.5382.001234/2026).',
        ]);

        try {
            $this->retorno->registrar($demanda, $usuario, $dados['texto'], $dados['processo'] ?? null);
        } catch (DomainException|EscritaNaoLiberada $e) {
            return back()->with('flash.erro', $e->getMessage());
        }

        $demanda->refresh();

        return back()->with(
            'flash.sucesso',
            RetornoAoCanal::tipoDe($demanda) === RetornoAoCanal::PROCESSO
                ? "Abertura do processo {$demanda->processo_esalvador} registrada para a demanda {$demanda->protocolo}."
                : "Resposta ao e-Salvador registrada para a demanda {$demanda->protocolo}"
                    .($demanda->processo_esalvador !== null ? " (processo {$demanda->processo_esalvador})." : '.'),
        );
    }
}

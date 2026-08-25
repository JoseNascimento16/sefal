<?php

namespace App\Services\Monitoramento;

use Closure;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * UMA verificação da tela de Monitoramento: uma condição mínima para um fluxo do
 * sistema funcionar, com a verificação e o CAMINHO DA CORREÇÃO amarrados juntos.
 *
 * ── O critério de admissão (a parte mais importante) ──────────────────────────
 *
 * Entra aqui somente o que, faltando, QUEBRA um fluxo EM SILÊNCIO. É a diferença
 * entre uma tela que se lê em dez segundos e um inventário de parametrizações que
 * ninguém abre:
 *
 *   ✅ entra: não existe administrador ativo → ninguém distribui acesso, e o
 *      sistema fica sem dono sem nunca dizer isso a ninguém;
 *   ❌ não entra: lookup descritivo (a cor de um selo, a lista de bairros). A
 *      ausência incomoda, não quebra.
 *
 * ── A saída é obrigatória, por construção ─────────────────────────────────────
 *
 * Todo check diz PARA ONDE IR: a rota da tela onde se corrige, ou uma instrução
 * quando a correção não tem tela (ambiente, comando, administrador). Alarme sem
 * porta não compila — o construtor recusa.
 */
class CheckParametrizacao
{
    /**
     * @param  Closure(): ResultadoCheck  $verificacao  Consulta BARATA (banco/configuração) — roda ao abrir a tela.
     * @param  Closure(): ResultadoCheck|null  $verificacaoProfunda  Opcional, toca rede/disco de verdade — só sob demanda.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $titulo,
        private readonly Closure $verificacao,
        public readonly ?string $rota = null,
        public readonly ?string $rotaRotulo = null,
        public readonly ?string $instrucao = null,
        private readonly ?Closure $verificacaoProfunda = null,
    ) {
        if ($rota === null && $instrucao === null) {
            throw new \InvalidArgumentException(
                "O check '{$id}' não diz para onde ir: informe a rota da tela de correção ou a instrução.",
            );
        }
    }

    /**
     * Executa a verificação sem deixar exceção derrubar a tela.
     *
     * Esta tela é o instrumento de diagnóstico — ela precisa ABRIR justamente
     * quando as coisas estão quebradas. Um check que estoura (tabela que não
     * existe, banco fora, migração pendente) vira item vermelho legível, nunca um
     * erro 500 que apaga o diagnóstico dos outros checks junto.
     */
    public function executar(): ResultadoCheck
    {
        try {
            return ($this->verificacao)();
        } catch (Throwable $e) {
            report($e);

            // A mensagem CRUA fica de fora de propósito: erro de banco carrega
            // SQL, host, porta e valores — infraestrutura que não pode aparecer
            // na tela. O `report()` acima já guardou o erro completo, e a tela de
            // Logs o entrega a quem tem acesso a ela.
            return ResultadoCheck::falha(
                'A verificação não pôde ser executada — isso costuma indicar atualização de banco pendente ou banco '
                .'indisponível. O erro completo foi registrado em Sistema › Logs para o suporte.',
            );
        }
    }

    public function temVerificacaoProfunda(): bool
    {
        return $this->verificacaoProfunda !== null;
    }

    /**
     * Executa a verificação PROFUNDA (a que toca rede ou disco de verdade), com a
     * mesma blindagem da barata.
     */
    public function executarProfunda(): ResultadoCheck
    {
        if ($this->verificacaoProfunda === null) {
            return $this->executar();
        }

        try {
            return ($this->verificacaoProfunda)();
        } catch (Throwable $e) {
            report($e);

            return ResultadoCheck::falha(
                'A verificação não conseguiu concluir o teste real. O erro completo foi registrado em '
                .'Sistema › Logs para o suporte.',
            );
        }
    }

    /**
     * Serializa para a tela, resolvendo a rota em endereço.
     *
     * Rota inexistente NÃO derruba a tela: degrada para instrução. O teste-lei
     * (`Route::has` para todo check) acusa isso no gate, muito antes de alguém
     * clicar num botão que não leva a lugar nenhum.
     *
     * @return array{id: string, titulo: string, status: string, detalhe: string, acao_url: string|null, acao_rotulo: string|null, instrucao: string|null, profundo: bool}
     */
    public function paraTela(ResultadoCheck $resultado): array
    {
        $url = ($this->rota !== null && Route::has($this->rota)) ? route($this->rota) : null;

        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'status' => $resultado->status,
            'detalhe' => $resultado->detalhe,
            'acao_url' => $url,
            'acao_rotulo' => $url !== null ? ($this->rotaRotulo ?? 'Abrir a tela de correção') : null,
            'instrucao' => $url === null
                ? ($this->instrucao ?? 'A tela de correção não está disponível — procure um administrador.')
                : null,
            'profundo' => $this->temVerificacaoProfunda(),
        ];
    }
}

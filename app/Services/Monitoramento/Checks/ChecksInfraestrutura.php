<?php

namespace App\Services\Monitoramento\Checks;

use App\Models\User;
use App\Services\Monitoramento\CheckParametrizacao;
use App\Services\Monitoramento\ResultadoCheck;
use Illuminate\Support\Facades\Storage;

/**
 * Infraestrutura e ambiente — o que não tem tela de parametrização e, quando
 * falta, para o sistema inteiro sem dizer nada a ninguém.
 *
 * ── Avaliado e DEIXADO DE FORA (para a próxima revisão não reexplorar) ────────
 *
 *  - modo de depuração e endereço público: entram quando houver ambiente
 *    publicado (hoje o deploy ainda não foi ativado — ver `docs/deploy/okd.md`);
 *  - conexão com o Oracle e atualizações de banco pendentes: entram junto com o
 *    banco provisionado, quando houver o que conferir contra a realidade;
 *  - cadastros do domínio (áreas, tipos de documento, motivos): as telas chegam
 *    nas fases seguintes, e o check nasce COM cada uma — check de tela que não
 *    existe é ruído verde permanente;
 *  - batimento do agendador: não há rotina agendada ainda; entra com a primeira.
 *
 * A lista curta é deliberada. Uma tela com sessenta itens verdes não se lê — e o
 * dia em que um ficar vermelho, ninguém repara.
 */
class ChecksInfraestrutura
{
    /** @return list<CheckParametrizacao> */
    public static function checks(): array
    {
        return [
            new CheckParametrizacao(
                id: 'infra-admin-ativo',
                titulo: 'Existe conta de administrador ativa',
                verificacao: function (): ResultadoCheck {
                    // Ativa, e não apenas cadastrada: conta desligada não entra no
                    // sistema, e contá-la faria o check garantir que há quem
                    // administre quando não há mais ninguém.
                    $ativos = User::query()->where('admin', true)->where('ativo', true)->count();

                    if ($ativos === 0) {
                        $desligados = User::query()->where('admin', true)->count();

                        return ResultadoCheck::falha(
                            'Nenhuma conta de administrador ATIVA'
                            .($desligados > 0 ? " (há {$desligados} desligada(s))" : '')
                            .' — ninguém consegue distribuir acesso às telas nem administrar o sistema, e não há como '
                            .'devolver acesso a quem perder o dele.',
                        );
                    }

                    return ResultadoCheck::ok(
                        $ativos.' conta(s) de administrador ativa(s) — o sistema tem quem o administre.',
                    );
                },
                // Ainda não há tela de usuários (ela chega nas próximas entregas);
                // até lá a correção é de ambiente, e a instrução diz isso em vez de
                // apontar para uma tela que não existe.
                instrucao: 'Peça a quem administra o ambiente para reativar uma conta de administrador ou criar uma '
                    .'nova pelo comando de preparação do sistema. Enquanto não houver nenhuma, ninguém consegue '
                    .'conceder acesso a tela alguma.',
            ),

            new CheckParametrizacao(
                id: 'infra-armazenamento-gravavel',
                titulo: 'Armazenamento de arquivos montado e gravável',
                verificacao: function (): ResultadoCheck {
                    $privado = storage_path('app/private');
                    $publico = storage_path('app/public');

                    // Barato de propósito: só pergunta ao sistema de arquivos se o
                    // caminho existe e aceita escrita. A prova REAL (escrever, ler
                    // e apagar) é a verificação profunda — disco lento não pode
                    // atrasar a abertura da tela de diagnóstico.
                    $quebrados = array_keys(array_filter([
                        'privado (fotos e documentos das fiscalizações)' => ! is_dir($privado) || ! is_writable($privado),
                        'público (imagens exibidas nas telas)' => ! is_dir($publico) || ! is_writable($publico),
                    ]));

                    if ($quebrados !== []) {
                        return ResultadoCheck::falha(
                            'O armazenamento de arquivos não está montado ou não aceita escrita — '
                            .implode(' e ', $quebrados).'. Toda foto de fiscalização e todo anexo falha ao enviar, ou é '
                            .'gravado em área temporária e SOME na próxima atualização do sistema.',
                        );
                    }

                    return ResultadoCheck::ok('Os diretórios de arquivos estão montados e aceitam escrita.');
                },
                instrucao: 'Peça a quem administra o ambiente para conferir o volume de arquivos do sistema — se está '
                    .'montado e com permissão de escrita para a aplicação.',
                verificacaoProfunda: function (): ResultadoCheck {
                    // A prova de verdade: escreve, confere e apaga. É o que
                    // distingue "a pasta parece boa" de "o volume está montado" —
                    // volume mal montado aceita a escrita e engole o arquivo.
                    $caminho = '.diagnostico/escrita-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.txt';

                    Storage::disk('local')->put($caminho, (string) now());
                    $gravou = Storage::disk('local')->exists($caminho);
                    Storage::disk('local')->delete($caminho);

                    return $gravou
                        ? ResultadoCheck::ok('Escrita real no armazenamento confirmada: o arquivo foi gravado, lido e apagado.')
                        : ResultadoCheck::falha(
                            'O armazenamento aceitou a escrita, mas o arquivo não apareceu depois — sinal de volume '
                            .'mal montado. Os anexos seriam dados como enviados e não existiriam.',
                        );
                },
            ),
        ];
    }
}

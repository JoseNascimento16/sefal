<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\DemandaAnexo;
use App\Models\FiscalizacaoFoto;
use App\Models\User;
use App\Support\Apresentacao\ArquivoParaTela;
use App\Support\Papel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Os ARQUIVOS do processo — a única porta por onde eles saem (pedido do dono,
 * 25/09/2026).
 *
 * Duas famílias, dois caminhos, cada um sob a tela a que pertence — é pelo
 * primeiro trecho do caminho que a guarda de leitura confere a permissão:
 *
 *  - a FOTO tirada em campo mora em `/retaguarda/fiscalizacoes/fotos/{id}`;
 *  - o ANEXO que veio com a demanda, em `/retaguarda/caixa-de-entrada/anexos/{id}`.
 *
 * Por cima da permissão da tela, o RECORTE do líder: ele vê só o que é das
 * equipes dele — a mesma régua das listas. Sem isso, bastava trocar o número no
 * endereço para abrir a foto de outra equipe.
 *
 * `?baixar=1` entrega como download; sem ele, abre no navegador — só imagem e
 * PDF. O resto é sempre download, com `nosniff`: um HTML disfarçado de anexo não
 * pode rodar dentro do sistema.
 */
class ArquivosController extends Controller
{
    /** O que o navegador pode ABRIR na própria aba; o resto é baixado. */
    private const ABRE_NO_NAVEGADOR = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

    public function foto(Request $request, int $foto): Response
    {
        $registro = FiscalizacaoFoto::with(['fiscalizacao.equipe', 'fiscalizacao.ciclo.equipe'])->findOrFail($foto);
        $vistoria = $registro->fiscalizacao;

        $this->exigirEquipe($request->user(), [
            $vistoria?->equipe?->codigo,
            $vistoria?->ciclo?->equipe?->codigo,
        ]);

        return $this->entregar($request, $registro->caminho, (string) ($registro->legenda ?? basename($registro->caminho)));
    }

    public function anexo(Request $request, int $anexo): Response
    {
        $registro = DemandaAnexo::with(['demanda.equipe', 'demanda.ciclos.equipe'])->findOrFail($anexo);
        $demanda = $registro->demanda;

        $this->exigirEquipe($request->user(), [
            $demanda?->equipe?->codigo,
            ...($demanda?->ciclos->map(static fn ($c): ?string => $c->equipe?->codigo)->all() ?? []),
        ]);

        return $this->entregar($request, $registro->caminho, $registro->nome);
    }

    /**
     * O líder só abre o que é das equipes dele; o Chefe de Setor, o fiscal e o
     * administrador, tudo (as listas deles também não têm recorte).
     *
     * @param  list<string|null>  $equipesDoArquivo
     */
    private function exigirEquipe(?User $usuario, array $equipesDoArquivo): void
    {
        if (! Papel::recorta($usuario)) {
            return;
        }

        $minhas = Papel::equipes($usuario);

        abort_unless(
            array_intersect($minhas, array_filter($equipesDoArquivo)) !== [],
            403,
            'Este arquivo é de outra equipe.',
        );
    }

    private function entregar(Request $request, string $caminho, string $nome): Response
    {
        $disco = Storage::disk(ArquivoParaTela::DISCO);

        // Registro cujo arquivo não está no disco: 404, e a tela já avisa antes
        // (o `disponivel` de cada arquivo) — não oferece link que dá erro.
        abort_unless($disco->exists($caminho), 404, 'O arquivo não foi encontrado no armazenamento.');

        $tipo = (string) ($disco->mimeType($caminho) ?: 'application/octet-stream');
        $cabecalhos = [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('baixar') || ! in_array($tipo, self::ABRE_NO_NAVEGADOR, true)) {
            return $disco->download($caminho, $nome, $cabecalhos);
        }

        return $disco->response($caminho, $nome, ['Content-Type' => $tipo, ...$cabecalhos]);
    }
}

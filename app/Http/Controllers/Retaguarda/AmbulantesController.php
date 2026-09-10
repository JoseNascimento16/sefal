<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use App\Support\ListagensDaRetaguarda;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Ambulantes — CONSULTA da base que o SGCI entrega.
 *
 * ⚠️ Esta tela NÃO CADASTRA, e isso é o desenho, não uma etapa que falta. Decisão
 * do dono (10/09/2026): "a tela de Ambulantes não será CRUD, só irá receber os
 * registros do SGCI via integração". A base de ambulantes é do **SGCI** — o sistema
 * do comércio informal —, e o SEFAL é espelho de leitura dela.
 *
 * Até 10/09/2026 este controller tinha `store`, `update` e `destroy`, desenhados
 * para o pior caso previsto na spec: o SEFAL sendo o MESTRE da base, com o fiscal
 * cadastrando em rua e a gestão validando a quarentena. O cliente decidiu o
 * contrário, e as três rotas saíram do servidor junto com a validação inteira. Sair
 * do servidor é o ponto: uma tela sem botão cujas rotas continuassem vivas seria
 * pior que a tela antiga — quem montasse a requisição gravaria na base espelho, e
 * a próxima carga do SGCI desfaria em silêncio.
 *
 * Por consequência, tudo o que sobrou aqui é LEITURA:
 *
 *  1. {@see index()} — a base inteira, para a tela filtrar, ordenar, paginar e
 *     exportar (o recorte visível);
 *  2. {@see foto()} — o retrato, servido pelo caminho da tela para a guarda de
 *     leitura conferir a permissão antes de entregar a imagem.
 *
 * **A tela DIZ de onde vem o dado.** Quem abre e não acha o botão de editar não
 * pode ficar procurando: o selo de protótipo (em `Ambulantes.tsx`) declara a
 * origem, que aqui é espelho de leitura e que a correção se faz no SGCI. É a lei do
 * projeto de que impedimento nunca fica em silêncio, aplicada a um impedimento
 * permanente e não a uma falha.
 *
 * ⚠️ A INTEGRAÇÃO não existe ainda (PEND-001). Enquanto ela não existir, o que a
 * tela mostra é o que já está gravado na tabela `ambulantes` — dado fictício, e a
 * tela diz isso. Nenhum contrato de API, cliente HTTP ou tabela nova foi inventado
 * aqui: quando o contrato chegar, o que muda é a ORIGEM das linhas, não esta tela.
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/ambulantes/…`), então a permissão se chama `ambulantes` — e o slug
 * ficou como estava mesmo com a tela mudando de natureza: slug é identidade de
 * acesso, e renomeá-lo tiraria a tela da matriz.
 */
class AmbulantesController extends Controller
{
    /**
     * Disco PRIVADO, e não o público.
     *
     * A foto é o retrato de um cidadão fiscalizado, exibida ao lado do CPF/CNPJ dele. No disco
     * público o arquivo é servido direto pelo servidor web, fora do encadeamento de middlewares
     * — quem tivesse a URL (histórico de estação compartilhada, log de proxy, cabeçalho de
     * referência, print encaminhado) abriria a imagem sem estar autenticado. Nome de arquivo
     * difícil de adivinhar reduz a chance de tropeçar nele, mas não é controle de acesso.
     *
     * Daqui a imagem só sai pela rota {@see foto()}, que passa pela guarda de leitura como
     * qualquer outra tela.
     *
     * É público de propósito: a provisão de persistência do deploy (volume/PVC) precisa cobrir
     * a pasta deste disco, e quem confere isso lê a decisão AQUI em vez de repeti-la.
     */
    public const DISCO_DAS_FOTOS = 'local';

    public function index(): Response
    {
        return Inertia::render('Retaguarda/Fiscalizacao/Ambulantes', [
            'ambulantes' => $this->listagem(),
            'atividades' => $this->atividades(),
            /*
             * O catálogo de situações vem do SERVIDOR, e continua vindo mesmo sem
             * formulário: é dele que saem os números do cabeçalho e a marca da
             * fila de conferência na linha. Escrito também na tela, um dia
             * discordaria — e a marca de "esperando conferência" sumiria da grade
             * sem nada quebrar.
             *
             * ⚠️ `SITUACOES_DE_MESA` NÃO desce mais: ela existia para dizer com
             * que situação um cadastro podia NASCER pela Retaguarda, e aqui não
             * nasce mais nenhum.
             */
            'situacoes' => Ambulante::SITUACOES,
            // As COLUNAS da aba "Localizar" e as do arquivo — ver
            // docs/padroes/listagem-clean.md. Documento, código e validade da
            // permissão descem para a ficha aberta e seguem no arquivo.
            'listagens' => ListagensDaRetaguarda::para('ambulantes'),
        ]);
    }

    /**
     * A foto de um cadastro — a única porta por onde a imagem sai.
     *
     * Mora sob `/retaguarda/ambulantes/…`, então a guarda de leitura confere a permissão da
     * tela antes de qualquer coisa: quem não abre o cadastro também não vê o retrato de quem está
     * nele. É por isso que o arquivo pode ficar no disco privado.
     *
     * Cadastro sem foto e arquivo que sumiu do disco respondem 404 — a tela já trata a ausência
     * mostrando as iniciais da pessoa, e uma resposta vazia com código 200 faria o navegador
     * desenhar uma imagem quebrada.
     */
    public function foto(int $ambulante): HttpResponse
    {
        $registro = Ambulante::query()->findOrFail($ambulante);

        $disco = Storage::disk(self::DISCO_DAS_FOTOS);

        abort_if($registro->foto === null || ! $disco->exists($registro->foto), 404);

        return $disco->response($registro->foto, headers: [
            // Dado pessoal não fica em cache compartilhado. `private` autoriza o
            // navegador de quem abriu, e só ele, a guardar por pouco tempo — sem
            // isso, cada linha da grade rebuscaria a imagem a cada rolagem.
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * A base inteira como a tela precisa dela.
     *
     * Vai inteira de propósito: a tela filtra, ordena e pagina no navegador, e é
     * desse recorte que sai a exportação. Quando a carga do SGCI chegar de verdade
     * e a base crescer, a busca passa para o servidor — por POST, com os filtros no
     * corpo (o WAF barra assinatura de SQL na URL). Ver PEND-012, que é
     * pré-requisito da carga.
     *
     * @return list<array<string, mixed>>
     */
    private function listagem(): array
    {
        $itens = Ambulante::query()
            ->with('atividade')
            ->orderBy('nome')
            ->get()
            ->map(fn (Ambulante $p): array => [
                'id' => (int) $p->getKey(),
                'codigo' => $p->codigo,
                'nome' => $p->nome,
                'apelido' => $p->apelido,
                // Os dois lados do documento vêm do servidor: o normalizado é o
                // que a busca casa, o formatado é o que a pessoa lê. Formatar na
                // tela daria dois donos à mesma regra.
                'documento' => $p->documento,
                'documento_formatado' => $p->documentoFormatado(),
                'rg' => $p->rg,
                'telefone' => $p->telefone,
                // Tem permissão da SEMOP? É o que separa o permissionário do
                // ambulante que a fiscalização encontra sem nada — e é por isso
                // que o número e a validade abaixo às vezes estão vazios.
                'permissionario' => (bool) $p->permissionario,
                'numero_permissao' => $p->numero_permissao,
                // ISO só por dentro; quem escreve dd/mm/aaaa é a tela.
                'validade_permissao' => $p->validade_permissao?->format('Y-m-d'),
                'atividade_id' => (int) $p->atividade_id,
                'atividade' => $p->atividade->nome,
                'situacao' => $p->situacao,
                // A imagem sai por rota autenticada, não por URL de disco público
                // (ver `DISCO_DAS_FOTOS`). O endereço leva o id, que é número —
                // nada de texto livre no caminho, que o WAF barraria.
                'foto_url' => $p->foto === null
                    ? null
                    : route('retaguarda.ambulantes.foto', $p->getKey(), absolute: false),
                'cadastrado_em' => $p->created_at?->format('Y-m-d'),
            ])
            ->all();

        return array_values($itens);
    }

    /**
     * As atividades, para a BUSCA — o nome de cada ramo é faceta da barra.
     *
     * Vão todas, com a situação de cada uma: a ficha de um ambulante continua
     * exibindo o nome do ramo inativado que ele aponta (senão o campo apareceria em
     * branco, como se o dado tivesse se perdido), e a barra de busca oferece como
     * exemplo apenas um ramo em uso.
     *
     * @return list<array<string, mixed>>
     */
    private function atividades(): array
    {
        $itens = AtividadeAmbulante::query()
            ->orderBy('nome')
            ->get()
            ->map(fn (AtividadeAmbulante $a): array => [
                'id' => (int) $a->getKey(),
                'nome' => $a->nome,
                'ativo' => (bool) $a->ativo,
            ])
            ->all();

        return array_values($itens);
    }
}

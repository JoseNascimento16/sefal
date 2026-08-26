<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\AtividadeAmbulante;
use App\Models\Permissionario;
use App\Rules\ArquivoSeguro;
use App\Rules\CpfOuCnpj;
use App\Rules\NomeDeCadastro;
use App\Support\Protocolo;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Cadastro de Permissionário — a identidade de quem é fiscalizado.
 *
 * É a tela-núcleo da fiscalização: sem um permissionário, não há a quem ligar
 * uma vistoria. Três decisões governam este controller, e todas as três vêm da
 * realidade da rua (spec §4.1):
 *
 *  1. **Documento é opcional em TODO caminho.** O alvo muitas vezes não tem CPF
 *     à mão, e exigi-lo faria o cadastro não acontecer. Quando existe, é
 *     validado, normalizado e único — a normalização mora no model, para valer
 *     também para a fila do PWA (Fase 4).
 *  2. **A foto é identidade, não enfeite.** É por ela e pelo apelido que o
 *     fiscal reconhece a pessoa. Por isso o arquivo passa pela allowlist de
 *     anexos, e trocar ou remover a foto apaga o arquivo anterior — sem isso, o
 *     disco vira depósito de órfãos.
 *  3. **A atividade apontada tem de estar em uso** no cadastro novo. No cadastro
 *     antigo, não: inativar tira o valor das escolhas novas, não invalida o
 *     passado (senão quem entra para corrigir um telefone seria obrigado a
 *     trocar o ramo).
 *
 * ⚠️ A **validação do cadastro em quarentena** (aprovar / mesclar duplicado /
 * recusar com motivo) é da Fase 5 e NÃO mora aqui: hoje a situação
 * `Cadastrado em campo` é apenas um valor que a tela mostra e o gestor pode
 * trocar à mão.
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/permissionarios/…`), então a permissão se chama `permissionarios`
 * e as rotas nascem protegidas sem ninguém declarar nada.
 */
class CadastroPermissionarioController extends Controller
{
    /** Onde as fotos moram. */
    private const PASTA_DAS_FOTOS = 'permissionarios';

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
        return Inertia::render('Retaguarda/Fiscalizacao/CadastroDePermissionario', [
            'permissionarios' => $this->listagem(),
            'atividades' => $this->atividades(),
            // Os dois catálogos de situação vêm do SERVIDOR: são os mesmos que a
            // validação exige. Escritos também na tela, um dia discordariam — e a
            // tela ofereceria uma opção que o servidor recusa.
            'situacoes' => Permissionario::SITUACOES,
            'situacoesDeInclusao' => Permissionario::SITUACOES_DE_MESA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validados($request, null);

        $permissionario = new Permissionario;
        $permissionario->fill($dados);

        // A rede do `$modelClass` importa: sem a linha do contador do dia (banco
        // restaurado, carga anterior), o número recomeçaria em 001 e colidiria
        // com o que já está gravado.
        $permissionario->codigo = Protocolo::proximo('PER', null, Permissionario::class, 'codigo');

        $guardada = $this->guardarFoto($request->file('foto'));

        /*
         * O arquivo é guardado ANTES da linha (é ele que preenche a coluna), então
         * a falha da gravação tem de levá-lo junto. Sem isto, um `save()` que
         * estoura — colisão no índice único numa corrida, queda de conexão — deixa
         * a imagem no disco sem nenhuma linha apontando para ela, e nada a recolhe
         * depois. É o espelho do cuidado que a alteração e a exclusão já tomam com
         * o arquivo ANTIGO; faltava o do arquivo novo.
         */
        try {
            $permissionario->foto = $guardada;
            $permissionario->save();
        } catch (\Throwable $erro) {
            $this->apagarFoto($guardada);

            throw $erro;
        }

        return redirect()
            ->route('retaguarda.permissionarios.index')
            ->with('flash.sucesso', "Permissionário cadastrado sob o código {$permissionario->codigo}.");
    }

    public function update(Request $request, int $permissionario): RedirectResponse
    {
        $registro = Permissionario::query()->findOrFail($permissionario);

        $registro->fill($this->validados($request, $registro));

        $descartar = $this->aplicarFoto($request, $registro);

        $registro->save();

        // O arquivo antigo só vai embora DEPOIS de a gravação dar certo. Apagado
        // antes, uma falha no `save()` deixaria o cadastro VIVO apontando para um
        // arquivo que não existe mais.
        $this->apagarFoto($descartar);

        return redirect()
            ->route('retaguarda.permissionarios.index')
            ->with('flash.sucesso', 'Alterações salvas.');
    }

    /**
     * Exclui o cadastro.
     *
     * Hoje nada aponta para o permissionário, então a exclusão é livre (com a
     * confirmação que a tela pede). Quando a cadeia de fiscalização existir, o
     * lugar de barrar é aqui — recusando com o motivo em tela, como a
     * parametrização faz com a atividade em uso. Apagar um cadastro fiscalizado
     * deixaria o histórico sem alvo.
     */
    public function destroy(int $permissionario): RedirectResponse
    {
        $registro = Permissionario::query()->findOrFail($permissionario);

        $foto = $registro->foto;

        $registro->delete();

        // Primeiro a linha, depois o arquivo: se a exclusão falhar, o cadastro
        // continua no sistema COM a foto dele. Na ordem inversa, a falha
        // deixaria um cadastro vivo sem o retrato que identifica a pessoa em rua.
        $this->apagarFoto($foto);

        return redirect()
            ->route('retaguarda.permissionarios.index')
            ->with('flash.sucesso', 'Permissionário excluído.');
    }

    /**
     * A foto de um cadastro — a única porta por onde a imagem sai.
     *
     * Mora sob `/retaguarda/permissionarios/…`, então a guarda de leitura confere a permissão da
     * tela antes de qualquer coisa: quem não abre o cadastro também não vê o retrato de quem está
     * nele. É por isso que o arquivo pode ficar no disco privado.
     *
     * Cadastro sem foto e arquivo que sumiu do disco respondem 404 — a tela já trata a ausência
     * mostrando as iniciais da pessoa, e uma resposta vazia com código 200 faria o navegador
     * desenhar uma imagem quebrada.
     */
    public function foto(int $permissionario): HttpResponse
    {
        $registro = Permissionario::query()->findOrFail($permissionario);

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
     * desse recorte que sai a exportação. Quando a base crescer a ponto de isso
     * pesar, a busca passa para o servidor — por POST, com os filtros no corpo
     * (o WAF barra assinatura de SQL na URL).
     *
     * @return list<array<string, mixed>>
     */
    private function listagem(): array
    {
        $itens = Permissionario::query()
            ->with('atividade')
            ->orderBy('nome')
            ->get()
            ->map(fn (Permissionario $p): array => [
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
                'numero_permissao' => $p->numero_permissao,
                // ISO só por dentro (é o valor de `<input type="date">`); quem
                // escreve dd/mm/aaaa é a tela.
                'validade_permissao' => $p->validade_permissao?->format('Y-m-d'),
                'atividade_id' => (int) $p->atividade_id,
                'atividade' => $p->atividade->nome,
                'situacao' => $p->situacao,
                // A imagem sai por rota autenticada, não por URL de disco público
                // (ver `DISCO_DAS_FOTOS`). O endereço leva o id, que é número —
                // nada de texto livre no caminho, que o WAF barraria.
                'foto_url' => $p->foto === null
                    ? null
                    : route('retaguarda.permissionarios.foto', $p->getKey(), absolute: false),
                'cadastrado_em' => $p->created_at?->format('Y-m-d'),
            ])
            ->all();

        return array_values($itens);
    }

    /**
     * As atividades que o formulário oferece.
     *
     * Vão TODAS, com a situação de cada uma: a tela oferece as em uso para
     * escolha nova e continua exibindo o nome da inativada no cadastro que já a
     * apontava — senão o campo apareceria em branco, como se o dado tivesse se
     * perdido.
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

    /**
     * Os dados válidos para gravar.
     *
     * @return array<string, mixed>
     */
    private function validados(Request $request, ?Permissionario $registro): array
    {
        // Na INCLUSÃO a quarentena não é oferecida (ver `SITUACOES_DE_MESA`); na
        // alteração, sim. A conferência é do servidor, e não só do `<select>`:
        // esconder a opção na tela não impede ninguém de mandar o valor.
        $ehInclusao = $registro === null;
        $situacoesAceitas = $ehInclusao
            ? Permissionario::SITUACOES_DE_MESA
            : Permissionario::SITUACOES;

        $dados = $request->validate([
            // Campo cadastral aceita nome de gente, não markup: o valor sai daqui
            // para relatório, planilha e documento — ver `NomeDeCadastro`.
            'nome' => ['required', 'string', 'max:150', new NomeDeCadastro],
            'apelido' => ['nullable', 'string', 'max:100', new NomeDeCadastro],
            // Opcional DE VERDADE: `nullable` primeiro, e a Rule só opina quando
            // há valor. É a identidade flexível do §4.1 da spec.
            'documento' => ['nullable', 'string', 'max:20', new CpfOuCnpj, $this->documentoInedito($registro)],
            'rg' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'numero_permissao' => ['nullable', 'string', 'max:30'],
            'validade_permissao' => ['nullable', 'date'],
            'atividade_id' => ['required', 'integer', $this->atividadeUtilizavel($registro)],
            'situacao' => ['required', Rule::in($situacoesAceitas)],
            // Foto de identificação: só imagem, e passando pela allowlist de
            // anexos (que barra o executável renomeado e o nome que o WAF
            // reprovaria na URL de download).
            'foto' => ['nullable', 'image', 'max:5120', new ArquivoSeguro(['jpg', 'jpeg', 'png'])],
            'remover_foto' => ['nullable', 'boolean'],
        ], [
            'nome.required' => 'Informe o nome do permissionário.',
            'atividade_id.required' => 'Escolha a atividade autorizada.',
            'situacao.required' => 'Escolha a situação do cadastro.',
            // A recusa diz o PORQUÊ: "situação inválida" faria a pessoa achar
            // que digitou errado um valor que a lista realmente tem.
            'situacao.in' => $ehInclusao
                ? '“'.Permissionario::SITUACAO_CAMPO.'” é a situação de quem foi cadastrado em rua, '
                    .'pelo aplicativo do fiscal. No cadastro feito aqui, escolha Regular ou Irregular.'
                : 'Situação inválida. Escolha uma das opções da lista.',
            'validade_permissao.date' => 'A validade da permissão precisa ser uma data.',
            'foto.image' => 'A foto precisa ser uma imagem (JPG ou PNG).',
            'foto.max' => 'A foto passa de 5 MB. Envie uma imagem menor.',
        ]);

        /*
         * Espaço nas pontas e campo em branco já chegam tratados: o `TrimStrings`
         * e o `ConvertEmptyStringsToNull` do framework rodam antes da validação —
         * é por isso que um nome só de espaços cai no `required`. Repetir o
         * tratamento aqui daria dois donos à mesma regra.
         *
         * A foto não é campo de formulário comum — quem a grava é o fluxo de
         * arquivo, e deixá-la aqui faria o `fill()` tentar gravar o upload.
         */
        unset($dados['foto'], $dados['remover_foto']);

        return $dados;
    }

    /**
     * Recusa documento já usado por OUTRO cadastro, comparando pelo valor
     * canônico.
     *
     * A comparação é feita sobre o normalizado dos dois lados porque é assim que
     * a coluna guarda: sem isso, `123.456.789-09` e `12345678909` passariam como
     * pessoas diferentes e o índice único do banco estouraria depois, com uma
     * tela de erro no lugar de uma recusa explicada.
     */
    private function documentoInedito(?Permissionario $registro): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) use ($registro): void {
            $canonico = Permissionario::documentoCanonico(is_string($valor) ? $valor : null);

            if ($canonico === null) {
                return;
            }

            $consulta = Permissionario::query()->where('documento', $canonico);

            if ($registro !== null) {
                $consulta->whereKeyNot($registro->getKey());
            }

            if ($consulta->exists()) {
                $falhar('Já existe um permissionário com esse documento.');
            }
        };
    }

    /**
     * A atividade existe e pode ser escolhida?
     *
     * Inativa é recusada no cadastro NOVO e aceita no cadastro que já a apontava
     * — inativar tira o valor de circulação, não reescreve o passado.
     */
    private function atividadeUtilizavel(?Permissionario $registro): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) use ($registro): void {
            // O valor chega cru (a regra `integer` roda em paralelo, não antes):
            // o que não é número não é chave de nada, e cai na recusa abaixo.
            $atividade = AtividadeAmbulante::query()->find(is_numeric($valor) ? (int) $valor : 0);

            if ($atividade === null) {
                $falhar('Essa atividade não existe mais. Escolha uma da lista.');

                return;
            }

            $jaEraEssa = $registro !== null && (int) $registro->atividade_id === (int) $valor;

            if (! $atividade->ativo && ! $jaEraEssa) {
                $falhar('Essa atividade está fora de uso e não pode ser escolhida. Reative-a na Parametrização ou escolha outra.');
            }
        };
    }

    /**
     * Aplica ao registro o que o formulário decidiu sobre a foto, e devolve o
     * arquivo que **deixou de ser usado** — para quem chamou apagá-lo depois de
     * gravar.
     *
     * São TRÊS casos, e confundi-los é o erro clássico: arquivo novo (troca),
     * pedido explícito de remoção, e **nada** — que é o caso normal de quem
     * entrou só para corrigir o telefone. Tratar "campo ausente" como remoção
     * apagaria a foto de quem nunca pediu isso, e a foto é a identidade de campo.
     *
     * Este método NÃO apaga nada de propósito. Entre os dois estragos possíveis
     * numa falha de gravação — arquivo sobrando no disco ou cadastro apontando
     * para arquivo inexistente —, o primeiro é lixo e o segundo é perda de dado.
     * Por isso a exclusão fica com quem sabe se o `save()` deu certo.
     *
     * @return string|null Caminho a descartar após a gravação bem-sucedida
     */
    private function aplicarFoto(Request $request, Permissionario $registro): ?string
    {
        $enviada = $request->file('foto');

        if ($enviada instanceof UploadedFile) {
            $anterior = $registro->foto;
            $registro->foto = $this->guardarFoto($enviada);

            return $anterior;
        }

        if ($request->boolean('remover_foto')) {
            $anterior = $registro->foto;
            $registro->foto = null;

            return $anterior;
        }

        return null;
    }

    /** Guarda a imagem no disco privado e devolve o caminho, ou null. */
    private function guardarFoto(?UploadedFile $arquivo): ?string
    {
        if (! $arquivo instanceof UploadedFile) {
            return null;
        }

        // Nome gerado pelo framework (aleatório), não o do celular: nome de
        // arquivo de campo carrega acento, espaço e o que mais o aparelho
        // inventar.
        return $arquivo->store(self::PASTA_DAS_FOTOS, self::DISCO_DAS_FOTOS) ?: null;
    }

    /** Apaga o arquivo, se houver — a linha do banco é outra história. */
    private function apagarFoto(?string $caminho): void
    {
        if ($caminho === null || $caminho === '') {
            return;
        }

        Storage::disk(self::DISCO_DAS_FOTOS)->delete($caminho);
    }
}

<?php

namespace App\Support\Parametrizacao;

use App\Http\Controllers\Retaguarda\Parametrizacao\ControllerDeLookup;
use App\Models\ListaDeEscolha;

/**
 * O que distingue UMA tela de parametrização das outras cinco.
 *
 * As seis telas são a mesma tela: listar, incluir, alterar, inativar, excluir.
 * O que muda é o nome das coisas, o texto que explica para que servem e o campo
 * que só aquela lista tem. Tudo isso vive aqui — e o comportamento vive uma vez
 * só, no {@see ControllerDeLookup}
 * e no componente de tela que ele alimenta.
 *
 * Seis cópias do mesmo CRUD seria a forma mais rápida de escrever e a mais cara
 * de manter: a correção entraria em cinco e esqueceria a sexta.
 */
final readonly class DefinicaoLookup
{
    /**
     * @param  class-string<ListaDeEscolha>  $modelo  o model da lista
     * @param  string  $tela  trecho do endereço e sufixo do nome da rota (`tipos-de-infracao`)
     * @param  string  $componente  a página Inertia que desenha a tela
     * @param  string  $titulo  o nome no plural, como aparece no menu
     * @param  string  $singular  o nome de UM registro, para as mensagens
     * @param  'm'|'f'  $genero  concordância das mensagens ("cadastrado" / "cadastrada")
     * @param  string  $descricao  o parágrafo que explica para que a lista serve
     * @param  string  $exemplo  exemplo dentro do campo de nome vazio
     * @param  list<CampoLookup>  $campos  os campos próprios desta lista
     * @param  list<string>  $exemplosDeBusca  frases clicáveis abaixo da busca
     */
    public function __construct(
        public string $modelo,
        public string $tela,
        public string $componente,
        public string $titulo,
        public string $singular,
        public string $genero,
        public string $descricao,
        public string $exemplo,
        public array $campos = [],
        public array $exemplosDeBusca = ['ativos', 'inativos'],
    ) {}

    /** O nome da rota de uma ação desta tela. */
    public function rota(string $acao): string
    {
        return "retaguarda.parametrizacao.{$this->tela}.{$acao}";
    }

    /** Onde a pessoa está — o mesmo caminho que o menu mostra. */
    public function trilha(): string
    {
        return "Parametrização › {$this->titulo}";
    }

    /**
     * Uma mensagem com a concordância certa: "Tipo de infração cadastrado",
     * "Atividade do ambulante cadastrada".
     */
    public function mensagem(string $verboNoMasculino): string
    {
        $participio = $this->genero === 'f'
            ? mb_substr($verboNoMasculino, 0, -1).'a'
            : $verboNoMasculino;

        return "{$this->singular} {$participio}.";
    }

    /**
     * A definição como a tela precisa dela.
     *
     * @return array<string, mixed>
     */
    public function paraTela(): array
    {
        return [
            'titulo' => $this->titulo,
            'singular' => $this->singular,
            'descricao' => $this->descricao,
            'exemplo' => $this->exemplo,
            'trilha' => $this->trilha(),
            'campos' => array_map(static fn (CampoLookup $c): array => $c->paraTela(), $this->campos),
            'exemplosDeBusca' => $this->exemplosDeBusca,
        ];
    }
}

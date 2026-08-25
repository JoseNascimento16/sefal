<?php

namespace App\Support\Parametrizacao;

/**
 * Um campo PRÓPRIO de uma lista de escolha — o que ela tem além do nome e da
 * situação (a descrição do tipo de infração, a sigla da unidade de medida).
 *
 * A declaração é uma só e vive no servidor: é ela que monta a validação E o que
 * a tela desenha. Se o formulário declarasse os seus campos por conta própria,
 * um dia a tela pediria algo que o servidor não valida — ou validaria algo que a
 * tela não tem como preencher.
 */
final readonly class CampoLookup
{
    /**
     * @param  string  $chave  nome da coluna e da propriedade que viaja para a tela
     * @param  string  $rotulo  o que se lê acima do campo
     * @param  bool  $longo  campo de texto em várias linhas
     * @param  string|null  $exemplo  texto de exemplo dentro do campo vazio
     * @param  string|null  $ajuda  a linha de explicação abaixo do campo
     */
    public function __construct(
        public string $chave,
        public string $rotulo,
        public bool $obrigatorio = true,
        public int $maximo = 120,
        public bool $longo = false,
        public ?string $exemplo = null,
        public ?string $ajuda = null,
    ) {}

    /**
     * As regras de validação do campo.
     *
     * @return list<string>
     */
    public function regras(): array
    {
        return [
            $this->obrigatorio ? 'required' : 'nullable',
            'string',
            'max:'.$this->maximo,
        ];
    }

    /**
     * O campo como a tela precisa dele.
     *
     * @return array<string, mixed>
     */
    public function paraTela(): array
    {
        return [
            'chave' => $this->chave,
            'rotulo' => $this->rotulo,
            'obrigatorio' => $this->obrigatorio,
            'maximo' => $this->maximo,
            'longo' => $this->longo,
            'exemplo' => $this->exemplo,
            'ajuda' => $this->ajuda,
        ];
    }
}

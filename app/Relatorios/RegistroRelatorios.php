<?php

namespace App\Relatorios;

use App\Relatorios\Contracts\Relatorio;

/**
 * O catálogo de relatórios do sistema — a lista de quem existe.
 *
 * Registro explícito, e não descoberta automática do diretório: relatório é
 * documento oficial que sai do sistema, e "apareceu na tela porque o arquivo
 * existe" é como se publica na Retaguarda um rascunho que ninguém revisou. Para
 * entrar no catálogo, alguém acrescenta a linha aqui.
 */
class RegistroRelatorios
{
    /**
     * @var list<class-string<Relatorio>>
     */
    private const RELATORIOS = [
        RelatorioUsuariosDoSistema::class,
        RelatorioPermissionarios::class,
    ];

    /**
     * @return list<Relatorio>
     */
    public function todos(): array
    {
        return array_map(static fn (string $classe): Relatorio => new $classe, self::RELATORIOS);
    }

    public function encontrar(string $chave): ?Relatorio
    {
        foreach ($this->todos() as $relatorio) {
            if ($relatorio->chave() === $chave) {
                return $relatorio;
            }
        }

        return null;
    }

    /**
     * O catálogo como a tela precisa dele: identidade, filtros e modos de cada
     * relatório. A tela não conhece relatório nenhum por dentro — ela desenha o
     * que vem daqui.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogo(): array
    {
        return array_map(static fn (Relatorio $r): array => [
            'chave' => $r->chave(),
            'titulo' => $r->titulo(),
            'grupo' => $r->grupo(),
            'descricao' => $r->descricao(),
            'modos' => $r->modos(),
            'formatos' => ['pdf', 'xlsx', 'docx'],
            'filtros' => array_map(static fn ($f): array => $f->toArray(), $r->filtros()),
        ], $this->todos());
    }
}

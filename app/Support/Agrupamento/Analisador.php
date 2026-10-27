<?php

namespace App\Support\Agrupamento;

use App\Models\Demanda;
use Illuminate\Support\Collection;

/**
 * Quem OLHA duas denuncias e diz se elas contam o mesmo fato.
 *
 * E interface, e nao classe, porque a forma de olhar vai mudar: hoje e regra
 * determinista (mesmo bairro, enderecos proximos, assunto parecido); amanha um
 * modelo de linguagem le os dois relatos e responde melhor do que qualquer regra
 * — sobretudo nos casos em que o cidadao descreve o mesmo ponto com palavras
 * completamente diferentes.
 *
 * O que NAO muda com o analisador e o resto do desenho: a proposta continua
 * sendo proposta, o motivo continua obrigatorio, e quem agrupa e gente. Trocar a
 * forma de olhar nao pode virar, por descuido, uma forma de DECIDIR.
 */
interface Analisador
{
    /**
     * As propostas que esta denuncia produz contra as vizinhas dela.
     *
     * Cada proposta ja vem com os dois lados NOMEADOS: quem analisa e quem sabe
     * qual das duas espera ha mais tempo, e essa escolha nao pode sobrar para o
     * chamador.
     *
     * @param  Collection<int, Demanda>  $vizinhas  candidatas ja recortadas por janela e situacao
     * @return list<Proposta>
     */
    public function analisar(Demanda $candidata, Collection $vizinhas): array;

    /** A chave que vai para `sugestoes_agrupamento.origem` (`regra`, `ia`). */
    public function origem(): string;
}

<?php

namespace App\Support;

use App\Models\DemandaTramite;
use App\Models\User;

/**
 * O papel de uma pessoa NO FLUXO — a fonte única das três perguntas que toda tela
 * da cadeia faz antes de servir ou de gravar:
 *
 *   1. **por quais equipes ela responde?** ({@see equipes})
 *   2. **a listagem dela é recortada por elas?** ({@see recorta})
 *   3. **ela DECIDE, ou apenas acompanha?** ({@see decide})
 *
 * Substitui `PapelNaArea`, e a troca de nome não é cosmética. A pergunta antiga
 * era "por quais ÁREAS esta pessoa responde como Chefe de Setor?" — e a resposta
 * do cliente, em 22/09/2026, é que o Chefe de Setor é UM SÓ e responde por tudo.
 * O recorte que existe de verdade é o do **líder de equipe**, que só enxerga a
 * própria equipe. A área continua existindo como território (é dela que sai a
 * equipe sugerida para um bairro), mas deixou de ser fronteira de quem vê o quê.
 *
 * ── Os três papéis, e o que cada um exerce ──────────────────────────────────
 *
 *  - **Chefe de Setor** — recebe tudo que chega (pré-tria, encaminha a um líder,
 *    devolve), lê o que volta e responde ao e-Salvador. Sem recorte.
 *  - **Líder de equipe** — recebe o que o chefe encaminhou à SUA equipe,
 *    direciona aos fiscais, recebe o retorno da rua e dá ciência ou pede nova
 *    vistoria. Recortado pela equipe.
 *  - **Administrador** — exerce os dois, sem recorte: cobre a ausência de
 *    qualquer um e demonstra o fluxo inteiro.
 *
 * Quem tem os dois setores (chefe E líder) NÃO é recortado: o papel que amplia
 * ganha, a mesma regra da união de setores na matriz de permissões.
 *
 * ⚠️ Isto NÃO é permissão de tela. A permissão (Modo Gerente) diz quem ENTRA;
 * isto diz sobre O QUE cada um decide, dentro da tela em que já entrou. As duas
 * conferências existem, e nenhuma substitui a outra.
 */
class Papel
{
    public const CHEFE = 'chefe-de-setor';

    public const LIDER = 'lider-de-equipe';

    public const FISCAL = 'fiscal';

    /**
     * Os CÓDIGOS das equipes que esta pessoa lidera — vazio para quem não lidera
     * nenhuma.
     *
     * Lista, e não uma equipe só: na vida real uma pessoa cobre a equipe do
     * colega em férias, ou a equipe recém-criada ainda sem líder próprio.
     *
     * @return list<string>
     */
    public static function equipes(?User $usuario): array
    {
        return $usuario === null ? [] : Estrutura::equipesDoLider($usuario->login);
    }

    /**
     * As ÁREAS das equipes que esta pessoa lidera — para o que é territorial.
     *
     * A operação nasce numa área, não numa equipe; por isso o recorte do líder
     * sobre operações é pelas áreas das equipes dele. Derivado da equipe, e
     * nunca de um vínculo próprio com a área: esse vínculo deixou de existir
     * quando o Chefe de Setor virou um só.
     *
     * @return list<string>
     */
    public static function areas(?User $usuario): array
    {
        $codigos = self::equipes($usuario);

        if ($codigos === []) {
            return [];
        }

        $areas = [];

        foreach (Estrutura::equipes() as $equipe) {
            if (in_array($equipe['equipe'], $codigos, true) && ! in_array($equipe['area'], $areas, true)) {
                $areas[] = $equipe['area'];
            }
        }

        return $areas;
    }

    /**
     * A listagem desta pessoa vem recortada pelas equipes dela?
     *
     * É o líder de equipe, e só ele. O administrador é o dono do sistema e o
     * Chefe de Setor responde por tudo — não se acompanha o que não se vê.
     */
    public static function recorta(?User $usuario): bool
    {
        if ($usuario === null || $usuario->ehAdmin()) {
            return false;
        }

        $setores = self::setores($usuario);

        return in_array(self::LIDER, $setores, true)
            && ! in_array(self::CHEFE, $setores, true);
    }

    /**
     * Esta pessoa DECIDE sobre o que está na fila, ou apenas acompanha?
     *
     * Decidem o Chefe de Setor, o líder de equipe (cada um na sua etapa) e o
     * administrador. O fiscal acompanha: ele vê o acervo do que fez, e a decisão
     * sobre o que fazer com isso é de quem está na mesa.
     */
    public static function decide(?User $usuario): bool
    {
        if ($usuario === null) {
            return false;
        }

        return $usuario->ehAdmin() || self::ehChefe($usuario) || self::ehLider($usuario);
    }

    /** Recebe tudo, encaminha aos líderes, fecha e responde ao canal. */
    public static function ehChefe(?User $usuario): bool
    {
        return $usuario !== null
            && ($usuario->ehAdmin() || in_array(self::CHEFE, self::setores($usuario), true));
    }

    /** Direciona aos fiscais e recebe o retorno da própria equipe. */
    public static function ehLider(?User $usuario): bool
    {
        return $usuario !== null
            && ($usuario->ehAdmin() || in_array(self::LIDER, self::setores($usuario), true));
    }

    /**
     * Com que papel esta pessoa assina um passo do trâmite.
     *
     * O trâmite guarda o PAPEL, e não só quem: "o chefe encaminhou" e "o líder
     * direcionou" são fatos diferentes mesmo quando a mesma pessoa acumula os
     * dois. Quando acumula, vale o papel da ETAPA em que está agindo — e é por
     * isso que quem chama informa a etapa, em vez de deixar esta classe supor.
     *
     * @param  'chefe'|'lider'  $etapa
     */
    public static function papelDoTramite(?User $usuario, string $etapa): string
    {
        return $etapa === 'lider'
            ? DemandaTramite::PAPEL_LIDER
            : DemandaTramite::PAPEL_CHEFE_DE_SETOR;
    }

    /** @return list<string> */
    private static function setores(User $usuario): array
    {
        return $usuario->setores->pluck('slug')->all();
    }
}

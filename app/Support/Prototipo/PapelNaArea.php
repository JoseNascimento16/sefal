<?php

namespace App\Support\Prototipo;

use App\Models\User;

/**
 * O papel de uma pessoa DIANTE DE UMA ÁREA de fiscalização — a fonte única das
 * três perguntas que toda tela da cadeia faz antes de servir ou de gravar:
 *
 *   1. **por quais áreas ela responde?** ({@see areas})
 *   2. **a listagem dela é recortada por elas?** ({@see recorta})
 *   3. **ela DECIDE, ou apenas acompanha?** ({@see decide})
 *
 * ── Por que isto existe como classe, e não como método privado de controller ──
 *
 * As mesmas três respostas governam Denúncias, Fiscalizações e Cadastro de
 * Operação — e cada uma delas as usa DUAS vezes: para recortar o que viaja até o
 * navegador e para recusar a gravação sobre registro alheio. Copiadas por tela,
 * seriam a mesma regra com quatro donos: no dia em que "Chefe de Setor que também
 * é Coordenador" mudasse de resposta, uma tela passaria a recortar e a outra não —
 * e a que não recortasse continuaria abrindo, sem nada acusar.
 *
 * (Esta classe nasceu justamente disso: `DenunciasController` e a fila do retorno
 * de campo tinham cópias literais de `areasDoChefe` e `temRecorteDeArea`.)
 *
 * ⚠️ PROTÓTIPO no vínculo, não na regra: hoje a ligação pessoa ↔ área mora em
 * `config/prototipo_estrutura.php` e casa pela MATRÍCULA
 * ({@see EstruturaFicticia::areasDoChefe}). Em produção isso é tabela, entre
 * USUÁRIO e área. Quem chama aqui já trata LISTA de áreas, então a modelagem
 * definitiva não obriga a mexer em quem lê.
 *
 * ⚠️ E isto NÃO é permissão de tela. A permissão (Modo Gerente) diz quem ENTRA;
 * isto diz sobre O QUE cada um decide, dentro da tela em que já entrou. As duas
 * conferências existem, e nenhuma substitui a outra.
 */
class PapelNaArea
{
    /** O setor de quem responde por uma área. */
    public const CHEFE = 'chefe-de-setor';

    /** O setor de quem tria a entrada do trabalho — acompanha o universo, não decide. */
    public const COORDENADOR = 'coordenador';

    /**
     * As áreas que esta pessoa responde como Chefe de Setor — vazio para quem não
     * responde por nenhuma.
     *
     * Lista, e não uma área só, porque na vida real uma pessoa responde por mais
     * de uma (férias, acumulação, área recém-criada).
     *
     * @return list<string>
     */
    public static function areas(?User $usuario): array
    {
        return $usuario === null ? [] : EstruturaFicticia::areasDoChefe($usuario->login);
    }

    /**
     * A listagem desta pessoa vem recortada pelas áreas dela?
     *
     * É o Chefe de Setor, e só ele. O administrador é o dono do sistema, e o
     * Coordenador precisa do universo — não se acompanha o que não se vê. Um
     * Chefe de Setor que também seja Coordenador NÃO é recortado: o papel que
     * amplia ganha, a mesma regra da união de setores na matriz de permissões.
     */
    public static function recorta(?User $usuario): bool
    {
        if ($usuario === null || $usuario->ehAdmin()) {
            return false;
        }

        $setores = self::setores($usuario);

        return in_array(self::CHEFE, $setores, true)
            && ! in_array(self::COORDENADOR, $setores, true);
    }

    /**
     * Esta pessoa DECIDE sobre o que é da área, ou apenas acompanha?
     *
     * Decide o Chefe de Setor (é o trabalho dele) e o administrador, que cobre a
     * ausência dele e demonstra o fluxo inteiro. O Coordenador acompanha: ele
     * precisa saber o que aconteceu com o que encaminhou, e a decisão sobre o
     * ponto continua sendo de quem responde pela área.
     */
    public static function decide(?User $usuario): bool
    {
        if ($usuario === null) {
            return false;
        }

        return $usuario->ehAdmin() || in_array(self::CHEFE, self::setores($usuario), true);
    }

    /** @return list<string> */
    private static function setores(User $usuario): array
    {
        return $usuario->setores->pluck('slug')->all();
    }
}

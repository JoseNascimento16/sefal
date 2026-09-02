<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Support\Prototipo\MapasFicticios;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mapa ao Vivo — PROTÓTIPO da cidade agora, para o GESTOR.
 *
 * Não é a tela do fiscal. O aplicativo mostra a calçada em que ele está; esta
 * tela mostra a CIDADE, em escala de operação: onde a demanda se acumula, qual
 * equipe cobre aquilo, quem está na rua neste momento e o que já venceu o prazo
 * de retorno. A pergunta que ela responde é "para onde eu mando gente hoje?".
 *
 * ── Desenho: o padrão IMERSIVO (RN-07) ──────────────────────────────────────
 *
 * O mapa é o FUNDO, sangrando de borda a borda, e a leitura flutua sobre a
 * cidade em painéis de vidro. Quem abre um mapa está olhando a cidade, não uma
 * tela com um mapa dentro dela. O menu permanece — o imersivo é sobre o
 * conteúdo, não sobre a casca (ver `docs/regras-de-negocio/design-retaguarda.md`).
 *
 * ── Os números NÃO são calculados aqui ──────────────────────────────────────
 *
 * "23 registros hoje", "7 retornos vencidos", o foco do dia e os últimos
 * registros são agregações dos MESMOS pontos que o mapa desenha, feitas na tela.
 * É a RN-06: número de cabeçalho que sai de uma segunda consulta um dia discorda
 * do que está desenhado ao lado — e é sempre o número que se acredita.
 *
 * Só GET: é tela de leitura. Quem grava fiscalização é o aplicativo, em rua.
 *
 * ⚠️ PROTÓTIPO: nada vem do banco. Os pontos são inventados por
 * {@see MapasFicticios} (com coordenadas reais de Salvador e a estrutura de
 * equipes real, tirada de `EstruturaFicticia`) e não há tempo real — a tela diz o
 * instante que está mostrando. A guarda de acesso deduz a tela do primeiro trecho
 * do caminho (`/retaguarda/mapa`).
 */
class MapaAoVivoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Retaguarda/Fiscalizacao/MapaAoVivo', MapasFicticios::aoVivo());
    }
}

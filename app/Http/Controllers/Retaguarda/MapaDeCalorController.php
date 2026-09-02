<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Support\Prototipo\MapasFicticios;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mapa de Calor — PROTÓTIPO do registro virando decisão de operação.
 *
 * Cada abordagem registrada em rua é uma linha; mil linhas são um DESENHO. Esta
 * é a tela que devolve o registro ao trabalho: ela diz onde a cidade está pedindo
 * presença, quanto isso mudou em relação ao período anterior e de quem é a área.
 * É a razão de o registro em campo ser tão barato de fazer.
 *
 * ── A leitura vem ANTES do mapa ─────────────────────────────────────────────
 *
 * A tela abre com UMA FRASE ("o Centro Histórico concentra 42% das ocorrências
 * dos últimos 30 dias — 3× a média da cidade"), e só depois vêm a mancha e o
 * ranking. Quem tem trinta segundos entre duas reuniões não interpreta gradiente:
 * lê a frase. O mapa serve para conferir e para achar o recorte; a frase é o que
 * sai da tela na cabeça de quem olhou.
 *
 * ── Por que 180 dias de dados para janelas de 90 ────────────────────────────
 *
 * O ranking mostra a VARIAÇÃO contra o período anterior. Comparar os últimos 90
 * dias com os 90 anteriores exige ter 180 — sem isso a coluna de variação seria
 * invenção. A tela recorta a janela (7/30/90) sobre o mesmo conjunto, no
 * navegador: trocar de período não é uma nova consulta, é o mesmo dado com outro
 * corte, e a comparação fica garantidamente coerente com a mancha desenhada.
 *
 * Só GET: é tela de leitura. A recomendação de operação LEVA ao Cadastro de
 * Operação — quem cria a operação é aquela tela, não esta.
 *
 * ⚠️ PROTÓTIPO: nada vem do banco (ver {@see MapasFicticios}). A guarda de acesso
 * deduz a tela do primeiro trecho do caminho (`/retaguarda/mapa-de-calor`), e a
 * concessão inicial exclui o fiscal: concentração histórica serve para PLANEJAR,
 * e planejar é ato de gestão.
 */
class MapaDeCalorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Retaguarda/Fiscalizacao/MapaDeCalor', MapasFicticios::calor());
    }
}

// Roteamento das setas do fluxograma da Saúde dos Fluxos (pedido do dono, 26/10/2026: "não deixe
// linhas por dentro de outra" e "evite que linhas CORTEM o quadrado").
//
// Cada seta anda SÓ pelos corredores entre as caixas, em ângulo reto: o caminho é procurado numa
// grade de corredores (Dijkstra) que não tem ponto nem trecho dentro de caixa nenhuma — a linha
// nunca corta um quadradinho. Depois, setas que dividem o mesmo corredor ganham FAIXAS paralelas
// separadas: duas setas nunca se sobrepõem a ponto de parecerem uma só (o caso do Balcão → Docs
// Validados, que parecia passar pela Abertura e pela Triagem).

export interface Caixa {
    id: string;
    x: number;
    y: number;
    w: number;
    h: number;
}

export interface Ponto {
    x: number;
    y: number;
}

export interface Rota {
    pontos: Ponto[];
    /** Onde escrever o rótulo da seta: no meio do trecho mais longo. */
    rotulo: { x: number; y: number; ancora: 'start' | 'middle' };
}

interface Geometria {
    caixas: Caixa[];
    /** Linhas de corredor (verticais e horizontais) — o meio dos vãos entre as caixas. */
    corredoresX: number[];
    corredoresY: number[];
    /** Vão entre caixas — limita quantas faixas cabem num corredor. */
    vaoX: number;
    vaoY: number;
}

type Lado = 'cima' | 'baixo' | 'esquerda' | 'direita';

const DOBRA = 40; // custo de cada cotovelo: menos voltas, leitura mais fácil
const CRUZAMENTO = 30; // custo de cruzar uma seta já traçada
const CORREDOR_OCUPADO = 0.6; // por unidade de comprimento, a cada seta que já usa aquele trecho
const LADO_OCUPADO = 25; // por seta que já encosta naquele lado da caixa, no mesmo sentido
const LADO_CONTRARIO = 400; // por seta no sentido contrário naquele lado: só se não houver outro jeito
const RODADAS_DE_AJUSTE = 2;
const ESPACO_FAIXA = 7;
const FOLGA = 3;

/** Traça todas as setas, na ordem dada; cada uma desvia do que as anteriores já ocupam. */
export function rotear(geo: Geometria, arestas: { de: string; para: string }[]): (Rota | null)[] {
    const porId = new Map(geo.caixas.map((c) => [c.id, c]));
    const xs = unicos([...geo.corredoresX, ...geo.caixas.flatMap((c) => [c.x + c.w / 2])]);
    const ys = unicos([...geo.corredoresY, ...geo.caixas.flatMap((c) => [c.y + c.h / 2])]);

    // Pontos da grade fora de qualquer caixa (com folga).
    const dentro = (x: number, y: number) => geo.caixas.some((c) => x > c.x - FOLGA && x < c.x + c.w + FOLGA && y > c.y - FOLGA && y < c.y + c.h + FOLGA);
    const indice = new Map<string, number>();
    const pontos: Ponto[] = [];
    for (const x of xs) {
        for (const y of ys) {
            if (!dentro(x, y)) {
                indice.set(chave(x, y), pontos.length);
                pontos.push({ x, y });
            }
        }
    }
    // `exceto`: a própria caixa da porta — o trecho que sai dela encosta nela, e isso não é cortar.
    const cortaCaixa = (a: Ponto, b: Ponto, exceto?: Caixa) =>
        geo.caixas.some((c) => {
            if (c === exceto) {
                return false;
            }
            const [x1, x2] = [Math.min(a.x, b.x), Math.max(a.x, b.x)];
            const [y1, y2] = [Math.min(a.y, b.y), Math.max(a.y, b.y)];
            return x2 > c.x - FOLGA && x1 < c.x + c.w + FOLGA && y2 > c.y - FOLGA && y1 < c.y + c.h + FOLGA;
        });

    // Vizinhos: o próximo ponto da grade em cada direção, se o trecho não cortar caixa.
    const vizinhos: { j: number; eixo: 0 | 1; comp: number }[][] = pontos.map(() => []);
    pontos.forEach((p, i) => {
        const direcoes: [number, number, 0 | 1][] = [[1, 0, 0], [-1, 0, 0], [0, 1, 1], [0, -1, 1]];
        for (const [dx, dy, eixo] of direcoes) {
            const lista = eixo === 0 ? xs : ys;
            const atual = eixo === 0 ? p.x : p.y;
            const pos = lista.indexOf(atual) + (eixo === 0 ? dx : dy);
            if (pos < 0 || pos >= lista.length) {
                continue;
            }
            const q = eixo === 0 ? { x: lista[pos], y: p.y } : { x: p.x, y: lista[pos] };
            const j = indice.get(chave(q.x, q.y));
            if (j !== undefined && !cortaCaixa(p, q)) {
                vizinhos[i].push({ j, eixo, comp: Math.abs(q.x - p.x) + Math.abs(q.y - p.y) });
            }
        }
    });

    // Ocupação pelas setas já traçadas: trechos usados e pontos atravessados em cada eixo.
    const usoTrecho = new Map<string, number>();
    const usoLado = new Map<string, number>();
    const passaH = new Map<number, number>();
    const passaV = new Map<number, number>();

    const porta = (c: Caixa, lado: Lado): { porta: Ponto; saida: Ponto; eixo: 0 | 1 } => {
        const cx = c.x + c.w / 2;
        const cy = c.y + c.h / 2;
        const meioX = (v: number) => maisProximo(geo.corredoresX, v);
        const meioY = (v: number) => maisProximo(geo.corredoresY, v);
        switch (lado) {
            case 'cima': return { porta: { x: cx, y: c.y }, saida: { x: cx, y: meioY(c.y - geo.vaoY / 2) }, eixo: 1 };
            case 'baixo': return { porta: { x: cx, y: c.y + c.h }, saida: { x: cx, y: meioY(c.y + c.h + geo.vaoY / 2) }, eixo: 1 };
            case 'esquerda': return { porta: { x: c.x, y: cy }, saida: { x: meioX(c.x - geo.vaoX / 2), y: cy }, eixo: 0 };
            default: return { porta: { x: c.x + c.w, y: cy }, saida: { x: meioX(c.x + c.w + geo.vaoX / 2), y: cy }, eixo: 0 };
        }
    };
    const NORMAL: Record<Lado, [number, number]> = { cima: [0, -1], baixo: [0, 1], esquerda: [-1, 0], direita: [1, 0] };
    const LADOS: Lado[] = ['baixo', 'direita', 'esquerda', 'cima'];

    interface Tracado { caminho: number[]; bruta: RotaBruta }

    /** Soma (+1) ou retira (−1) a ocupação de uma seta traçada. */
    const registrar = (t: Tracado | null, sinal: 1 | -1) => {
        if (!t) {
            return;
        }
        const { caminho, bruta } = t;
        for (const k of [`${bruta.saida.caixa.id}:${bruta.saida.lado}:sai`, `${bruta.chegada.caixa.id}:${bruta.chegada.lado}:chega`]) {
            usoLado.set(k, (usoLado.get(k) ?? 0) + sinal);
        }
        for (let k = 0; k + 1 < caminho.length; k++) {
            const tr = trecho(caminho[k], caminho[k + 1]);
            usoTrecho.set(tr, (usoTrecho.get(tr) ?? 0) + sinal);
            const horizontal = pontos[caminho[k]].y === pontos[caminho[k + 1]].y;
            const mapa = horizontal ? passaH : passaV;
            mapa.set(caminho[k + 1], (mapa.get(caminho[k + 1]) ?? 0) + sinal);
        }
    };

    const tracar = (a: { de: string; para: string }): Tracado | null => {
        const de = porId.get(a.de);
        const para = porId.get(a.para);
        if (!de || !para) {
            return null;
        }
        const vx = para.x + para.w / 2 - (de.x + de.w / 2);
        const vy = para.y + para.h / 2 - (de.y + de.h / 2);

        // Saída e chegada: o fluxo lê de cima para baixo — descendo, sai pela base e entra pelo topo;
        // na mesma linha, pelos lados; voltando, pelos lados. Lado de costas para o outro custa caro.
        const desce = para.y > de.y + 1;
        const mesmaLinha = Math.abs(para.y - de.y) <= 1;
        const custoLado = (l: Lado, sentido: number, ehSaida: boolean) => {
            const [nx, ny] = NORMAL[l];
            if ((nx * vx + ny * vy) * sentido <= 0) {
                return 110; // de costas para o outro: só vale se for o único jeito de não misturar sentidos
            }
            const vertical = l === 'cima' || l === 'baixo';
            if (desce) {
                return vertical ? 0 : ehSaida ? 35 : 60;
            }
            return mesmaLinha ? (vertical ? 80 : 0) : (vertical ? 60 : 0);
        };
        // Lado de caixa já usado custa mais — e MUITO mais se for no sentido contrário: uma seta
        // saindo colada a outra chegando pelo mesmo lado parece uma linha só atravessando a caixa.
        const lotado = (c: Caixa, l: Lado, sentido: 'sai' | 'chega') =>
            (usoLado.get(`${c.id}:${l}:${sentido}`) ?? 0) * LADO_OCUPADO
            + (usoLado.get(`${c.id}:${l}:${sentido === 'sai' ? 'chega' : 'sai'}`) ?? 0) * LADO_CONTRARIO;
        const saidas = LADOS.map((l) => ({ l, ...porta(de, l), custo: custoLado(l, 1, true) + lotado(de, l, 'sai') }));
        const chegadas = LADOS.map((l) => ({ l, ...porta(para, l), custo: custoLado(l, -1, false) + lotado(para, l, 'chega') }));

        // Dijkstra no estado (ponto, eixo em que chegou).
        const n = pontos.length;
        const dist = new Float64Array(n * 2).fill(Infinity);
        const veio = new Int32Array(n * 2).fill(-1);
        const fila = new Heap();
        for (const s of saidas) {
            const i = indice.get(chave(s.saida.x, s.saida.y));
            if (i === undefined || cortaCaixa(s.porta, s.saida, de)) {
                continue;
            }
            const estado = i * 2 + s.eixo;
            const custo = s.custo + Math.abs(s.saida.x - s.porta.x) + Math.abs(s.saida.y - s.porta.y);
            if (custo < dist[estado]) {
                dist[estado] = custo;
                veio[estado] = -2 - LADOS.indexOf(s.l); // marca a porta de saída
                fila.push(custo, estado);
            }
        }
        const fim = new Map<number, { l: Lado; custo: number; eixo: 0 | 1 }>();
        for (const c of chegadas) {
            const i = indice.get(chave(c.saida.x, c.saida.y));
            if (i !== undefined && !cortaCaixa(c.porta, c.saida, para)) {
                fim.set(i, { l: c.l, custo: c.custo, eixo: c.eixo });
            }
        }

        let melhor = Infinity;
        let melhorEstado = -1;
        let melhorLado: Lado = 'cima';
        while (fila.tamanho > 0) {
            const [d, estado] = fila.pop();
            if (d > dist[estado] || d >= melhor) {
                continue;
            }
            const i = estado >> 1;
            const eixo = (estado & 1) as 0 | 1;
            const alvo = fim.get(i);
            if (alvo) {
                const total = d + alvo.custo + (alvo.eixo !== eixo ? DOBRA : 0);
                if (total < melhor) {
                    melhor = total;
                    melhorEstado = estado;
                    melhorLado = alvo.l;
                }
            }
            for (const v of vizinhos[i]) {
                const ocupado = usoTrecho.get(trecho(i, v.j)) ?? 0;
                const cruza = (v.eixo === 0 ? passaV.get(v.j) : passaH.get(v.j)) ?? 0;
                const custo = d + v.comp * (1 + ocupado * CORREDOR_OCUPADO) + (v.eixo !== eixo ? DOBRA : 0) + cruza * CRUZAMENTO;
                const prox = v.j * 2 + v.eixo;
                if (custo < dist[prox]) {
                    dist[prox] = custo;
                    veio[prox] = estado;
                    fila.push(custo, prox);
                }
            }
        }
        if (melhorEstado < 0) {
            return null;
        }

        // Reconstrói: porta de saída → grade → porta de chegada.
        const caminho: number[] = [];
        let e = melhorEstado;
        let ladoSaida: Lado = 'baixo';
        while (e >= 0) {
            caminho.unshift(e >> 1);
            const anterior = veio[e];
            if (anterior <= -2) {
                ladoSaida = LADOS[-2 - anterior];
                break;
            }
            e = anterior;
        }
        const pts: Ponto[] = [porta(de, ladoSaida).porta, ...caminho.map((i) => pontos[i]), porta(para, melhorLado).porta];

        return { caminho, bruta: { pontos: simplificar(pts), saida: { caixa: de, lado: ladoSaida }, chegada: { caixa: para, lado: melhorLado } } };
    };

    // 1ª rodada: cada seta desvia do que as anteriores ocupam. Depois, cada uma é retraçada já
    // sabendo onde TODAS as outras ficaram — a ordem do catálogo deixa de decidir quem fica com o
    // lado bom de uma caixa disputada.
    const tracados = arestas.map((a) => {
        const t = tracar(a);
        registrar(t, 1);

        return t;
    });
    for (let rodada = 0; rodada < RODADAS_DE_AJUSTE; rodada++) {
        arestas.forEach((a, i) => {
            registrar(tracados[i], -1);
            tracados[i] = tracar(a);
            registrar(tracados[i], 1);
        });
    }

    return separarFaixas(tracados.map((t) => t?.bruta ?? null), geo);
}

interface Ponta {
    caixa: Caixa;
    lado: Lado;
}

interface RotaBruta {
    pontos: Ponto[];
    saida: Ponta;
    chegada: Ponta;
}

/** Setas no mesmo corredor e no mesmo trecho ganham faixas paralelas — nunca uma por cima da outra. */
function separarFaixas(brutas: (RotaBruta | null)[], geo: Geometria): (Rota | null)[] {
    interface Trecho { rota: number; seg: number; vertical: boolean; fixo: number; a: number; b: number; faixa: number }
    const rotas = brutas.map((b) => b?.pontos ?? null);
    const trechos: Trecho[] = [];
    rotas.forEach((pts, r) => {
        pts?.forEach((p, s) => {
            const q = pts[s + 1];
            if (!q) {
                return;
            }
            const vertical = p.x === q.x;
            trechos.push({ rota: r, seg: s, vertical, fixo: vertical ? p.x : p.y, a: Math.min(vertical ? p.y : p.x, vertical ? q.y : q.x), b: Math.max(vertical ? p.y : p.x, vertical ? q.y : q.x), faixa: 0 });
        });
    });

    // Agrupa por trilho (mesma linha de corredor) e distribui as faixas onde os trechos se encostam.
    const grupos = new Map<string, Trecho[]>();
    for (const t of trechos) {
        const k = `${t.vertical ? 'v' : 'h'}:${t.fixo}`;
        grupos.set(k, [...(grupos.get(k) ?? []), t]);
    }
    const deslocamento = new Map<string, number>();
    for (const grupo of grupos.values()) {
        grupo.sort((p, q) => p.a - q.a || p.b - q.b);
        const fimDaFaixa: number[] = [];
        for (const t of grupo) {
            let f = fimDaFaixa.findIndex((fim) => fim < t.a - 6);
            if (f < 0) {
                f = fimDaFaixa.length;
                fimDaFaixa.push(0);
            }
            fimDaFaixa[f] = t.b;
            t.faixa = f;
        }
        const total = fimDaFaixa.length;
        const limite = (grupo[0].vertical ? geo.vaoX : geo.vaoY) / 2 - 4;
        const passo = total > 1 ? Math.min(ESPACO_FAIXA, (2 * limite) / (total - 1)) : 0;
        for (const t of grupo) {
            deslocamento.set(`${t.rota}:${t.seg}`, (t.faixa - (total - 1) / 2) * passo);
        }
    }

    // Pontas no mesmo lado de uma caixa (uma seta chegando e outra saindo, ou várias chegando)
    // ficam espalhadas ao longo do lado, com folga visível — coladas, pareciam uma linha só que
    // atravessava a caixa. A ordem segue para onde cada seta vai, para elas não se cruzarem ali.
    const lados = new Map<string, { rota: number; seg: number; rumo: number; comp: number }[]>();
    brutas.forEach((b, r) => {
        if (!b || b.pontos.length < 2) {
            return;
        }
        const n = b.pontos.length;
        const ultimo = n - 2;
        // Para onde a seta segue depois do trecho que encosta na caixa — é isso que ordena as pontas.
        const destinos = [[b.saida, 0, b.pontos[Math.min(2, n - 1)]], [b.chegada, ultimo, b.pontos[Math.max(n - 3, 0)]]] as const;
        for (const [ponta, seg, rumoPonto] of destinos) {
            const horizontalNoLado = ponta.lado === 'cima' || ponta.lado === 'baixo';
            const k = `${ponta.caixa.id}:${ponta.lado}`;
            const rumo = horizontalNoLado ? rumoPonto.x : rumoPonto.y;
            lados.set(k, [...(lados.get(k) ?? []), { rota: r, seg, rumo, comp: horizontalNoLado ? ponta.caixa.w : ponta.caixa.h }]);
        }
    });
    // Uma seta reta (um trecho só) encosta em dois lados ao mesmo tempo — a base de uma caixa e o
    // topo da outra: a posição que ganhou num lado vale no outro, e as demais pontas desviam dela.
    const fixadas = new Set<string>();
    for (const grupo of [...lados.values()].sort((p, q) => q.length - p.length)) {
        if (grupo.length < 2) {
            continue;
        }
        grupo.sort((p, q) => p.rumo - q.rumo);
        const util = grupo[0].comp - 10; // até perto das quinas, sem encostar no arredondado
        const vagas = grupo.map((_, i) => ((i + 1) / (grupo.length + 1) - 0.5) * util);
        const livres = grupo.filter((g) => !fixadas.has(`${g.rota}:${g.seg}`));
        for (const g of grupo.filter((g) => fixadas.has(`${g.rota}:${g.seg}`))) {
            const atual = deslocamento.get(`${g.rota}:${g.seg}`) ?? 0;
            const mais = vagas.reduce((m, v, i) => (Math.abs(v - atual) < Math.abs(vagas[m] - atual) ? i : m), 0);
            vagas.splice(mais, 1);
        }
        livres.forEach((g, i) => {
            const k = `${g.rota}:${g.seg}`;
            deslocamento.set(k, vagas[i]);
            fixadas.add(k);
        });
    }

    return rotas.map((pts, r) => {
        if (!pts) {
            return null;
        }
        // `seg`, e não `s`: a lei do plural da interface confunde a chamada com um plural entre parênteses.
        const desl = (seg: number) => deslocamento.get(`${r}:${seg}`) ?? 0;
        const vertical = (seg: number) => pts[seg].x === pts[seg + 1].x;
        const novos = pts.map((p, i) => {
            let { x, y } = p;
            for (const seg of [i - 1, i]) {
                if (seg < 0 || seg >= pts.length - 1) {
                    continue;
                }
                if (vertical(seg)) {
                    x = p.x + desl(seg);
                } else {
                    y = p.y + desl(seg);
                }
            }
            return { x, y };
        });

        // Rótulo no meio do trecho mais longo.
        let maior = 0;
        for (let s = 1; s < novos.length - 1; s++) {
            if (comprimento(novos[s], novos[s + 1]) > comprimento(novos[maior], novos[maior + 1])) {
                maior = s;
            }
        }
        const a = novos[maior];
        const b = novos[maior + 1];
        const rotulo = a.x === b.x
            ? { x: a.x + 5, y: (a.y + b.y) / 2 + 3, ancora: 'start' as const }
            : { x: (a.x + b.x) / 2, y: a.y - 4, ancora: 'middle' as const };

        return { pontos: novos, rotulo };
    });
}

function simplificar(pts: Ponto[]): Ponto[] {
    const saida: Ponto[] = [];
    for (const p of pts) {
        const ultimo = saida[saida.length - 1];
        if (ultimo && ultimo.x === p.x && ultimo.y === p.y) {
            continue;
        }
        const penultimo = saida[saida.length - 2];
        if (penultimo && ultimo && ((penultimo.x === ultimo.x && ultimo.x === p.x) || (penultimo.y === ultimo.y && ultimo.y === p.y))) {
            saida[saida.length - 1] = p;
            continue;
        }
        saida.push(p);
    }
    return saida;
}

const comprimento = (a: Ponto, b: Ponto) => Math.abs(a.x - b.x) + Math.abs(a.y - b.y);
const chave = (x: number, y: number) => `${x},${y}`;
const trecho = (i: number, j: number) => (i < j ? `${i}-${j}` : `${j}-${i}`);
const unicos = (v: number[]) => [...new Set(v.map((n) => Math.round(n * 10) / 10))].sort((a, b) => a - b);
const maisProximo = (lista: number[], v: number) => lista.reduce((m, x) => (Math.abs(x - v) < Math.abs(m - v) ? x : m), lista[0]);

/** Fila de prioridade mínima (heap binário) — o Dijkstra roda a cada desenho. */
class Heap {
    private custos: number[] = [];
    private itens: number[] = [];

    get tamanho(): number {
        return this.itens.length;
    }

    push(custo: number, item: number): void {
        this.custos.push(custo);
        this.itens.push(item);
        let i = this.itens.length - 1;
        while (i > 0) {
            const pai = (i - 1) >> 1;
            if (this.custos[pai] <= this.custos[i]) {
                break;
            }
            this.troca(i, pai);
            i = pai;
        }
    }

    pop(): [number, number] {
        const topo: [number, number] = [this.custos[0], this.itens[0]];
        const ultimoCusto = this.custos.pop() as number;
        const ultimoItem = this.itens.pop() as number;
        if (this.itens.length > 0) {
            this.custos[0] = ultimoCusto;
            this.itens[0] = ultimoItem;
            let i = 0;
            for (;;) {
                const e = 2 * i + 1;
                const d = e + 1;
                let menor = i;
                if (e < this.itens.length && this.custos[e] < this.custos[menor]) {
                    menor = e;
                }
                if (d < this.itens.length && this.custos[d] < this.custos[menor]) {
                    menor = d;
                }
                if (menor === i) {
                    break;
                }
                this.troca(i, menor);
                i = menor;
            }
        }
        return topo;
    }

    private troca(i: number, j: number): void {
        [this.custos[i], this.custos[j]] = [this.custos[j], this.custos[i]];
        [this.itens[i], this.itens[j]] = [this.itens[j], this.itens[i]];
    }
}

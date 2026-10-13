/* ============================================================================
   A IMPRESSÃO da via na térmica de 80 mm — o mesmo caminho do CODECON
   ----------------------------------------------------------------------------
   O aplicativo do CODECON imprime na mesma impressora térmica Bluetooth sem
   falar com ela diretamente: monta o cupom em HTML e o entrega ao diálogo de
   impressão do Android, e a impressora é alcançada pelo SERVIÇO DE IMPRESSÃO
   instalado e pareado no aparelho. Aqui é igual: o Chrome do Android abre o
   mesmo diálogo para `window.print()`. Sem impressora pareada, o diálogo
   oferece "Salvar como PDF" — a via não fica presa a um equipamento.

   O CSS é o do cupom do CODECON, com as lições que ele já pagou na térmica:
   margem zero no @page com padding SIMÉTRICO (o cupom saía torto), sans-serif e
   negrito em tudo (a monoespaçada fina saía apagada) e o brasão binarizado,
   embutido.
   ============================================================================ */

import { BRASAO_SALVADOR_DATA_URL } from './brasao-impressao';
import type { ViaImpressa } from './dados-prototipo';

const escapar = (texto: string): string =>
    texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const ESTILO = `
  @page { margin: 0; }
  * { font-family: Arial, Helvetica, 'DejaVu Sans', sans-serif; box-sizing: border-box; }
  html, body { margin: 0; }
  body { font-size: 14px; font-weight: 700; color: #000; width: 100%; line-height: 1.4; padding: 8px 10px; }
  .center { text-align: center; }
  .b { font-weight: 900; }
  .logo { text-align: center; margin: 2px 0 6px; }
  .logo img { width: 150px; max-width: 60%; height: auto; image-rendering: crisp-edges; }
  .hr { border-top: 2px solid #000; margin: 6px 0; }
  .titulo { font-size: 15px; font-weight: 900; margin: 5px 0; }
  .lbl { font-weight: 900; text-transform: uppercase; font-size: 14px; margin-top: 7px; }
  .item { margin: 3px 0; }
  .assina { margin-top: 18px; text-align: center; }
  .assina-vis { height: 52px; }
  .assina-recusa { height: 52px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 14px; }
  .assina-linha { border-top: 2px solid #000; padding-top: 3px; font-size: 13px; font-weight: 700; }
  .rodape { font-size: 11px; text-align: center; margin-top: 8px; }
`;

/**
 * O cupom da via, em HTML — rótulo em cima, valor embaixo, uma coluna só,
 * porque é o que cabe em 80 mm.
 */
export function htmlDaVia(via: ViaImpressa, fiscal: { nome: string; matricula: string }): string {
    const linhas: string[] = [
        `<div class="logo"><img src="${BRASAO_SALVADOR_DATA_URL}" alt="Prefeitura Municipal do Salvador" /></div>`,
        '<div class="center b">PREFEITURA MUNICIPAL DE SALVADOR</div>',
        '<div class="center" style="font-size:12px">SECRETARIA MUNICIPAL DE ORDEM PÚBLICA</div>',
        '<div class="center" style="font-size:12px">SEMOP · SEFAL</div>',
        '<div class="hr"></div>',
        `<div class="center titulo">${escapar(via.titulo)}</div>`,
        `<div class="center b">Nº ${escapar(via.numero)}</div>`,
        '<div class="hr"></div>',
    ];

    for (const campo of via.campos) {
        linhas.push(`<div class="lbl">${escapar(campo.rotulo)}</div>`, `<div>${escapar(campo.valor)}</div>`);
    }

    for (const lista of via.listas.filter((l) => l.itens.length > 0)) {
        linhas.push('<div class="hr"></div>', `<div class="lbl">${escapar(lista.titulo)}</div>`);
        lista.itens.forEach((item) => linhas.push(`<div class="item">• ${escapar(item)}</div>`));
    }

    linhas.push('<div class="hr"></div>');

    for (const a of via.assinaturas) {
        const vis =
            a.estado === 'recusada'
                ? '<div class="assina-recusa">** ASSINATURA RECUSADA **</div>'
                : '<div class="assina-vis"></div>';
        const nome = a.nome ? `${escapar(a.nome)}<br>` : '';

        linhas.push(`<div class="assina">${vis}<div class="assina-linha">${nome}${escapar(a.rotulo)}</div></div>`);
    }

    linhas.push(
        `<div class="assina"><div class="assina-vis"></div><div class="assina-linha">${escapar(fiscal.nome)} — matrícula ${escapar(fiscal.matricula)}<br>Agente fiscal</div></div>`,
        '<div class="hr"></div>',
        `<div class="rodape">${escapar(via.rodape)}</div>`,
        '<div class="rodape">Documento emitido pelo SEFAL</div>',
    );

    return `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>${escapar(via.titulo)} ${escapar(via.numero)}</title><style>${ESTILO}</style></head><body>${linhas.join('')}</body></html>`;
}

/**
 * Entrega o HTML ao diálogo de impressão do aparelho, num quadro oculto — a
 * tela do aplicativo não é o que sai no papel. Resolve quando o diálogo fecha
 * (ou depois de um tempo, nos navegadores que não avisam).
 */
export function imprimirHtml(html: string): Promise<void> {
    return new Promise((resolve, reject) => {
        const quadro = document.createElement('iframe');

        quadro.setAttribute('aria-hidden', 'true');
        quadro.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
        document.body.appendChild(quadro);

        const janela = quadro.contentWindow;
        const doc = quadro.contentDocument;

        if (!janela || !doc) {
            quadro.remove();
            reject(new Error('Este navegador não abriu a impressão. Use o Chrome do aparelho.'));

            return;
        }

        let terminou = false;
        const terminar = () => {
            if (terminou) {
                return;
            }

            terminou = true;
            window.setTimeout(() => quadro.remove(), 500);
            resolve();
        };

        doc.open();
        doc.write(html);
        doc.close();

        /* O brasão é imagem: espera ela carregar, senão o cupom sai sem ele. */
        const imprimir = () => {
            janela.addEventListener('afterprint', terminar, { once: true });

            try {
                janela.focus();
                janela.print();
            } catch {
                quadro.remove();
                reject(new Error('Não foi possível abrir a impressão neste aparelho.'));

                return;
            }

            // Navegadores que não disparam `afterprint` (ou o disparam cedo).
            window.setTimeout(terminar, 60_000);
        };

        const imagens = Array.from(doc.images);
        const pendentes = imagens.filter((i) => !i.complete);

        if (pendentes.length === 0) {
            imprimir();

            return;
        }

        let restam = pendentes.length;
        pendentes.forEach((i) => {
            const pronto = () => {
                restam -= 1;

                if (restam === 0) {
                    imprimir();
                }
            };

            i.addEventListener('load', pronto, { once: true });
            i.addEventListener('error', pronto, { once: true });
        });
    });
}

/* ============================================================================
   A fila do aparelho — o que ainda não chegou ao servidor
   ----------------------------------------------------------------------------
   Rua não tem sinal garantido. O registro (com as fotos) é guardado no
   IndexedDB do navegador assim que nasce, e só sai daqui quando o servidor
   confirma o recebimento. Fechar o aplicativo, recarregar a página ou acabar a
   bateria não perde o que o fiscal fez.

   Cada entrada leva a MATRÍCULA de quem registrou: o aparelho é compartilhado
   entre fiscais, e um não pode ver (nem enviar em seu nome) o que o outro
   deixou na fila.
   ============================================================================ */

import type { Registro } from './dados-prototipo';

export type EntradaDaFila = { matricula: string; registro: Registro; fotos: Blob[] };

/** Um levantamento concluído no aparelho e ainda não confirmado pelo servidor. */
export type LevantamentoNaFila = {
    matricula: string;
    clientId: string;
    rua: string;
    bairro: string;
    equipes: string[];
    iniciadoEm: string | null;
    concluidoEm: string;
    linhas: {
        id: string;
        nome: string;
        documento: string;
        contato: string;
        referencia: string;
        atividade: string;
        latitude: number | null;
        longitude: number | null;
    }[];
    /** A foto de cada linha, pela chave da linha. */
    fotos: Record<string, Blob>;
};

const BANCO = 'sefal-campo';
const LOJA = 'registros';
const LEVANTAMENTOS = 'levantamentos';

const abrir = (): Promise<IDBDatabase> =>
    new Promise((resolve, reject) => {
        const pedido = indexedDB.open(BANCO, 2);

        pedido.onupgradeneeded = () => {
            const banco = pedido.result;

            if (!banco.objectStoreNames.contains(LOJA)) {
                banco.createObjectStore(LOJA, { keyPath: 'registro.clientId' });
            }

            if (!banco.objectStoreNames.contains(LEVANTAMENTOS)) {
                banco.createObjectStore(LEVANTAMENTOS, { keyPath: 'clientId' });
            }
        };
        pedido.onsuccess = () => resolve(pedido.result);
        pedido.onerror = () => reject(pedido.error);
    });

async function naLoja<T>(
    modo: IDBTransactionMode,
    acao: (loja: IDBObjectStore) => IDBRequest<T>,
    loja: string = LOJA,
): Promise<T> {
    const banco = await abrir();

    return new Promise((resolve, reject) => {
        const pedido = acao(banco.transaction(loja, modo).objectStore(loja));

        pedido.onsuccess = () => resolve(pedido.result);
        pedido.onerror = () => reject(pedido.error);
    }).finally(() => banco.close()) as Promise<T>;
}

/** Guarda (ou atualiza) o registro. Sem IndexedDB (janela privada), segue só na memória. */
export async function guardarNaFila(entrada: EntradaDaFila): Promise<void> {
    try {
        await naLoja('readwrite', (loja) => loja.put(entrada));
    } catch {
        // Armazenamento bloqueado: o registro continua na memória da aba.
    }
}

export async function tirarDaFila(clientId: string): Promise<void> {
    try {
        await naLoja('readwrite', (loja) => loja.delete(clientId));
    } catch {
        // Sem armazenamento: nada a apagar.
    }
}

/** O que ESTE fiscal deixou na fila do aparelho. */
export async function filaDe(matricula: string): Promise<EntradaDaFila[]> {
    try {
        const todas = await naLoja<EntradaDaFila[]>('readonly', (loja) => loja.getAll());

        return todas.filter((e) => e.matricula === matricula);
    } catch {
        return [];
    }
}

/* ------------------------------- Levantamentos ------------------------------- */

export async function guardarLevantamento(entrada: LevantamentoNaFila): Promise<void> {
    try {
        await naLoja('readwrite', (loja) => loja.put(entrada), LEVANTAMENTOS);
    } catch {
        // Armazenamento bloqueado: o levantamento continua na memória da aba.
    }
}

export async function tirarLevantamento(clientId: string): Promise<void> {
    try {
        await naLoja('readwrite', (loja) => loja.delete(clientId), LEVANTAMENTOS);
    } catch {
        // Sem armazenamento: nada a apagar.
    }
}

/** Os levantamentos que ESTE fiscal deixou na fila do aparelho. */
export async function levantamentosDe(matricula: string): Promise<LevantamentoNaFila[]> {
    try {
        const todos = await naLoja<LevantamentoNaFila[]>('readonly', (loja) => loja.getAll(), LEVANTAMENTOS);

        return todos.filter((e) => e.matricula === matricula);
    } catch {
        return [];
    }
}

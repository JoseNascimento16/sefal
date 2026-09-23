<?php

namespace App\Services\ESalvador;

/**
 * O cliente da API do e-Salvador — a ESTRUTURA da escrita, ainda sem escrever.
 *
 * O e-Salvador é o processo administrativo eletrônico da Prefeitura (SEMGE). O
 * que a fiscalização produz volta para lá de dois jeitos (ver
 * docs/integracoes/esalvador.md, "Como o resultado da fiscalização VOLTA"):
 *
 *  - **responder** um processo que veio de lá: `POST /criar-tramite`
 *    (`descricao` + anexos em base64) e, se for o caso, `PUT /tramita-processo`
 *    / `POST /arquiva-processo`;
 *  - **abrir** um processo para o que NÃO veio de lá (a demanda avulsa): o
 *    endpoint de criação ainda não está documentado para nós — é pergunta aberta
 *    à SEMGE.
 *
 * ⚠️ NENHUM dos dois é chamado hoje. A API é de PRODUÇÃO, não há homologação, e
 * a escrita (POST/PUT/DELETE) está PROIBIDA até o sistema amadurecer (decisão do
 * dono, 15/09/2026, reafirmada em 23/09/2026: "montar a estrutura; a criação de
 * processo ainda não podemos fazer"). Então:
 *
 *  - com a integração DESLIGADA (`ESALVADOR_LIGADA=false`, o estado atual), os
 *    métodos devolvem `null` e NADA sai para a rede — o ato fica registrado
 *    localmente e o chefe o faz à mão no e-Salvador;
 *  - com a integração LIGADA, eles lançam {@see EscritaNaoLiberada}: ligar o
 *    interruptor por engano não pode fazer o SEFAL escrever num processo real.
 *
 * Quando a escrita for liberada, o corpo dos dois métodos passa a chamar a API
 * (autenticando com `POST /login`, como o reconhecimento já provou) e o resto do
 * sistema não muda: quem chama já trata "enviado" e "não enviado".
 */
class ESalvador
{
    public function ligado(): bool
    {
        return (bool) config('esalvador.ligada', false);
    }

    /**
     * Responde, no processo de origem, o que a fiscalização apurou.
     *
     * @param  string  $identificador  o processo no e-Salvador (`assunto.unidade.numero/ano`)
     * @return array<string, mixed>|null o que a API devolveu; `null` = nada enviado (integração desligada)
     *
     * @throws EscritaNaoLiberada
     */
    public function responderProcesso(string $identificador, string $descricao): ?array
    {
        if (! $this->ligado()) {
            return null;
        }

        // POST /criar-tramite {numero, ano, descricao, nome_arquivo[], arquivo[]}
        throw EscritaNaoLiberada::para('responder processo '.$identificador);
    }

    /**
     * Abre um processo com o resultado de uma demanda que não veio do e-Salvador
     * (a avulsa).
     *
     * @param  array<string, mixed>  $dados  assunto, requerente, descrição — o contrato exato depende da SEMGE
     * @return array<string, mixed>|null o processo criado; `null` = nada enviado (integração desligada)
     *
     * @throws EscritaNaoLiberada
     */
    public function abrirProcesso(array $dados): ?array
    {
        if (! $this->ligado()) {
            return null;
        }

        // Endpoint de criação ainda não documentado para a SEMOP — pergunta aberta à SEMGE.
        throw EscritaNaoLiberada::para('abrir processo');
    }
}

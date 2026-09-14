<?php

use App\Models\Demanda;

/*
|--------------------------------------------------------------------------
| Demandas — os catálogos do fluxo de entrada e triagem
|--------------------------------------------------------------------------
|
| Substitui a parte de CATÁLOGO dos arquivos de protótipo
| (`prototipo_caixa_entrada.php` e `prototipo_denuncias.php`). O que era dado de
| demonstração foi para o banco pelos seeders; o que é lista de escolha do
| negócio ficou aqui, num lugar só.
|
| É a FONTE ÚNICA: a tela oferece estas opções e o servidor valida contra estas
| mesmas opções. Escritas nos dois lugares, um dia a tela ofereceria algo que o
| servidor recusa — e o usuário levaria a culpa por um erro que não cometeu.
|
| ⚠️ As SITUAÇÕES não estão aqui: elas vivem em `App\Models\Demanda::SITUACOES`,
| porque não são só rótulos — há regra pendurada em cada uma (o que está aberto,
| o que já fechou, o que conta prazo). Catálogo com regra mora no model.
|
*/

return [

    /*
     * Os canais por onde um fato chega. `nome` é o que a pessoa lê; a CHAVE é o
     * que viaja no banco, na API e no relatório.
     *
     * `entrada_padrao` diz como aquele canal costuma chegar HOJE: o e-Salvador e
     * o Salvador Digital ainda entregam papel ao coordenador (`balcao`), e vão
     * virar `integracao` quando a API existir. Ofício e pedido de licença nascem
     * internos e não têm integração prevista.
     */
    'canais' => [

        Demanda::CANAL_E_SALVADOR => [
            'nome' => 'e-Salvador',
            'sistema' => 'Portal e-Salvador — Ouvidoria Geral do Município',
            'artigo' => 'o',
            'entrada_padrao' => Demanda::ENTRADA_INTEGRACAO,
            /*
             * O canal admite denúncia anônima? O e-Salvador exige conta (gov.br),
             * então não: quem abre está identificado. É esta chave que faz a tela
             * deixar de perguntar o que não existe naquele canal.
             */
            'admite_anonima' => false,
            'tem_anexo' => true,
            /*
             * ⚠️ É o canal que mais produz denúncia REPETIDA do mesmo fato: dez
             * pessoas relatam as mesmas mesas na calçada em dez protocolos. Por
             * isso é nele que a pré-triagem por agrupamento importa.
             */
            'agrupa' => true,
        ],

        Demanda::CANAL_SALVADOR_DIGITAL => [
            'nome' => 'Salvador Digital',
            'sistema' => 'Central de Atendimento Salvador Digital',
            'artigo' => 'a',
            'entrada_padrao' => Demanda::ENTRADA_INTEGRACAO,
            // Atendimento por telefone: pode ser anônima, e ninguém anexa foto.
            'admite_anonima' => true,
            'tem_anexo' => false,
            'agrupa' => true,
        ],

        Demanda::CANAL_NOVA_LICENCA => [
            'nome' => 'Nova licença',
            'sistema' => 'Processo de licenciamento da SEMOP',
            'artigo' => 'o',
            'entrada_padrao' => Demanda::ENTRADA_BALCAO,
            'admite_anonima' => false,
            'tem_anexo' => true,
            // Pedido de licença é de UM requerente para UM ponto: agrupar não faz sentido.
            'agrupa' => false,
        ],

        Demanda::CANAL_OFICIO => [
            'nome' => 'Ofício',
            'sistema' => 'Ofício de órgão ou do Ministério Público',
            'artigo' => 'o',
            'entrada_padrao' => Demanda::ENTRADA_BALCAO,
            'admite_anonima' => false,
            'tem_anexo' => true,
            'agrupa' => false,
        ],
    ],

    /*
     * Por que uma demanda volta ou é arquivada. A escolha é de lista para o
     * relatório poder somar por motivo; o texto livre continua obrigatório ao
     * lado dela, porque o motivo genérico não conta o caso.
     */
    'motivos_de_devolucao' => [
        'Endereço insuficiente para localizar o ponto',
        'Fora da competência da SEFAL',
        'Demanda duplicada — já existe registro do mesmo fato',
        'Objeto já regularizado',
        'Pedido de licença sem a documentação exigida',
        'Denúncia sem elementos mínimos para vistoria',
    ],

    /* Para onde a demanda vai quando não é atendida. */
    'destinos_de_retorno' => [
        'Devolvida ao remetente',
        'Arquivada',
    ],

    /*
     * O prazo padrão de atendimento, em dias, quando o formulário não informa
     * outro. O prazo real de cada canal é pergunta aberta ao cliente (PEND) —
     * quando ela for respondida, vira prazo POR CANAL, aqui.
     */
    'prazo_padrao_em_dias' => 10,

    /*
     * Pré-triagem por agrupamento: quando o sistema propõe que duas denúncias
     * são o mesmo fato.
     */
    'agrupamento' => [
        /*
         * Confiança mínima para a proposta chegar à tela. Abaixo disso a
         * sugestão é ruído: o coordenador aprende a ignorar a lista inteira, e
         * aí o recurso deixa de existir mesmo estando ligado.
         */
        'confianca_minima' => 0.6,

        /*
         * Só denúncias recebidas dentro desta janela são comparadas entre si.
         * Sem ela, o sistema proporia agrupar a reclamação de hoje com a de seis
         * meses atrás sobre o mesmo ponto — que é outro fato, e já foi
         * respondido.
         */
        'janela_em_dias' => 30,

        // Quantas propostas a tela mostra por vez, as mais confiantes primeiro.
        'limite_por_demanda' => 5,
    ],
];

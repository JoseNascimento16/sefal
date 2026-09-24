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
     * `entrada_padrao` diz como aquele canal costuma chegar HOJE: o e-Salvador
     * chega por integração (a API existe; a leitura está em reconhecimento); o
     * Fala Salvador não tem API e é digitado; ofício, pedido de licença e avulsa
     * nascem em papel/telefone e não têm integração prevista.
     *
     * `registro` diz QUEM digita o canal quando ele chega fora da integração:
     * `chefe` (na Caixa de Entrada) ou `lider` (na própria tela do canal). É o
     * que decide o que o formulário da Caixa oferece — o Fala Salvador não
     * aparece lá porque só os líderes o acessam (decisão do dono, 22/09/2026).
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
             * pessoas relatam as mesas na calçada em dez protocolos. Por
             * isso é nele que a pré-triagem por agrupamento importa.
             */
            'agrupa' => true,
            // Enquanto a integração não lê, o papel que chega é digitado pelo chefe.
            'registro' => 'chefe',
            /*
             * `retorno` diz como o RESULTADO volta ao canal, concluído o trabalho
             * ({@see \App\Support\RetornoAoCanal}): `tramite` = o chefe responde
             * no processo de origem; `processo` = o chefe abre um processo (a
             * avulsa não tem); ausente = não volta por sistema. A escrita na API
             * do e-Salvador está proibida por enquanto: o ato é registrado aqui
             * e feito à mão lá.
             */
            'retorno' => 'tramite',
            // Onde o retorno é feito — o nome que a tela escreve ("Resposta ao …").
            'retorno_em' => 'e-Salvador',
        ],

        Demanda::CANAL_FALA_SALVADOR => [
            'nome' => 'Fala Salvador',
            'sistema' => 'Central de Atendimento Fala Salvador (156)',
            'artigo' => 'a',
            /*
             * Sem API. Só os LÍDERES acessam o Fala Salvador; o SEFAL é
             * intermediário de registro — o líder digita aqui o que recebeu lá,
             * para o caso andar pelo fluxo, e responde ao cidadão no próprio
             * Fala Salvador.
             */
            'entrada_padrao' => Demanda::ENTRADA_BALCAO,
            // Atendimento por telefone: pode ser anônima, e ninguém anexa foto.
            'admite_anonima' => true,
            'tem_anexo' => false,
            'agrupa' => true,
            'registro' => 'lider',
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
            'registro' => 'chefe',
            /*
             * A licença chega pelo e-Salvador (assunto 216) e é respondida lá. Na
             * tela, é a aba "Licenças" da caixa do e-Salvador (dono, 24/09/2026).
             */
            'retorno' => 'tramite',
            'retorno_em' => 'e-Salvador',
        ],

        Demanda::CANAL_OFICIO => [
            'nome' => 'Ofício',
            'sistema' => 'Ofício de órgão ou do Ministério Público',
            'artigo' => 'o',
            'entrada_padrao' => Demanda::ENTRADA_BALCAO,
            'admite_anonima' => false,
            'tem_anexo' => true,
            'agrupa' => false,
            'registro' => 'chefe',
        ],

        Demanda::CANAL_AVULSA => [
            'nome' => 'Avulsa',
            // O nome da CAIXA (a tela), no plural — o `nome` é o de cada demanda.
            'titulo' => 'Avulsas',
            'sistema' => 'ligação ou e-mail de superior ao Chefe de Setor',
            'artigo' => 'a',
            'entrada_padrao' => Demanda::ENTRADA_BALCAO,
            // Quem pede é um superior identificado; o "requerente" é ele.
            'admite_anonima' => false,
            // E-mail encaminhado, print, ofício informal: há o que anexar.
            'tem_anexo' => true,
            // Um pedido, uma ação: agrupar não faz sentido.
            'agrupa' => false,
            'registro' => 'chefe',
            /*
             * Concluída, o chefe DELIBERA: abre processo no e-Salvador com o
             * resultado, ou encerra só com a fiscalização (dono, 24/09/2026).
             */
            'retorno' => 'processo',
            'retorno_em' => 'e-Salvador',
        ],

        Demanda::CANAL_E_PROTOCOLO => [
            'nome' => 'e-Protocolo',
            'sistema' => 'e-Protocolo — atendimento presencial na sede da SEFAL',
            'artigo' => 'o',
            /*
             * O cidadão vai à sede e o atendimento é protocolado no e-Protocolo.
             * Sem integração: o chefe digita. Ainda não se sabe se o protocolo
             * passa pelo e-Salvador antes de chegar a ele (dono, 24/09/2026) — por
             * isso a caixa é própria, e a resposta fica registrada aqui.
             */
            'entrada_padrao' => Demanda::ENTRADA_BALCAO,
            'admite_anonima' => false,
            'tem_anexo' => true,
            'agrupa' => false,
            'registro' => 'chefe',
            'retorno' => 'tramite',
            'retorno_em' => 'e-Protocolo',
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

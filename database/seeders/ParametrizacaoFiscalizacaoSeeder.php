<?php

namespace Database\Seeders;

use App\Models\AtividadeAmbulante;
use App\Models\MotivoRecusa;
use App\Models\OrigemOperacao;
use App\Models\ParametroFiscalizacao;
use App\Models\TipoInfracao;
use App\Models\TipoOperacao;
use App\Models\UnidadeMedida;
use Illuminate\Database\Seeder;

/**
 * O ponto de partida das listas de escolha e dos parâmetros da fiscalização.
 *
 * Não é dado de demonstração: é o mínimo para a operação começar. Lista vazia
 * trava o trabalho em silêncio — o fiscal abre o formulário em rua e não tem o
 * que escolher, sem nada em tela dizendo que falta parametrizar.
 *
 * Idempotente e NÃO destrutivo (`firstOrCreate` pela chave de negócio): rodar de
 * novo cria só o que falta e nunca desfaz o que o gestor ajustou — inativação,
 * descrição reescrita, sigla corrigida. É o que permite semear de novo a cada
 * deploy sem medo.
 *
 * Os valores vieram da spec de design e do vocabulário da SEMOP; o gestor
 * ajusta pela tela de Parametrização, que é a dona da lista daqui em diante.
 */
class ParametrizacaoFiscalizacaoSeeder extends Seeder
{
    public function run(): void
    {
        $this->tiposDeInfracao();
        $this->atividadesDoAmbulante();
        $this->unidadesDeMedida();
        $this->tiposDeOperacao();
        $this->origensDeOperacao();
        $this->motivosDeRecusa();
        $this->parametros();
    }

    private function tiposDeInfracao(): void
    {
        $tipos = [
            'Sem permissão para a atividade' => 'Exerce comércio ambulante sem permissão válida para o ramo encontrado.',
            'Área não autorizada' => 'Ocupa ponto fora da área permitida na permissão ou em local proibido por norma.',
            'Produto impróprio para consumo' => 'Mercadoria alimentícia sem condições de conservação, higiene ou validade.',
            'Ocupação irregular do passeio' => 'Estrutura ou mercadoria obstruindo a passagem de pedestres.',
            'Recusa de identificação' => 'Nega-se a apresentar documento ou a permissão à equipe de fiscalização.',
        ];

        foreach ($tipos as $nome => $descricao) {
            TipoInfracao::firstOrCreate(['nome' => $nome], ['descricao' => $descricao, 'ativo' => true]);
        }
    }

    private function atividadesDoAmbulante(): void
    {
        $atividades = [
            'Alimentos preparados',
            'Bebidas',
            'Frutas e verduras',
            'Vestuário e acessórios',
            'Artesanato',
        ];

        foreach ($atividades as $nome) {
            AtividadeAmbulante::firstOrCreate(['nome' => $nome], ['ativo' => true]);
        }
    }

    private function unidadesDeMedida(): void
    {
        // A sigla é o que sai no documento impresso em rua.
        $unidades = [
            'Unidade' => 'un',
            'Quilograma' => 'kg',
            'Litro' => 'L',
            'Caixa' => 'cx',
            'Dúzia' => 'dz',
        ];

        foreach ($unidades as $nome => $sigla) {
            UnidadeMedida::firstOrCreate(['nome' => $nome], ['sigla' => $sigla, 'ativo' => true]);
        }
    }

    private function tiposDeOperacao(): void
    {
        $tipos = [
            'Fiscalização de rotina',
            'Operação conjunta',
            'Mutirão programado',
            'Atendimento a demanda',
        ];

        foreach ($tipos as $nome) {
            TipoOperacao::firstOrCreate(['nome' => $nome], ['ativo' => true]);
        }
    }

    private function origensDeOperacao(): void
    {
        $origens = [
            'Denúncia do cidadão',
            'Demanda do Ministério Público',
            'Planejamento da SEMOP',
            'Solicitação de outro órgão',
            'Ouvidoria',
        ];

        foreach ($origens as $nome) {
            OrigemOperacao::firstOrCreate(['nome' => $nome], ['ativo' => true]);
        }
    }

    private function motivosDeRecusa(): void
    {
        $motivos = [
            'Foto ilegível',
            'Cadastro duplicado',
            'Dados insuficientes',
            'Localização incompatível',
        ];

        foreach ($motivos as $nome) {
            MotivoRecusa::firstOrCreate(['nome' => $nome], ['ativo' => true]);
        }
    }

    /**
     * Os números da regra de negócio.
     *
     * Ficam no banco — e não em constante no código — porque o cliente pode
     * querer mudá-los sem release. A tela de edição nasce junto com a cadeia de
     * fiscalização, que é quem os lê: botão que muda um número sem fluxo que o
     * consuma não muda nada.
     */
    private function parametros(): void
    {
        $parametros = [
            'prazo_notificacao_dias' => [
                'valor' => '10',
                'descricao' => 'Prazo, em dias corridos, para o permissionário atender a uma notificação.',
            ],
        ];

        foreach ($parametros as $chave => $dados) {
            ParametroFiscalizacao::firstOrCreate(['chave' => $chave], $dados);
        }
    }
}

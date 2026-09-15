<?php

namespace App\Models;

use App\Support\Documento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * A DEMANDA — tudo que entra e pede uma decisão.
 *
 * É a mesma entidade que as telas chamam de "Caixa de Entrada" (o papel que o
 * coordenador digita) e de "Denúncias" (o que chega por integração). A razão de
 * ser uma tabela só está na migration; aqui o que importa é a consequência:
 * **a regra de prazo, de roteamento e de trâmite é escrita UMA vez**.
 *
 * ## O trâmite passa todo por {@see registrar()}
 *
 * Nenhum caminho de código muda `situacao` na mão. Mudar a situação sem deixar
 * passo é apagar a memória do processo — e foi assim que, no protótipo, tela e
 * histórico podiam discordar. Aqui as duas coisas acontecem juntas ou não
 * acontecem: a situação da demanda é, por construção, a do último passo.
 *
 * @property int $id
 * @property string $protocolo
 * @property string $canal
 * @property string $entrada
 * @property string|null $numero_origem
 * @property Carbon $recebida_em
 * @property Carbon|null $prazo_em
 * @property bool $anonima
 * @property string|null $requerente
 * @property string|null $documento
 * @property string|null $email
 * @property string|null $telefone
 * @property string $assunto
 * @property string|null $relato
 * @property string|null $logradouro
 * @property string|null $numero
 * @property string|null $referencia
 * @property string|null $bairro
 * @property bool $endereco_impreciso
 * @property string $situacao
 * @property int|null $area_id
 * @property int|null $equipe_id
 * @property int|null $operacao_id
 * @property Carbon|null $concluida_em
 * @property-read Collection<int, DemandaTramite> $tramites
 */
#[Fillable([
    'protocolo', 'canal', 'entrada', 'numero_origem', 'recebida_em', 'prazo_em',
    'anonima', 'requerente', 'documento', 'email', 'telefone',
    'assunto', 'relato',
    'logradouro', 'numero', 'referencia', 'bairro', 'endereco_impreciso', 'latitude', 'longitude',
    'situacao', 'area_id', 'equipe_id', 'operacao_id', 'criada_por_id', 'concluida_em',
    'agrupada_em_id', 'agrupada_em',
])]
class Demanda extends Model
{
    // ── Como ela chegou ─────────────────────────────────────────────────────

    /** O sistema recebeu sozinho, sem ninguém digitar. */
    public const ENTRADA_INTEGRACAO = 'integracao';

    /** O coordenador digitou o papel que chegou à mesa. */
    public const ENTRADA_BALCAO = 'balcao';

    // ── De onde veio o fato ─────────────────────────────────────────────────

    public const CANAL_E_SALVADOR = 'e-salvador';

    public const CANAL_SALVADOR_DIGITAL = 'salvador-digital';

    public const CANAL_NOVA_LICENCA = 'nova-licenca';

    public const CANAL_OFICIO = 'oficio';

    /** @var list<string> */
    public const CANAIS = [
        self::CANAL_E_SALVADOR,
        self::CANAL_SALVADOR_DIGITAL,
        self::CANAL_NOVA_LICENCA,
        self::CANAL_OFICIO,
    ];

    // ── Onde ela está ───────────────────────────────────────────────────────

    /**
     * Chegou por INTEGRAÇÃO e ainda não foi entendida.
     *
     * O e-Salvador não entrega casos organizados: entrega o que cada cidadão
     * escreveu. Dez relatos podem ser um fato só, e mandar os dez para a mesa do
     * coordenador como dez casos faria a triagem decidir dez vezes sobre o mesmo
     * ponto — e a equipe ir dez vezes ao mesmo lugar.
     *
     * Por isso a integração cai ANTES da Caixa, numa etapa própria: a
     * PRÉ-TRIAGEM, onde o que é repetição vira um registro só. Só depois disso a
     * demanda passa ao crivo do coordenador (encaminhar ou devolver).
     *
     * ⚠️ O que o coordenador DIGITA (balcão) não passa por aqui: ele já leu o
     * papel e sabe o que é. Fazê-lo consolidar o que acabou de cadastrar seria
     * pedir que ele confira a si mesmo.
     */
    public const EM_PRE_TRIAGEM = 'Em pré-triagem';

    public const RECEBIDA = 'Recebida';

    public const ENCAMINHADA_A_AREA = 'Encaminhada à área';

    public const DIRECIONADA_A_EQUIPE = 'Direcionada à equipe';

    public const EM_OPERACAO = 'Em operação';

    public const EM_CAMPO = 'Em campo';

    public const AGUARDANDO_REGULARIZACAO = 'Aguardando regularização';

    public const RETORNO_VENCIDO = 'Retorno vencido';

    public const CONCLUIDA = 'Concluída';

    public const DEVOLVIDA = 'Devolvida';

    public const ARQUIVADA = 'Arquivada';

    /**
     * Esta denúncia relata o MESMO fato de outra e foi agregada a ela.
     *
     * Continua aberta — o cidadão ainda espera resposta —, mas não espera
     * decisão própria: quem vai a campo é a principal, e o resultado dela
     * responde por esta. É por isso que a situação existe em vez de a agregada
     * simplesmente ficar `Recebida`: na fila de triagem, ela apareceria como
     * trabalho a fazer, e o coordenador triaria dez vezes o mesmo caso.
     */
    public const AGRUPADA = 'Agrupada';

    /**
     * A chave, no trâmite do agrupamento, que guarda a etapa deixada para trás.
     *
     * É constante e não texto solto porque duas pontas a leem: quem agrupa
     * escreve, quem desagrupa lê — e um erro de digitação num dos lados devolveria
     * a denúncia à etapa errada sem nada parecer quebrado.
     */
    public const CAMPO_ETAPA_DEIXADA = 'Etapa em que estava';

    /**
     * O catálogo, na ordem do fluxo.
     *
     * ⚠️ A Caixa de Entrada dizia "Aguardando triagem" para o que aqui é
     * `Recebida`. É o MESMO estado, e o nome que fica é este — dois nomes para
     * um estado é o começo exato da divergência que a lei da fonte única
     * descreve.
     *
     * @var list<string>
     */
    public const SITUACOES = [
        self::EM_PRE_TRIAGEM,
        self::RECEBIDA,
        self::ENCAMINHADA_A_AREA,
        self::DIRECIONADA_A_EQUIPE,
        self::EM_OPERACAO,
        self::EM_CAMPO,
        self::AGUARDANDO_REGULARIZACAO,
        self::RETORNO_VENCIDO,
        self::AGRUPADA,
        self::CONCLUIDA,
        self::DEVOLVIDA,
        self::ARQUIVADA,
    ];

    /** As que ainda esperam alguma decisão — o que "está aberto" significa. */
    /** @var list<string> */
    public const ABERTAS = [
        self::EM_PRE_TRIAGEM,
        self::RECEBIDA,
        self::ENCAMINHADA_A_AREA,
        self::DIRECIONADA_A_EQUIPE,
        self::EM_OPERACAO,
        self::EM_CAMPO,
        self::AGUARDANDO_REGULARIZACAO,
        self::RETORNO_VENCIDO,
        self::AGRUPADA,
    ];

    /** As que encerraram o caso. Prazo não corre mais nelas. */
    /** @var list<string> */
    public const FECHADAS = [self::CONCLUIDA, self::DEVOLVIDA, self::ARQUIVADA];

    protected $table = 'demandas';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recebida_em' => 'datetime',
            'prazo_em' => 'date',
            'concluida_em' => 'datetime',
            'agrupada_em' => 'datetime',
            'anonima' => 'boolean',
            'endereco_impreciso' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** Documento do requerente sempre normalizado — CNPJ novo tem LETRAS. */
    protected function setDocumentoAttribute(?string $valor): void
    {
        $normalizado = Documento::normalizar($valor);
        $this->attributes['documento'] = $normalizado === '' ? null : $normalizado;
    }

    // ── Relações ────────────────────────────────────────────────────────────

    /** @return HasMany<DemandaTramite, $this> */
    public function tramites(): HasMany
    {
        return $this->hasMany(DemandaTramite::class)->orderBy('ordem');
    }

    /**
     * O passo mais recente do trâmite.
     *
     * Existe como método porque `tramites()` já traz `orderBy('ordem')`, e
     * encadear um `latest('ordem')` nele NÃO inverte a ordem — a primeira
     * cláusula vence e o chamador recebe o passo mais ANTIGO achando que pegou o
     * último. Com um só lugar sabendo disso, ninguém mais cai nessa.
     */
    public function ultimoTramite(): ?DemandaTramite
    {
        return $this->tramites()->reorder()->orderByDesc('ordem')->first();
    }

    /** @return HasMany<DemandaAnexo, $this> */
    public function anexos(): HasMany
    {
        return $this->hasMany(DemandaAnexo::class);
    }

    /** @return HasMany<Fiscalizacao, $this> */
    public function fiscalizacoes(): HasMany
    {
        return $this->hasMany(Fiscalizacao::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<Equipe, $this> */
    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    /** @return BelongsTo<Operacao, $this> */
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    /** @return BelongsTo<User, $this> */
    public function criadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por_id');
    }

    // ── O trâmite ───────────────────────────────────────────────────────────

    /**
     * Registra um passo e move a demanda para a situação que ele produziu.
     *
     * É o ÚNICO caminho de mudança de situação. Os `$mudancas` são as colunas de
     * destino que aquele passo definiu (área, equipe, operação) — passadas aqui,
     * e não gravadas antes pelo chamador, para que o destino e o registro do
     * destino nasçam do mesmo ato.
     *
     * @param  array<string, scalar|null>  $campos  rótulo => valor da decisão
     * @param  array<string, mixed>  $mudancas  colunas da demanda que este passo altera
     */
    public function registrar(
        string $acao,
        string $situacao,
        string $papel,
        ?User $autor = null,
        ?string $detalhe = null,
        array $campos = [],
        array $mudancas = [],
        ?Carbon $quando = null,
    ): DemandaTramite {
        $momento = $quando ?? Date::now();

        $this->fill($mudancas);
        $this->situacao = $situacao;

        if (in_array($situacao, self::FECHADAS, true) && $this->concluida_em === null) {
            $this->concluida_em = $momento;
        }

        $this->save();

        return $this->tramites()->create([
            'ordem' => (int) $this->tramites()->max('ordem') + 1,
            'ocorrida_em' => $momento,
            'user_id' => $autor?->id,
            'papel' => $papel,
            'autor' => $autor?->name,
            'acao' => $acao,
            'detalhe' => $detalhe,
            'situacao' => $situacao,
            'campos' => $campos === [] ? null : $campos,
        ]);
    }

    // ── Agrupamento: dez denúncias que são um fato ──────────────────────────

    /**
     * A demanda que leva este caso a campo. Nula quando esta é a principal.
     *
     * @return BelongsTo<Demanda, $this>
     */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'agrupada_em_id');
    }

    /**
     * As denúncias que relatam o mesmo fato e foram agregadas a esta.
     *
     * @return HasMany<Demanda, $this>
     */
    public function agregadas(): HasMany
    {
        return $this->hasMany(self::class, 'agrupada_em_id');
    }

    /** @return HasMany<SugestaoAgrupamento, $this> */
    public function sugestoesDeAgrupamento(): HasMany
    {
        return $this->hasMany(SugestaoAgrupamento::class, 'demanda_id');
    }

    /** Esta demanda é uma agregada (o trabalho dela é feito por outra)? */
    public function agregada(): bool
    {
        return $this->agrupada_em_id !== null;
    }

    /**
     * Agrega esta denúncia ao registro de trabalho de outra.
     *
     * O ato é de GENTE, sempre — a sugestão da máquina não agrupa sozinha. E ele
     * deixa passo nos DOIS lados: na agregada, porque o cidadão dela precisa
     * saber por que o caso dele parou de andar sozinho; na principal, porque
     * quem for a campo precisa saber que está respondendo por mais de um
     * protocolo (e a chefia, que o caso pesa mais do que um relato).
     */
    public function agruparEm(self $principal, User $quem, string $motivo): void
    {
        if ($principal->is($this)) {
            throw new InvalidArgumentException('Uma demanda não pode ser agregada a si mesma.');
        }

        /*
         * Agregar a uma agregada criaria corrente — e a resposta teria de
         * percorrer um caminho que ninguém desenhou. O grupo é sempre raso: uma
         * principal, N agregadas.
         */
        if ($principal->agregada()) {
            throw new InvalidArgumentException(
                'A demanda escolhida já está agregada a outra: agrupe pelo registro de trabalho.',
            );
        }

        if ($this->agregadas()->exists()) {
            throw new InvalidArgumentException(
                'Esta demanda é o registro de trabalho de outras: desagrupe-as antes.',
            );
        }

        $this->registrar(
            acao: 'Agrupada a outro registro',
            situacao: self::AGRUPADA,
            papel: DemandaTramite::PAPEL_COORDENADOR,
            autor: $quem,
            detalhe: $motivo,
            campos: [
                'Registro que leva o caso a campo' => $principal->protocolo,
                'Assunto do registro' => $principal->assunto,
                // A etapa que ela deixou. É o que desagrupar lê para devolvê-la
                // ao lugar de onde saiu — gravado no ato que a tirou de lá, e
                // não numa coluna à parte que um dia discordaria do trâmite.
                self::CAMPO_ETAPA_DEIXADA => $this->situacao,
            ],
            mudancas: ['agrupada_em_id' => $principal->id, 'agrupada_em' => Date::now()],
        );

        $principal->registrar(
            acao: 'Recebeu denúncia agregada',
            situacao: $principal->situacao,
            papel: DemandaTramite::PAPEL_COORDENADOR,
            autor: $quem,
            detalhe: $motivo,
            campos: [
                'Denúncia agregada' => $this->protocolo,
                'Origem dela' => $this->numero_origem ?? '—',
                'Total de denúncias no registro' => (string) ($principal->agregadas()->count() + 1),
            ],
        );
    }

    /**
     * Desfaz a agregação — e é barato de propósito.
     *
     * A associação pode estar errada ("mesas na calçada" pode ser dois
     * estabelecimentos a cinquenta metros um do outro). Nada foi fundido, então
     * desagrupar é devolver a demanda à fila de triagem no estado em que ela
     * chegou. Se o coordenador tivesse de temer a irreversibilidade, deixaria o
     * erro de pé.
     */
    public function desagrupar(User $quem, string $motivo): void
    {
        if (! $this->agregada()) {
            return;
        }

        $principal = $this->principal;

        $this->registrar(
            acao: 'Desagrupada',
            // Volta para a etapa em que ESTAVA quando foi agrupada, e não para
            // um estado fixo. Uma denúncia agrupada durante a pré-triagem ainda
            // não foi entendida por ninguém: devolvê-la direto à Caixa a faria
            // pular a etapa e chegar à mesa do coordenador como caso pronto.
            situacao: $this->situacaoAntesDoAgrupamento(),
            papel: DemandaTramite::PAPEL_COORDENADOR,
            autor: $quem,
            detalhe: $motivo,
            campos: ['Estava agregada a' => $principal?->protocolo ?? '—'],
            mudancas: ['agrupada_em_id' => null, 'agrupada_em' => null],
        );

        $principal?->registrar(
            acao: 'Denúncia agregada foi retirada',
            situacao: $principal->situacao,
            papel: DemandaTramite::PAPEL_COORDENADOR,
            autor: $quem,
            detalhe: $motivo,
            campos: ['Denúncia retirada' => $this->protocolo],
        );
    }

    /**
     * Onde ela estava quando foi agrupada.
     *
     * Lê o trâmite anterior ao do agrupamento — a memória do processo já guarda
     * isso, e guardá-lo uma segunda vez numa coluna daria dois donos à mesma
     * informação. Só as duas etapas de espera são aceitas de volta: nada mais
     * teria sido agrupado, e restaurar cegamente devolveria a denúncia a um
     * estado que ninguém consegue justificar.
     */
    private function situacaoAntesDoAgrupamento(): string
    {
        $anterior = $this->ultimoTramite()?->campos[self::CAMPO_ETAPA_DEIXADA] ?? null;

        return in_array($anterior, [self::EM_PRE_TRIAGEM, self::RECEBIDA], true)
            ? $anterior
            : self::RECEBIDA;
    }

    /**
     * O leque de volta: o que aconteceu na fiscalização responde por todas.
     *
     * Chamado quando a PRINCIPAL se encerra. Cada agregada recebe o mesmo
     * desfecho, em passo próprio, apontando o protocolo que o produziu — é o que
     * permite responder individualmente a cada ouvidoria sem ter ido dez vezes
     * ao mesmo lugar.
     *
     * Idempotente: agregada já encerrada não é tocada de novo.
     *
     * @return int quantas foram respondidas
     */
    public function responderAgregadas(string $desfecho, User $quem, string $situacao = self::CONCLUIDA): int
    {
        $respondidas = 0;

        foreach ($this->agregadas()->get() as $agregada) {
            if (in_array($agregada->situacao, self::FECHADAS, true)) {
                continue;
            }

            $agregada->registrar(
                acao: 'Respondida pela fiscalização do registro agrupado',
                situacao: $situacao,
                papel: DemandaTramite::PAPEL_COORDENADOR,
                autor: $quem,
                detalhe: $desfecho,
                campos: [
                    'Registro que foi a campo' => $this->protocolo,
                    'Desfecho da fiscalização' => $desfecho,
                ],
            );

            $respondidas++;
        }

        return $respondidas;
    }

    // ── Prazo ───────────────────────────────────────────────────────────────

    /**
     * Dias que faltam para o prazo. Negativo = vencida; `null` = sem prazo.
     *
     * Demanda já fechada não conta prazo: o que acabou não vence.
     */
    public function diasDePrazo(?Carbon $hoje = null): ?int
    {
        if ($this->prazo_em === null || in_array($this->situacao, self::FECHADAS, true)) {
            return null;
        }

        return ($hoje ?? Date::now())->startOfDay()->diffInDays($this->prazo_em->startOfDay(), false);
    }

    public function vencida(?Carbon $hoje = null): bool
    {
        $dias = $this->diasDePrazo($hoje);

        return $dias !== null && $dias < 0;
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    /** @param  Builder<static>  $query */
    public function scopeAbertas(Builder $query): void
    {
        $query->whereIn('situacao', self::ABERTAS);
    }

    /** O que chegou por integração — a tela de Denúncias. */
    /** @param  Builder<static>  $query */
    public function scopeDeIntegracao(Builder $query): void
    {
        $query->where('entrada', self::ENTRADA_INTEGRACAO);
    }

    /** O que o coordenador digitou — a Caixa de Entrada. */
    /** @param  Builder<static>  $query */
    public function scopeDeBalcao(Builder $query): void
    {
        $query->where('entrada', self::ENTRADA_BALCAO);
    }

    /**
     * O que chegou por integração e AINDA NÃO FOI ENTENDIDO.
     *
     * É a fila da Pré-Triagem: a leva crua do e-Salvador, antes de alguém dizer
     * quantos fatos distintos ela contém. Ver {@see self::EM_PRE_TRIAGEM}.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEmPreTriagem(Builder $query): void
    {
        $query->where('situacao', self::EM_PRE_TRIAGEM);
    }

    /**
     * O que já passou da pré-triagem — a fila da Caixa.
     *
     * Inclui o que o coordenador digitou (que nunca esteve em pré-triagem) e o
     * que veio por integração e já foi liberado. O crivo de encaminhar ou
     * devolver é o mesmo para os dois: a partir daqui, a origem não muda a
     * decisão.
     *
     * @param  Builder<static>  $query
     */
    public function scopeTriadas(Builder $query): void
    {
        $query->where('situacao', '!=', self::EM_PRE_TRIAGEM);
    }

    /**
     * Só os REGISTROS DE TRABALHO — as agregadas ficam de fora.
     *
     * É o recorte de toda fila operacional (triagem, direcionamento, mapa,
     * contadores do menu): a agregada não espera decisão própria, e mostrá-la
     * faria a fila cobrar dez vezes o mesmo caso.
     *
     * @param  Builder<static>  $query
     */
    public function scopeDeTrabalho(Builder $query): void
    {
        $query->whereNull('agrupada_em_id');
    }
}

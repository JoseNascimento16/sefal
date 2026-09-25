<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\LinkDeSenha;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $login
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $admin
 * @property bool $ativo
 * @property string|null $remember_token
 * @property Carbon|null $senha_definida_em
 * @property bool $is_gerente
 * @property bool $is_admin_usuarios
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Setor> $setores
 */
#[Fillable(['name', 'login', 'email', 'password', 'admin', 'ativo', 'is_gerente', 'is_admin_usuarios'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Onde o sistema guarda AUTORIA — quem registrou, decidiu, encaminhou,
     * respondeu. É a razão de a conta com histórico nunca ser removida de vez:
     * as chaves são "anular ao apagar", e apagar a conta deixaria o trâmite, a
     * vistoria e a decisão sem autor (e a da vistoria nem deixa apagar).
     *
     * Estrutura (líder da equipe, fiscal da equipe, chefe da área) NÃO entra:
     * é lotação de hoje, não registro do que foi feito.
     *
     * @var list<array{0: string, 1: string}>
     */
    public const AUTORIA = [
        ['demanda_tramites', 'user_id'],
        ['demandas', 'criada_por_id'],
        ['demandas', 'respondida_por_id'],
        ['fiscalizacoes', 'fiscal_id'],
        ['fiscalizacoes', 'decidida_por_id'],
        ['fiscalizacao_ciclos', 'aberto_por_id'],
        ['fiscalizacao_ciclos', 'encaminhado_por_id'],
        ['sugestoes_agrupamento', 'decidida_por_id'],
        ['operacoes', 'criada_por_id'],
        ['operacoes', 'cancelada_por_id'],
        ['operacoes', 'coordenador_id'],
        ['permissoes_log', 'user_id'],
    ];

    /**
     * A conta está nascendo SEM senha escolhida — criada pela tela de Usuários,
     * com uma senha aleatória que ninguém conhece. Enquanto for assim, gravar a
     * senha não conta como "senha definida". Não é coluna: vale só para o save
     * que a criou.
     */
    public bool $senhaProvisoria = false;

    /**
     * Toda vez que a senha muda de verdade — pelo link do convite, pelo "Esqueci
     * minha senha", pela troca no perfil, por comando —, fica carimbado que a
     * pessoa tem senha. Um lugar só, no save, para nenhum caminho esquecer.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->isDirty('password') && ! $user->senhaProvisoria) {
                $user->senha_definida_em = Date::now();
            }
        });
    }

    /**
     * Cria a conta com o primeiro acesso PENDENTE: a senha é aleatória e
     * descartada, e quem entra pela primeira vez escolhe a sua pelo convite.
     *
     * @param  array<string, mixed>  $atributos
     */
    public static function criarComPrimeiroAcessoPendente(array $atributos): self
    {
        $user = new self($atributos);
        $user->senhaProvisoria = true;
        $user->password = Str::random(64);
        $user->senha_definida_em = null;
        $user->save();
        $user->senhaProvisoria = false;

        return $user;
    }

    /**
     * O e-mail do "Esqueci minha senha", em português — o padrão do framework
     * chegava em inglês. O convite de primeiro acesso usa a mesma notificação,
     * no outro tom ({@see LinkDeSenha}).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new LinkDeSenha($token, LinkDeSenha::REDEFINICAO));
    }

    /** A pessoa já escolheu a própria senha? */
    public function senhaDefinida(): bool
    {
        return $this->senha_definida_em !== null;
    }

    /**
     * A conta aparece em algum registro do que foi feito — trâmite, vistoria,
     * decisão, operação, concessão de acesso?
     */
    public function temHistorico(): bool
    {
        foreach (self::AUTORIA as [$tabela, $coluna]) {
            if (DB::table($tabela)->where($coluna, $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * A matrícula é sempre guardada em minúsculo e sem espaço nas pontas.
     *
     * É a normalização na ESCRITA que sustenta o login sem caixa: com ela,
     * 'ADMIN' e 'admin' disputam a MESMA linha e a chave única faz o seu papel.
     * Sem ela, a unicidade da coluna (que, tanto no SQLite quanto no Oracle, é
     * sensível à caixa) deixaria as duas contas coexistirem — e a busca do login
     * escolheria uma delas na sorte.
     *
     * @return Attribute<string, string>
     */
    protected function login(): Attribute
    {
        return Attribute::set(fn (string $valor) => self::normalizarMatricula($valor));
    }

    /**
     * A forma canônica de uma matrícula — use ao gravar ou ao procurar.
     */
    public static function normalizarMatricula(string $matricula): string
    {
        return mb_strtolower(trim($matricula));
    }

    /**
     * Acha o usuário pela matrícula, sem se importar com a caixa digitada.
     *
     * O `lower()` na leitura é redundante (a escrita já normaliza) e fica de
     * propósito: cobre qualquer linha que tenha sido gravada por fora do model.
     */
    public static function porMatricula(string $matricula): ?self
    {
        return static::query()
            ->whereRaw('lower(login) = ?', [self::normalizarMatricula($matricula)])
            ->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'admin' => 'boolean',
            'ativo' => 'boolean',
            'senha_definida_em' => 'datetime',
            'is_gerente' => 'boolean',
            'is_admin_usuarios' => 'boolean',
        ];
    }

    /**
     * Setores (perfis de acesso) a que o usuário pertence.
     *
     * @return BelongsToMany<Setor, $this, UserSetor, 'pivot'>
     */
    public function setores(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'user_setores', 'user_id', 'setor_id')
            ->using(UserSetor::class)
            ->withTimestamps();
    }

    /**
     * As equipes que ele LIDERA (é o encarregado com conta — `equipes.lider_id`).
     *
     * @return HasMany<Equipe, $this>
     */
    public function equipesQueLidera(): HasMany
    {
        return $this->hasMany(Equipe::class, 'lider_id');
    }

    /**
     * As equipes em que ele está como FISCAL.
     *
     * @return BelongsToMany<Equipe, $this>
     */
    public function equipesComoFiscal(): BelongsToMany
    {
        return $this->belongsToMany(Equipe::class, 'equipe_fiscais')->withTimestamps();
    }

    /**
     * Administrador enxerga tudo. São duas portas para o mesmo papel: a flag
     * `admin` na conta e o vínculo com o setor `administrador`. Basta uma.
     */
    public function ehAdmin(): bool
    {
        return $this->admin || $this->setores->contains('slug', 'administrador');
    }
}

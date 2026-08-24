<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Setor> $setores
 */
#[Fillable(['name', 'login', 'email', 'password', 'admin', 'ativo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
     * Administrador enxerga tudo. São duas portas para o mesmo papel: a flag
     * `admin` na conta e o vínculo com o setor `administrador`. Basta uma.
     */
    public function ehAdmin(): bool
    {
        return $this->admin || $this->setores->contains('slug', 'administrador');
    }
}

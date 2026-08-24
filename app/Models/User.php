<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $login
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $admin
 * @property bool $ativo
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Setor> $setores
 */
#[Fillable(['name', 'login', 'email', 'password', 'admin', 'ativo'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'admin' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Setores (perfis de acesso) a que o usuário pertence.
     *
     * @return BelongsToMany<Setor, $this>
     */
    public function setores(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'user_setores', 'user_id', 'setor_id')->withTimestamps();
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

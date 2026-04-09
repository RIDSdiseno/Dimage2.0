<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    use HasRoles { hasRole as traitHasRole; hasAnyRole as traitHasAnyRole; }

    protected $table = 'users';

    protected $fillable = [
        'name', 'mail', 'password', 'photo', 'username', 'telephone', 'type_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    // ── type_id → role name mapping (legacy) ─────────────────────────────
    const TYPE_ROLES = [
        1  => 'admin',
        2  => 'secretaria',
        3  => 'holding',
        4  => 'clinica',
        5  => 'radiologo',
        6  => 'odontologo',
        7  => 'contralor',
        11 => 'tecnico',
    ];

    /** Returns the role name derived from type_id (null if unknown). */
    public function typeRole(): ?string
    {
        return self::TYPE_ROLES[(int) $this->type_id] ?? null;
    }

    /**
     * Override Spatie's hasRole to also check legacy type_id.
     */
    public function hasRole($roles, string $guard = null): bool
    {
        if ($this->traitHasRole($roles, $guard)) {
            return true;
        }

        $typeRole = $this->typeRole();
        if ($typeRole === null) {
            return false;
        }

        $check = is_array($roles) ? $roles : [$roles];
        return in_array($typeRole, $check, true);
    }

    /**
     * Override Spatie's hasAnyRole to also check legacy type_id.
     */
    public function hasAnyRole($roles): bool
    {
        if ($this->traitHasAnyRole($roles)) {
            return true;
        }

        $typeRole = $this->typeRole();
        if ($typeRole === null) {
            return false;
        }

        $check = is_array($roles) ? $roles : [$roles];
        return in_array($typeRole, $check, true);
    }

    // ── Mail / Email alias (legacy column is 'mail') ──────────────────────
    public function getEmailAttribute(): ?string
    {
        return $this->mail;
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['mail'] = $value;
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->mail;
    }

    // ── Relationships ─────────────────────────────────────────────────────
    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    /** For users of type CLINICA (4) */
    public function clinic()
    {
        return $this->hasOne(Clinic::class);
    }

    /** For users of type HOLDING (3) */
    public function holding()
    {
        return $this->hasOne(Holding::class);
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'fonction'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const FONCTIONS_CEI = ['PR_CEI', 'VP_CEI', 'R_CEI'];

    public const FONCTIONS_BE = ['PR_BE', 'S_BE', 'M_BE'];

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
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isCei(): bool
    {
        return $this->isSuperAdmin() || in_array($this->fonction, self::FONCTIONS_CEI, true);
    }

    public function isBe(): bool
    {
        return $this->isSuperAdmin() || in_array($this->fonction, self::FONCTIONS_BE, true);
    }
}

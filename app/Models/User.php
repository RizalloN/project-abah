<?php

namespace App\Models;

use App\Traits\IdReusable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, IdReusable, Notifiable;

    public const SUPERADMIN_PN = '90179583';

    protected $fillable = [
        'name',
        'pn',
        'role',
        'branch_scope',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->isSuperAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin' && $this->pn === self::SUPERADMIN_PN;
    }

    public function canManageUser(self $target): bool
    {
        if ($this->isSuperAdmin()) {
            return $target->isSuperAdmin() || in_array($target->role, ['admin', 'user'], true);
        }

        return $this->isAdmin()
            && $target->role === 'user'
            && $target->pn !== self::SUPERADMIN_PN;
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }
}

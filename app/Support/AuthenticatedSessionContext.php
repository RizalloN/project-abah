<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

final class AuthenticatedSessionContext
{
    public const KEY = 'authenticated_user_context';

    public static function fingerprint(User $user): string
    {
        return hash('sha256', serialize([
            (string) $user->getAuthIdentifier(),
            (string) $user->pn,
            (string) $user->role,
            (string) $user->branch_scope,
            $user->getAuthPassword(),
            $user->getRawOriginal('created_at'),
        ]));
    }

    public static function store(Session $session, User $user): void
    {
        $session->put(self::KEY, self::fingerprint($user));
    }
}

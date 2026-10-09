<?php

namespace App\Support;

use Illuminate\Auth\GenericUser;

class MasterAuthUser extends GenericUser
{
    public object $role;

    public function __construct(?string $login = null)
    {
        $login = trim((string) $login);
        if ($login === '') {
            $login = 'MASTER';
        }

        parent::__construct([
            'id' => 'master-auth-user',
            'Login' => $login,
            'IsActive' => 1,
            'IdRole' => null,
            'remember_token' => null,
        ]);

        $this->role = (object) [
            'NameRole' => 'Администратор',
        ];
    }
}


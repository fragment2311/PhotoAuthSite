<?php

namespace App\Support;

use Illuminate\Auth\GenericUser;

class MasterAuthUser extends GenericUser
{
    public object $role;

    public function __construct()
    {
        parent::__construct([
            'id' => 'master-auth-user',
            'Login' => 'MASTER',
            'IsActive' => 1,
            'IdRole' => null,
        ]);

        $this->role = (object) [
            'NameRole' => 'Администратор',
        ];
    }
}


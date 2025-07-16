<?php

namespace App\Services;

use Nette\Database\Explorer;

class DatabaseConsistencyChecker
{
    protected $explorer;

    public function __construct(private \Nette\Database\Explorer $database) 
    {
        $this->explorer = $database;
    }

    public function checkRoles(): void
    {
        $roleTable = $this->explorer->table('role');
        if ($roleTable->count('*') > 0) 
        {
            return;
        }

        // Insert roles and keep references for parent assignment
        $roles = [
            'guest' => null,
            'unapprovedUser' => null,
            'user' => null,
            'politician' => null,
            'moderator' => null,
            'admin' => null,
            'superadmin' => null,
        ];

        // Insert guest first (no parent)
        $roles['guest'] = $roleTable->insert([
            'role_name' => 'guest',
            'role_parental_role_id' => null,
        ])->getPrimary();

        // unapprovedUser -> parent guest
        $roles['unapprovedUser'] = $roleTable->insert([
            'role_name' => 'unapprovedUser',
            'role_parental_role_id' => $roles['guest'],
        ])->getPrimary();

        // user, politician, moderator -> parent guest
        foreach (['user', 'politician', 'moderator'] as $role) {
            $roles[$role] = $roleTable->insert([
                'role_name' => $role,
                'role_parental_role_id' => $roles['unapprovedUser'],
            ])->getPrimary();
        }

        // admin -> parent moderator
        $roles['admin'] = $roleTable->insert([
            'role_name' => 'admin',
            'role_parental_role_id' => $roles['moderator'],
        ])->getPrimary();

        // superadmin -> parent admin
        $roles['superadmin'] = $roleTable->insert([
            'role_name' => 'superadmin',
            'role_parental_role_id' => $roles['admin'],
        ])->getPrimary();
    }
}

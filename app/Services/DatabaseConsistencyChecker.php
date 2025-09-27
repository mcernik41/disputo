<?php

namespace App\Services;

use Nette\Database\Explorer;
use App\Services\Seed\RegionSeeder;
use App\Services\Seed\AreaSeeder;

class DatabaseConsistencyChecker
{
    protected $explorer;

    public function __construct(private \Nette\Database\Explorer $database) 
    {
        $this->explorer = $database;
    }

    /**
     * Zajistí existenci systémového uživatele s primárním klíčem -1.
     * Tento uživatel je používán jako "vlastník" systémově seedovaných záznamů.
     * Pokud uživatel s user_id = -1 neexistuje, je vložen. Role je nastavena na superadmin (pokud existuje),
     * jinak admin, jinak první dostupná role.
     */
    public function checkSystemUser(): void
    {
        // Už existuje?
        $system = $this->explorer->table('user')->where('user_id', -1)->fetch();
        if ($system) {
            return;
        }

        // Zjistit vhodnou roli
        $roleId = null;
        $super = $this->explorer->table('role')->where('role_name', 'superadmin')->fetch();
        if ($super) {
            $roleId = $super->getPrimary();
        } else {
            $admin = $this->explorer->table('role')->where('role_name', 'admin')->fetch();
            if ($admin) {
                $roleId = $admin->getPrimary();
            } else {
                // fallback – vezmeme libovolnou první roli (např. guest), aby FK nebyl null pokud to schéma vyžaduje
                $any = $this->explorer->table('role')->limit(1)->fetch();
                $roleId = $any?->getPrimary();
            }
        }

        // Základní bezpečné heslo – nikdy se nepoužívá k interaktivnímu přihlášení
        $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $this->explorer->table('user')->insert([
            'user_id' => -1,               // explicitní ID
            'user_name' => 'System',
            'user_surname' => 'Account',
            'user_username' => 'system',
            'user_email' => 'system@local',
            'user_password' => $randomPassword,
            'user_role_id' => $roleId,
            'user_deleted' => 0,
            'user_politicalAccount' => 0,
            'user_publicIdentity' => 0,
            'user_authtoken' => null,
        ]);
    }

    /**
     * Checks the consistency of roles in the database.
     * This method is called by the BasePresenter to ensure that all required roles exist.
     *
     * @return void
     */
    public function checkRoles(): void
    {
        $roleTable = $this->explorer->table('role');

        // Definice rolí a jejich rodičů
        $roleDefinitions = [
            'guest' => null,
            'unapprovedUser' => 'guest',
            'user' => 'unapprovedUser',
            'politician' => 'user',
            'moderator' => 'user',
            'admin' => 'moderator',
            'superadmin' => 'admin',
        ];

        // Získání existujících rolí z DB
        $existingRoles = [];
        foreach ($roleTable as $row) {
            $existingRoles[$row->role_name] = $row->getPrimary();
        }

        // Vkládání chybějících rolí s ohledem na závislosti
        $roles = $existingRoles;
        $maxIterations = count($roleDefinitions);
        $i = 0;
        while (count($roles) < count($roleDefinitions) && $i < $maxIterations) {
            foreach ($roleDefinitions as $role => $parent) {
                if (isset($roles[$role])) continue;
                if ($parent === null || isset($roles[$parent])) {
                    $roles[$role] = $roleTable->insert([
                        'role_name' => $role,
                        'role_parentalRole_id' => $parent ? $roles[$parent] : null,
                    ])->getPrimary();
                }
            }
            $i++;
        }
    }

    /**
     * Checks the consistency of resources in the database.
     * This method is called by the BasePresenter to ensure that all required resources exist.
     *
     * @return void
     */
    public function checkResources(): void
    {
        $resourceTable = $this->explorer->table('resource');

        // Nově doplněné resource 'region' a 'area' pro hierarchické entity.
        $resourceDefinitions = [
            'politicalParty' => null,
            'userRequest' => null,
            'region' => null,
            'area' => null,
        ];

        // Získání existujících rolí z DB
        $existingResources = [];
        foreach ($resourceTable as $row) {
            $existingResources[$row->resource_name] = $row->getPrimary();
        }

        // Vkládání chybějících rolí s ohledem na závislosti
        $resources = $existingResources;
        $maxIterations = count($resourceDefinitions);
        $i = 0;
        while (count($resources) < count($resourceDefinitions) && $i < $maxIterations) {
            foreach ($resourceDefinitions as $resource => $parent) {
                if (isset($resources[$resource])) continue;
                if ($parent === null || isset($resources[$parent])) {
                    $resources[$resource] = $resourceTable->insert([
                        'resource_name' => $resource,
                        'resource_parentalResource_id' => $parent ? $resources[$parent] : null,
                    ])->getPrimary();
                }
            }
            $i++;
        }

        // Po zajištění resources můžeme spustit seedery (pokud jsou tabulky prázdné)
        (new RegionSeeder($this->explorer))->seed();
        (new AreaSeeder($this->explorer))->seed();
    }

    /**
     * Nastaví základní vztahy mezi rolemi a zdroji do tabulky resourceAccess.
     * Pokud záznam již existuje, nebude znovu vytvořen.
     *
     * @return void
     */
    public function checkResourceAccess(): void
    {
        $roleTable = $this->explorer->table('role');
        $resourceTable = $this->explorer->table('resource');
        $resourceAccessTable = $this->explorer->table('resourceAccess');

        // Mapování rolí a zdrojů podle jména na jejich ID
        $roles = [];
        foreach ($roleTable as $row) {
            $roles[$row->role_name] = $row->getPrimary();
        }
        $resources = [];
        foreach ($resourceTable as $row) {
            $resources[$row->resource_name] = $row->getPrimary();
        }

        // Definice práv – doplněna práva pro 'region' a 'area'.
        $accessDefinitions = [
            // role => [resource => [práva]]
            'guest' => [
                'politicalParty' => ['view'],
                'region' => ['view'], // hosté mohou prohlížet strom území
                'area' => ['view'],   // a také okruhů
            ],
            'user' => [
                'politicalParty' => ['add'],
                'userRequest' => ['add'],
                'region' => ['add'], // přidávání nových uzlů území
                'area' => ['add'],   // přidávání nových uzlů okruhů
            ],
            'admin' => [
                'politicalParty' => ['approve'],
                'userRequest' => ['view', 'approve', 'delete'],
                'region' => ['approve'], // potenciálně budoucí moderace (placeholder)
                'area' => ['approve'],  // dtto
            ],
        ];

        // Vkládání chybějících záznamů
        foreach ($accessDefinitions as $roleName => $resourceRights) {
            if (!isset($roles[$roleName])) continue;

            foreach ($resourceRights as $resourceName => $rights) {
                if (!isset($resources[$resourceName])) continue;

                foreach ($rights as $right) {
                    // Kontrola, zda záznam již existuje
                    $count = $this->explorer->table('resourceAccess')
                        ->where('role_role_id', $roles[$roleName])
                        ->where('resource_resource_id', $resources[$resourceName])
                        ->where('resourceAccess_right', $right)
                        ->count('*');
                    if ($count == 0) {
                        $comment = sprintf(
                            'Role "%s" can "%s" on resource "%s"',
                            $roleName,
                            $right,
                            $resourceName
                        );
                        $resourceAccessTable->insert([
                            'role_role_id' => $roles[$roleName],
                            'resource_resource_id' => $resources[$resourceName],
                            'resourceAccess_right' => $right,
                            'resourceAccess_assertion' => null,
                            'resourceAccess_comment' => $comment,
                        ]);
                    }
                }
            }
        }
    }
}

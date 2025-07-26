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

        // Definice rolí a jejich rodičů
        $resourceDefinitions = [
            'politicalParty' => null,
            'userRequest' => null
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

        // Definice práv (příkladová práva, upravte dle potřeby)
        $accessDefinitions = [
            // role => [resource => [práva]]
            'guest' => [
                'politicalParty' => ['view'],
            ],
            'user' => [
                'politicalParty' => ['add'],
                'userRequest' => ['add'],
            ],
            'admin' => [
                'politicalParty' => ['approve'],
                'userRequest' => ['view', 'approve', 'delete'],
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

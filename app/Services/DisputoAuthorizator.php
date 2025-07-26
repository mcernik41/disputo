<?php

declare(strict_types=1);

namespace App\Services;

use Nette\Security\Permission;
use Nette\Database\Explorer;
use Nette\Security\Authorizator;


final class DisputoAuthorizator implements Authorizator
{
    private Permission $acl;

    public function __construct(
        private Explorer $database,
    ) {
        $this->acl = new Permission();
        $this->initializeAcl($this->acl);
    }
    /**
     * Povinná metoda Authorizatoru - dotazuje se na práva přes interní ACL.
     */
    public function isAllowed($role, $resource, $privilege): bool
    {
        return $this->acl->isAllowed($role, $resource, $privilege);
    }

    /**
     * Initializes the ACL with roles, resources, and rights from the database.
     * @param Permission $acl
     * @return void
     */
    public function initializeAcl(Permission $acl): void
    {
        if (count($acl->getRoles()) === 0) {
            $this->loadRolesToAcl($acl);
            $this->loadResourcesToAcl($acl);
            $this->loadRoleResourceAccessToAcl($acl);
        }
    }

    /**
     * Loads all roles from the database and adds them to the given ACL object.
     * @param Permission $acl
     * @return void
     */
    public function loadRolesToAcl(Permission $acl): void
    {
        $roles = $this->database->table('role')->order('role_id ASC');
        foreach ($roles as $row) {
            $roleName = $row->role_name;
            $parentId = $row->role_parentalRole_id;
            $parentName = null;
            if ($parentId) {
                $parentRow = $this->database->table('role')->where('role_id', $parentId)->fetch();
                if ($parentRow) {
                    $parentName = $parentRow->role_name;
                }
            }
            if (!$acl->hasRole($roleName)) {
                $acl->addRole($roleName, $parentName);
            }
        }
    }

    /**
     * Loads all resources from the database and adds them to the given ACL object.
     * @param Permission $acl
     * @return void
     */
    public function loadResourcesToAcl(Permission $acl): void
    {
        $resources = $this->database->table('resource')->order('resource_id ASC');
        foreach ($resources as $row) {
            $resourceName = $row->resource_name;
            $parentId = $row->resource_parentalResource_id;
            $parentName = null;
            if ($parentId) {
                $parentRow = $this->database->table('resources')->where('resource_id', $parentId)->fetch();
                if ($parentRow) {
                    $parentName = $parentRow->resource_name;
                }
            }
            if (!$acl->hasResource($resourceName)) {
                $acl->addResource($resourceName, $parentName);
            }
        }
    }

    /**
     * Loads all role-resource access rights from the database and adds them to the given ACL object.
     * @param Permission $acl
     * @return void
     */
    public function loadRoleResourceAccessToAcl(Permission $acl): void
    {
        $accesses = $this->database->table('resourceAccess');
        foreach ($accesses as $access) {
            $roleName = $access->role->role_name;
            $resourceName = $access->resource->resource_name;
            $right = $access->resourceAccess_right;
            $assertion = $access->resourceAccess_assertion;
            if ($right) {
                $acl->allow($roleName, $resourceName, $right);
            } else {
                $acl->allow($roleName, $resourceName, null, $assertion);
            }
        }
    }
}

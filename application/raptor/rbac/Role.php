<?php

namespace Raptor\RBAC;

class Role
{
    public array $permissions = [];

    public function fetchPermissions(\PDO $pdo, int $role_id)
    {
        $permissions_table = (new Permissions($pdo))->getName();
        $role_perm_table = (new RolePermission($pdo))->getName();
        $sql =
            "SELECT t2.name, t2.alias FROM $role_perm_table t1 " .
            "INNER JOIN $permissions_table t2 ON t1.permission_id=t2.id " .
            'WHERE t1.role_id=:role_id';
        $pdo_stmt = $pdo->prepare($sql);
        if ($pdo_stmt->execute([':role_id' => $role_id])) {
            while ($row = $pdo_stmt->fetch()) {
                $this->permissions["{$row['alias']}_{$row['name']}"] = true;
            }
        }
        
        return $this;
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions[$permissionName] ?? false;
    }
}

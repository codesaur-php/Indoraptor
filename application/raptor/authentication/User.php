<?php

namespace Raptor\Authentication;

class User
{
    public array $profile;
    public array $organization;
    
    private readonly array $_rbac;
    
    public function __construct(array $user, array $organization, array $rbac)
    {
        $this->profile = $user;
        $this->organization = $organization;
        $this->_rbac = $rbac;
    }

    public function is(string $role): bool
    {
        if (isset($this->_rbac['system_coder'])) {
            return true;
        }
        
        return isset($this->_rbac[$role]);
    }

    public function can(string $permission, ?string $role = null): bool
    {
        if (isset($this->_rbac['system_coder'])) {
            return true;
        }
        
        if (!empty($role)) {
            return $this->_rbac[$role][$permission] ?? false;
        }
        
        foreach ($this->_rbac as $role) {
            if (isset($role[$permission])) {
                return $role[$permission] == true;
            }
        }
        
        return false;
    }
}

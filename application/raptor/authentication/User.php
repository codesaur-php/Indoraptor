<?php

namespace Raptor\Authentication;

class User
{
    private readonly array $_user;
    private readonly array $_organizations;
    private readonly array $_rbac;
    private readonly string $_token;
    
    public function __construct(array $user, array $organizations, array $rbac, string $token)
    {
        $this->_user = $user;
        $this->_organizations = $organizations;
        $this->_rbac = $rbac;
        $this->_token = $token;
        
        \putenv("CODESAUR_USER_ID={$this->_user['id']}");
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

    public function getProfile(): array
    {
        return $this->_user;
    }

    public function getAlias(): string
    {
        return $this->getOrganization()['alias'] ?? '';
    }
    
    public function getOrganizations(): array
    {
        return $this->_organizations;
    }

    public function getOrganization(): array
    {
        return $this->_organizations[0] ?? [];
    }
    
    public function getToken(): string
    {
        return $this->_token;
    }
}

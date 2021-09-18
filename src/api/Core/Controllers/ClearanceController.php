<?php

namespace Casanova\Manifold\Api\Core\Controllers;

use Casanova\Manifold\Api\Core\Role\Models\Role;
use Exception;

use Casanova\Manifold\Api\Core\Controllers\CacheController as Cache;

/**
 * Class responsible for authorization (clearance) access based on module and user role
 */
class ClearanceController
{
    /**
     * All existant roles
     *
     * @access private
     * @var array
     */
    private $roles;
    
    /**
     * If roles are initiated
     *
     * @var boolean
     */
    private $initiated = false;

    /**
     * Relational array with method name to bit position
     *
     * @var array
     */
    private $intervalMap = [ 'create' => [0, 4], 'read' => [4, 8], 'update' => [8, 12], 'delete' => [12, 16] ];

    /**
     * Relational array with bit string-number to string name and vice versa
     *
     * @var array
     */
    private $clearanceInterval = [ '01' => 'DENY', 'DENY' => '01', '10' => 'UNALLOW', 'UNALLOW' => '10', '11' => 'ALLOW', 'ALLOW' => '11' ];

    /**
     * Set ClearanceController::$roles to the sent param
     *
     * @uses ClearanceController::$roles set to the sent param
     * @param array $roles
     * @return void
     */
    public function init($roles){
        if (isset($roles)){
            $this->roles = $roles;
            $this->initiated = true;
        }
    }

    /**
     * Return boolean if the user has authorization to access the route or resource
     * inner function comments explains it better
     *
     * @param array $user
     * @param string $CRUDmethod
     * @param string $routePath
     * @param string $moduleAlias
     * @param array $resource
     * @param string $recursiveRoleParameter
     * @return boolean
     */
    public function can($user, $CRUDmethod, $routePath, $moduleAlias, $resource = null, $recursiveRoleParameter = null){
        $resourceOwnerKey = 'id';
        $isUserResourceOwner = false;
        $permissionBIN = '10';
        $clearanceStr = $this->clearanceInterval['UNALLOW']; // Starts unallowed

        // $tokenUserId = array_key_exists($resourceOwnerKey, $user)? $user[$resourceOwnerKey] : -1; 
        // $ifResourceOwnerKeyAndUserIdMatch = array_key_exists($resourceOwnerKey, $user)? $resource[$resourceOwnerKey] === $tokenUserId : false;
        $userType = gettype($user);
        if ($userType === 'object'){
            $ifResourceEmailAndUserEmailMatch = property_exists($user, 'email') && isset($resource['email'])? $resource['email'] === $user['email'] : false;
        } else if ($userType === 'array'){
            $ifResourceEmailAndUserEmailMatch = isset($user['email']) && isset($resource['email'])? $resource['email'] === $user['email'] : false;
        }

        // if target resource is owned by user trying to access it
        // if ($resource && $resource[$resourceOwnerKey] && ($ifResourceOwnerKeyAndUserIdMatch || $ifResourceEmailAndUserEmailMatch)){
        if ($resource && $resource[$resourceOwnerKey] && $ifResourceEmailAndUserEmailMatch){
            $isUserResourceOwner = true;
        }
        
        // if roles weren't yet set - mainly for testing reasons
        if (!isset($this->roles)){
            $this->roles = Cache::getRoles();
        }

        // if recursiveRoleParameter is set then use it instead
        $userPermissionRole = $recursiveRoleParameter ?? $user['permission_role'];
        $roleFilter = array_filter($this->roles, function($role) use ($userPermissionRole) {  return $role['name'] === $userPermissionRole; });

        // if role doesnt exist
        if (sizeof($roleFilter) !== 1){
            throw new Exception("O grupo de permissões ${userPermissionRole} não está registrado ");
        }

        $roleInfo = array_shift($roleFilter);
        $rolePermissions = (array) $roleInfo['permissions'];
        $formatedModuleAlias = strtolower($moduleAlias);
        $modulePermissions = isset($rolePermissions[$formatedModuleAlias])? (array) $rolePermissions[$formatedModuleAlias] : null;


        // if role doesn't have any permission about the module
        if (!$modulePermissions){
            return false;
        }

        $permissionKeysArray = array_keys($modulePermissions);
        // if $permissionKeysArray has any key with default permission and permissionKeysArray is === 2
        // $isDefaultPermissionStrategy = $permissionKeysArray.every(routePath => {
        //     return routePath === 'defaultPermission' || routePath === 'allowedPaths'
        // }) && permissionKeysArray.length >= 2

        $isDefaultPermissionStrategy = in_array('defaultPermission', $permissionKeysArray)
                                    && in_array('allowedPaths', $permissionKeysArray)
                                    && sizeof($permissionKeysArray) === 2;

        if ($isDefaultPermissionStrategy){
            $isPathAllowed = array_filter($modulePermissions['allowedPaths'], function($allowedPath) use ($routePath){
                return $allowedPath === $routePath;
            });
            // if route path not added on allowedPaths
            // security reasons
            if (sizeof($isPathAllowed) < 1){
                return false;
            }

            $permissionBIN = $modulePermissions['defaultPermission'];
        } else {
            $permissionBIN = $modulePermissions[$routePath];
        }

        try {
            $methodInterval = $this->intervalMap[strtolower($CRUDmethod)];
        } catch (\Throwable $th) {
            throw new Exception("O método de acesso ${CRUDmethod} não está listado");
        }

        if (gettype($permissionBIN) !== 'string'){
            throw new Exception("A permissão do grupo de permissões ${userPermissionRole} precisa ser do tipo STRING")      ;     
        }

        // if permission has less than 16 chars it's a invalid one
        // return false not to reveal inside logic
        if (strlen($permissionBIN) !== 16){
            return false;   
        }

        $methodBits = substr($permissionBIN, $methodInterval[0], 4);
        // if resource is set and user owns it, gets second half of string else first half (ALL / OWN)
        $clearanceBits = substr($methodBits, ($isUserResourceOwner? 2: 0), 2);
        $clearanceStr = $this->clearanceInterval[$clearanceBits];

        // return true if clearance level equals to 'ALLOW'
        if ($clearanceStr === 'ALLOW') {
            return true;
        } else {
            // if clearance level equals to DENY and it's on recursion
            // it means a lower level permission have a deny that takes precedent          
            if ($clearanceStr === 'DENY' && $recursiveRoleParameter !== null){
                return false;
            }// if clearance level equals to DENY or UNALLOW and role doesn't inherit from any other role
            else if (($clearanceStr === 'DENY' || $clearanceStr === 'UNALLOW')
                    && (!$roleInfo['inherits'] || (is_countable($roleInfo['inherits'])? sizeof($roleInfo['inherits']) : 0) < 1)) {                
                return false;
            } else {
                // if clearalnce level equals to UNALLOW && role inherits from any other role
                // recursively looks for any allowance
                // stops the recursive proccess once found any true value
                // works properly with precedence in upper level roles
                // ">" = inherits from
                // e.g. vip_client > client > user = vip_client clearance levels takes precedent
                return $this->can($user, $CRUDmethod, $routePath, $moduleAlias, $resource, $roleInfo['inherits']);
            }
        }
    }
    
}

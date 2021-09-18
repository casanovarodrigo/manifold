<?php

namespace Casanova\Manifold\Api\Core\Role\Models;

use Illuminate\Database\Eloquent\Model;
use Casanova\Manifold\Api\Core\Controllers\ModuleController;
use Illuminate\Support\Facades\DB;

use Exception;

class Role extends Model
{
    protected $fillable = [
        'name',
        'inherit_id',
        'permissions'
    ];

    protected $hidden = [ 'inherit_id' ];

    protected $casts = [
        'permissions' => 'array'
    ];

    /**
     * Return related users in the database
     *
     * @return void
     */
    function user(){
        return $this->hasMany('Casanova\Manifold\Api\Core\User\Models\User', 'role_id', 'id');
    }

    /**
     * Return related role that inherits from in the database
     *
     * @return void
     */
    function inherit(){
        return $this->belongsTo('Casanova\Manifold\Api\Core\Role\Models\Role', 'inherit_id', 'id');
    }

    /**
     * Check if permission are valid
     *
     * @param array $roleToValidate
     * @throws Exception if role permissions are invalid
     * @return void
     */
    protected function arePermissionsValid($roleToValidate){
        // for every module permission check if isDefaultPermissionStrategy [modulePermission has defaultPermission && allowedPaths]
        // or permission for every single route
        if ($roleToValidate['permissions']){
            foreach ($roleToValidate['permissions'] as $moduleAlias => $routePath) {
                $isDefaultStrategy = false;
                $isRouteStrategy = false;
                $arrKeys = array_keys($routePath);
                $allowedPathExists = in_array('allowedPaths', $arrKeys);
                $defaultPermissionExists = in_array('defaultPermission', $arrKeys);

                if ($defaultPermissionExists && sizeof($routePath) === 1){
                    $isDefaultStrategy = true;
                }

                if (!$defaultPermissionExists && !$allowedPathExists && sizeof($routePath) >= 1){
                    $isRouteStrategy = true;
                }

                if (!($isDefaultStrategy xor $isRouteStrategy)){
                    $isDefaultStrategy = false;
                    $isRouteStrategy = false;
                    throw new Exception("ambiguous_route_to_permission_strategy__Estratégia de permissão da rota " . $moduleAlias ." é ambígua. Nenhuma estratégia ou com ambas características defaultPermission||allowedPaths e única por rota.");
                }

            }
        }
    }

    /**
     * Return one role by ID
     *
     * @param int $roleId
     * @return array single role
     */
    function getOne($roleId) {
        $roleExist = DB::table('roles as t1')
            ->select('t1.*')
            ->addSelect('t2.name as inherits')
            ->leftJoin('roles as t2', 't2.id', '=', 't1.inherit_id')
            ->where('t1.id', '=', $roleId);
        
        $response = null;
        if ($roleExist->first()){
            $response = (array) $roleExist->first();
            $response['permissions'] = json_decode($response['permissions']);
        }
        return $response;
    }
    
    /**
     * Return all roles
     *
     * @return array with all roles
     */
    function getAll() {
        $queryCollection = DB::table('roles as t1')
            ->select('t1.*')
            ->addSelect('t2.name as inherits')
            ->leftJoin('roles as t2', 't2.id', '=', 't1.inherit_id')
            ->get()
            ->toArray();

        return array_map(function($item){
            $item->permissions = json_decode($item->permissions);
            return (array) $item;
        }, $queryCollection);
    }

    /**
     * Save the role to the database
     *
     * @param array $roleToCreate
     * @throws Exception if role already exist
     * @return Collection created role
     */
    function store($roleToCreate){
        $this->arePermissionsValid($roleToCreate);  

        // check if role with same name already exists
        $roleExist = $this->where('name', $roleToCreate['name']);
        if ($roleExist->first()){
            throw new Exception("existing_role__Grupo de permissões já registrado");
        }

        $rolePermissions = &$roleToCreate['permissions'];
        $ModuleController = null;
        foreach ($rolePermissions as $modAlias => $modulePermission) {
            $isDefaultStrategy = array_key_exists('defaultPermission', $modulePermission)
                                && sizeof($modulePermission) === 1;

            if ($isDefaultStrategy){
                if (!$ModuleController){
                    $ModuleController = app()->make(ModuleController::class);
                }
                $moduleToHavePermission = $ModuleController->getFromAlias($modAlias);
                $moduleRoutes = $moduleToHavePermission[$modAlias]['routerFiles'];
                $rolePermissions[$modAlias]['allowedPaths'] = array_map(function($route){
                    return $route['path'];
                }, $moduleRoutes);
            }
        }
        
        return $this->create($roleToCreate);
    }
    
    /**
     * Edit and save role to the database
     *
     * @param int $roleId
     * @param array $roleInfoToUpdate
     * @throws Exception if role doesn't exist or the new role name already exist
     * @return Collection edited role
     */
    function edit($roleId, $roleInfoToUpdate){
        // check if role with same name already exist
        $roleExist = $this->where('id', $roleId);
        if (!$roleExist->first()){
            throw new Exception(400, "inexistent_role__Grupo de permissões não registrado");
        }

        $this->arePermissionsValid($roleInfoToUpdate);

        // if changing role name search for uniqueness
        if ($roleExist->first()->name !== $roleInfoToUpdate['name']){
            $futureRoleNameExist = $this->where('name', $roleInfoToUpdate['name']);
            if ($futureRoleNameExist->first()){
                throw new Exception("existing_role__Novo nome do Grupo de permissões já está registrado");
            }  
        }

        $infoToUpdate = array_merge($roleInfoToUpdate, [ "id" => $roleId ]);
        $response = false;
        
        $updatedRole = $roleExist->update($infoToUpdate);
        if ($updatedRole){
            $response = $infoToUpdate;
        }

        return $response;
    }
    
    /**
     * Remove role from database
     *
     * @param int $roleId
     * @throws Exception if role doesn't exist
     * @return void
     */
    function remove($roleId){
        // check if role with same name already exists
        $roleExist = $this->where('id', $roleId);
        if (!$roleExist->first()){
            throw new Exception("inexistent_role__Grupo de permissões não registrado");
        }

        $response = false;
        $updatedRole = $roleExist->delete();

        if ($updatedRole){
            $response = (bool) $updatedRole;
        }

        return $response;
    }

}

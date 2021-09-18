<?php

namespace Casanova\Manifold\Api\Core\Role\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Casanova\Manifold\Api\Core\Role\Models\Role;
use Casanova\Manifold\Api\Core\Controllers\CacheController as Cache;


class RoleController extends BaseController
{
    private $model;
    
    function __construct(Role $roleModel){
        $this->model = $roleModel;
    }

    /**
     * Return all roles in database
     *
     * @return Http\Response
     */
    function getAll(){
        return response()->api('All Roles', $this->model->getAll());
    }
    
    /**
     * Return all available roles that the authenticated user can use to invite a new user
     *
     * @param Request $request
     * @return Http\Response
     */
    function toInvite(Request $request){
        
        $userRoleName = $request->user['permission_role'];
        $roles = Cache::getRoles();
        // dd($roles);

        // super_admin can be created only through system
        // only admin can invite admin
        $filtered = array_filter($roles, function($key) use ($userRoleName){
            if ($key['name'] === 'super_admin'){
                return false;
            } else if ($key['name'] === 'admin' && ($userRoleName !== 'admin' && $userRoleName !== 'super_admin')){
                return false;
            } else {
                return true;
            }

            // it is the same if not added?
            //  else if ($key->name === 'admin' && ($userRoleName === 'admin' || $userRoleName === 'super_admin')){
            //     return true;
            // }
        });
        return response()->api('Roles to invite', $filtered);
    }
    
    /**
     * Return one role by ID
     *
     * @param Request $request
     * @return Http\Response
     */
    function getOne(Request $request){
        $roleId = $request->route('id');
        try {
            $role = $this->model->getOne($roleId);
            return response()->api('Single Role', $role);
        } catch (\Throwable $th) {
            return response()->apiError(400,'Error retrieving Role from database', $th->getMessage());
        }
    }

    /**
     * Save a new role to the database
     *
     * @param Request $request
     * @return Http\Response
     */
    function store(Request $request){
        $roleToCreate = $request->all();
        try {
            $role = $this->model->store($roleToCreate);
            Cache::resetRoles();
            return response()->api('Role created', $role);
        } catch (\Throwable $th) {
            return response()->apiError(400, 'Error saving new Role to database', $th->getMessage());
        }
    }
    
    /**
     * Edit and save a role to the database
     *
     * @param Request $request
     * @return Http\Response
     */
    function edit(Request $request){
        $roleId = $request->route('id');
        $roleInfoToUpdate = $request->all();
        try {
            $role = $this->model->edit($roleId, $roleInfoToUpdate);
            Cache::resetRoles();
            return response()->api('Role updated', $role);
        } catch (\Throwable $th) {
            return response()->apiError(400, 'Error updating Role to database', $th->getMessage());
        }
    }
    
    /**
     * Remove a role from the database
     *
     * @param Request $request
     * @return Http\Response
     */
    function remove(Request $request){
        $roleId = $request->route('id');
        try {
            $role = $this->model->remove($roleId);
            Cache::resetRoles();
            return response()->api('Role removed', $role);
        } catch (\Throwable $th) {
            return response()->apiError(400, 'Error removing Role from database', $th->getMessage());
        }
    }
    
    
}

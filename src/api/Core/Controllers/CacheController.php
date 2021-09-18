<?php

namespace Casanova\Manifold\Api\Core\Controllers;

use Illuminate\Support\Facades\Cache;
use Casanova\Manifold\Api\Core\Role\Models\Role;
use Carbon\Carbon;

use Exception;

/**
 * Class responsible for authorization (clearance) access based on module and user role
 */
class CacheController
{
    /**
     * Indexable variables
     *
     * @var array
     */
    private $indexable = [ 'roles' ];

    /**
     * Expiration time in seconds
     *
     * @var integer
     */
    private static $expiration = [
        "roles" => 18000 
    ];

    /**
     * Gets roles from cache. Get from database if not cached already
     *
     * @return array
     */
    public static function getRoles(){
        try {
            $roles = Cache::remember('roles', self::$expiration['roles'], function () {
                return (new Role)->getAll();
            });
            self::setLastModified('roles');
            return $roles;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Refresh roles in cache from database
     *
     * @return bool
     */
    public static function resetRoles(){
        try {
            $roleModel = (new Role)->getAll();
            Cache::put('roles', $roleModel, self::$expiration['roles']);
            self::setLastModified('roles');
            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
        return false;
    }

    public static function setLastModified($name){
        try {
            $cacheName = 'lastmodified.' . $name;
            $expirationDate = Carbon::now();
            $expirationDate->setTimezone('UTC');
            Cache::forever($cacheName, $expirationDate->timestamp);
            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
        return false;
    }

    public static function getLastModified($name){
        try {
            $cacheName = 'lastmodified.' . $name;
            return Cache::get($cacheName);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Remove key from cache
     *
     * @param string $key
     * @return bool
     */
    public static function removeKey($key){
        try {
            Cache::forget($key);
            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
        return false;
    }


    public static function clearCache(){
        try {
            Cache::flush();
            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
        return false;
    }

}
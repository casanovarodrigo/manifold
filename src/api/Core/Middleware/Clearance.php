<?php

namespace Casanova\Manifold\Api\Core\Middleware;

use Casanova\Manifold\Api\Core\Controllers\ClearanceController;
use Casanova\Manifold\Api\Core\Controllers\ModuleController;
use Casanova\Manifold\Api\Core\Role\Models\Role;
use Closure;
use Exception;

/**
 * Middleware responsible for applying the ClearanceController::can method
 */
class Clearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $CRUDmethod)
    {
        $user = $request->user;
        $path = $request->getPathInfo();
        
        $ModuleController = app()->make(ModuleController::class);
        $afterApi = explode('/api/', $path);
        $routeURI = explode('/', $afterApi[1]);
        $routePath = $routeURI[0];
        $moduleAlias = $ModuleController->getModuleFromPath($routePath);

        if (!$moduleAlias){
            throw new Exception('O módulo relacionado ao route path não existe, contate o desenvolvedor asd');
        }

        $resource = $request->foundResource? $request->foundResource->toArray() : null;

        $ClearanceController = app()->make(ClearanceController::class);

        $canPass = $ClearanceController->can($user, $CRUDmethod, $routePath, $moduleAlias, $resource);
        
        if(!$canPass){
            return response()->apiError(401, "Usuário sem permissões para a operação ${CRUDmethod} no módulo ${moduleAlias}"); 
        } else {
            return $next($request);
        }
        
    }
}

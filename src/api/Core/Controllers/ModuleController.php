<?php

namespace Casanova\Manifold\Api\Core\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;

/**
 * Class responsible to validate, register and deal with modules
 */
class ModuleController
{
    /**
     * list of core modules to use
     * add core modules here
     *
     * @access private
     * @var array
     */
    private $coreModulesList = ['user', 'role'];

    /**
     * List of core resource middlewares
     * add resource middlewares here
     * 
     * @access private
     * @var array
     */
    private $resourceMiddlewareList = [ 'User' ];

    /**
     * All validated modules
     *
     * @access private
     * @var array
     */
    private $allValidated = [];

    /**
     * All registered Modules
     *
     * @access private
     * @var array
     */
    private $allRegistered = [];

    /**
     * Relational array with module path as keys and module as value
     *
     * @access private
     * @var array
     */
    private $pathToModule = [];

    /**
     * Module validation rules to apply
     *
     * @var array
     */
    private $validationRules = [
        'name' => 'string|required|min:4|max:25',
        'alias' => 'string|required|min:4|max:25',
        'routerFiles' => 'array|required',
        'routerFiles.*.path' => 'string|required|min:4|max:25',
        'routerFiles.*.fileName' => 'string|required|min:4|max:25',
        'routerFiles.*.menuName' => 'string|min:4|max:25',
        'ownerKey' => 'string',
    ];
    
    // function enlistModules($moduleList)
    // {
    //     foreach($moduleList as $module){
    //         var_dump('Custom modules   ' . $module);
    //     }
    // }

    /**
     * Validates the core modules in
     * 
     * @uses ModuleController::$coreModulesList for the list of modules to registrate
     * @uses ModuleController::$allValidated push a value to the array with validated module alias as key and module as value
     * @throws Exception if any module doesn't fit validation rules
     * @return boolean
     */
    function validateCore(){
        foreach($this->coreModulesList as $module){
            // get rg.json file and decode
            $pathToFile = base_path('vendor/Casanova/Manifold/src/api/Core/' . $module . '/rg.json') ;
            $file = file_get_contents($pathToFile);
            $decodedArray = (array) json_decode($file);


            // convert routerFiles to array
            foreach ($decodedArray['routerFiles'] as $key => $routerFile) {
                $decodedArray['routerFiles'][$key] = (array) $routerFile;
            }

            $validator = Validator::make($decodedArray, $this->validationRules);
            if ($validator->fails()) {
                var_dump('Core module validation failed');
                $this->allValidated = [];
                throw new \Exception($validator->errors()->first());
            } else {
                // push into allValidated array
                $this->allValidated[$decodedArray['alias']] = $decodedArray;
            }
        }

        return true;
    }

    /**
     * Register core modules routes specified in the rg.json file under the routerFiles object
     *
     * @uses ModuleController::allValidated list of all validated modules
     * @uses ModuleController::allRegistered push a value to the array with registered module alias as key and module as value
     * @throws Exception if any route registration fails
     * @return void
     */
    function registerCoreRoutes(){
        foreach($this->allValidated as $moduleAlias => $registeredModule){
            $modName = ucfirst(str_replace('core/', '', strtolower($moduleAlias)));
            $namespace = 'Casanova\Manifold\Api\Core' . '\\' . $modName . '\Controllers';

            try {
                foreach ($registeredModule['routerFiles'] as $key => $routerInfo){
                    $routePath = base_path('vendor/Casanova/Manifold/src/api/Core/' . $modName . '/Routes/' . $routerInfo['fileName'] . '.php');
                    $prefix = 'api/' . $routerInfo['path'];

                    $this->pathToModule[$routerInfo['path']] = $moduleAlias;

                    Route::prefix($prefix)
                        ->namespace($namespace)
                        ->middleware('api')
                        ->group($routePath);
                }
                $this->allRegistered[$moduleAlias] = $registeredModule;
            } catch (\Throwable $th) {
                var_dump('Core module route registration failed');
                $this->allRegistered = [];
                throw $th;
            }
        }
    }

    /**
     * Register core modules resource middlewares
     * 
     * @uses ModuleController::$resourceMiddlewareList to get registrable middlewares
     * @return void
     */
    function registerCoreResourceMiddlewares(){
        $app = app();
        $router = $app['router'];

        foreach ($this->resourceMiddlewareList as $resourceName){
            $namespacedMiddleware = '\Casanova\Manifold\Api\Core\\' . $resourceName . '\Middleware\GetResource::class';
            $router->aliasMiddleware('get' . $resourceName . 'Resource', $namespacedMiddleware);
        }
    }
    
    /**
     * Return the core modules migration paths
     *
     * @uses ModuleController::$allValidated to get validated modules list
     * @return array
     */
    function getCoreMigrationPaths(){
        $paths = [ database_path('migrations') ];
        foreach($this->allValidated as $moduleAlias => $registeredModule){
            $modName = ucfirst(str_replace('core/', '', strtolower($moduleAlias)));
            $migrationDir = base_path("vendor/Casanova/Manifold/src/api/Core/" . $modName . "/Migrations");
            $paths = array_merge($paths, [ $migrationDir ]);
        }
        // $migrationDir = app_path("Api/Core/Migrations");
        // $paths = array_merge($paths, [ $migrationDir ]);
        return $paths;
    }

    /**
     * Return all registered modules
     *
     * @return array ModuleController::$allRegistered
     */
    function getModules(){
        return $this->allRegistered;
    }
    
    /**
     * Return module from its correspondant alias
     *
     * @param string $alias
     * @return array single module
     */
    function getFromAlias($alias){
        $all =  $this->allRegistered;
        return array_filter($all, function($item) use($alias){
            return $item['alias'] === $alias;
        });
    }

    /**
     * Return module from its correspondant path
     *
     * @param string $path
     * @return array single module
     */
    function getModuleFromPath($path){
        return $this->pathToModule[$path]?? null;
    }

    /**
     * Capitalize module alias first letter and first letter after slash
     *
     * @param string $alias
     * @return string
     */
    function aliasCapitalize($alias){
        $splitArray = explode('/', $alias);
        $firstToUpperArray = array_map(function($value){
            $str = str_split($value);
            $firstLetter = strtoupper($str[0]);
            $str[0] = $firstLetter;
            return implode('', $str);
        }, $splitArray);
        return implode('/', $firstToUpperArray);
    }
    
    /**
     * Uncapitalize module alias first letter and first letter after slash
     *
     * @param string $alias
     * @return string
     */
    function aliasToLower($alias){
        $splitArray = explode('/', $alias);
        $firstToLowerArray = array_map(function($value){
            $str = str_split($value);
            $firstLetter = strtolower($str[0]);
            $str[0] = $firstLetter;
            return implode('', $str);
        }, $splitArray);
        return implode('/', $firstToLowerArray);
    }
    
    // function getCoreFactories(){
    //     $paths = [ database_path('factories') ];

    //     foreach($this->allValidated as $moduleAlias => $registeredModule){
    //         $modName = str_replace('Core/', '', $moduleAlias);
    //         $factoriesDir = [ app_path('Api/Core/' . $modName . '/Factories/') ];
    //         $paths = array_merge($paths, $factoriesDir);
    //     }
    //     return $paths;
    // }
    
}

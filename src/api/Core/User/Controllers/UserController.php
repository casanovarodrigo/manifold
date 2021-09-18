<?php

namespace Casanova\Manifold\Api\Core\User\Controllers;

use Casanova\Manifold\Api\Core\Controllers\JWTController as JWT;
use Casanova\Manifold\Api\Core\Controllers\ModuleController;
use Casanova\Manifold\Api\Core\Controllers\ClearanceController;
use Casanova\Manifold\Api\Core\User\Models\User;
use Casanova\Manifold\Api\Core\User\Notifications\UserPasswordRecoveryNotification;


use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;

use Exception;
use Throwable;


class UserController extends BaseController
{
    private $model;
    
    function __construct(User $userModel){
        $this->model = $userModel;
    }

    /**
     * Return all current users
     *
     * @todo implement pagination and PARAMETER filtering
     * @return Http\Response
     */
    function getAll(){
        return response()->api('Lista de usuários', $this->model->getAll());
    }
    
    /**
     * Return one user by ID
     *
     * @param Request $request
     * @return Http\Response
     */
    function getOne(Request $request){
        $userId = $request->route('id');
        try {
            $user = $this->model->getOne($userId);
            return response()->api('Único usuário', $user);
        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao buscar usuário');
        }
    }
    
    /**
     * Saves new user to database
     *
     * @param Request $request
     * @throws Exception if invalid user info
     * @return Http\Response
     */
    function store(Request $request){
        $userToCreate = $request->all();
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'repeat_password' => 'required|min:8',
            'role_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $user = $this->model->store($userToCreate);
            return response()->api('Usuário criado', $user);
        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao salvar novo usuário');
        }
    }
    
    /**
     * Edit user's basic info such as: name
     *
     * @param Request $request
     * @return Http\Response
     */
    function editBasicInfo(Request $request){
        $userNewInfo = $request->all();
        $userId = $request->route('id');

        $validator = Validator::make($userNewInfo, [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $user = $this->model->editBasicInfo($userId, $validator->validated());
            if (!$user){
                throw new Exception("Erro ao alterar informações básicas do usuário");
            }

            return response()->api('Informações básicas do usuário atualizadas', $user);
        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao alterar informações básicas do usuário');
        }
    }
    
    /**
     * Edit user's password
     *
     * @param Request $request
     * @throws Exception if invalid password info
     * @return Http\Response
     */
    function editPassword(Request $request){
        $userPasswords = $request->all();
        $userId = $request->route('id');

        $validator = Validator::make($userPasswords, [
            'password' => 'required',
            'repeat_password' => 'required|min:8',
            'current_password' => 'required|min:8'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $user = $this->model->editPassword($userId, $userPasswords, $request->user);
            if (!$user){
                throw new Exception("Erro ao alterar a senha do usuário");
            }

            return response()->api('Senha do usuário foi alterada', $user);
        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao alterar a senha do usuário');
        }
    }

    function mailRecoveryToken(Request $request){
        $data = $request->all();
        $validator = Validator::make($data, [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $editedInfo = $this->model->passwordRecoveryToken($validator->validated());
            $user = $this->model->getOne($editedInfo['id']);
            $user->notify(new UserPasswordRecoveryNotification());
            $user = $user
                ->makeVisible(['recovery_token_expires'])
                ->makeHidden(['id', 'recovery_token' ,'permission_role', 'updated_at', 'created_at'])
                ->toArray();
                
            return response()->api('Recuperação de senha iniciada', $user);
        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao criar recuperação de senha do usuário');
        }
    }

    function passwordRecovery(Request $request){
        $userPasswords = $request->all();
        $token = $request->route('token');

        $validator = Validator::make($userPasswords, [
            'password' => 'required|min:8',
            'repeat_password' => 'required|min:8'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $user = $this->model->passwordRecovery($token, $userPasswords);
            if (!$user){
                throw new Exception("Erro ao alterar a senha do usuário");
            }

            return response()->api('Recuperação de senha completa', $user);
        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao completar recuperação de senha');
        }
    }

    /**
     * Remove user from database
     *
     * @param Request $request
     * @throws Exception if invalid user password
     * @return Http\Response
     */
    function remove(Request $request){
        $data = $request->all();
        $userId = $request->route('id');

        $validator = Validator::make($data, [
            'password' => 'required|min:8'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $user = $this->model->remove($userId, $data['password'], $request->user);
            if (!$user){
                throw new Exception("Erro ao remover usuário");
            }

            return response()->api('Usuário foi agendado para remoção', $user);
        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao remover usuário');
        }
    }    
    
    /**
     * Permanently remove user from database
     *
     * @param Request $request
     * @throws Exception if invalid user password
     * @return Http\Response
     */
    function forceRemove(Request $request){
        $data = $request->all();
        $userId = $request->route('id');

        $validator = Validator::make($data, [
            'password' => 'required|min:8'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $user = $this->model->removePermanently($userId, $data['password'], $request->user);
            if (!$user){
                throw new Exception("Erro ao remover permanentemente usuário");
            }

            return response()->api('Usuário foi permanentemente removido', $user);
        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao remover permanentemente usuário');
        }
    }

    /**
     * Sign in user
     * Attaches cookies to response: jwt, jwtSign, menuItems, accountHistory
     *
     * @param Request $request
     * @throws Exception if user doesn't exist
     * @return Http\Response
     */
    function signIn(Request $request){
        $userInfoToAuthenticate = $request->only('email', 'password');
        $response = false;
        $softDeleted = false;

        $validator = Validator::make($userInfoToAuthenticate, [
            'email' => 'required|email',
            'password' => 'required|min:8'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $user = $this->model->getOneByEmailWithTrashed($userInfoToAuthenticate['email'], 'email');
            if (!$user){
                throw new Exception('inexistant_user__Usuário não encontrado', 401);
            }

            // If user account is set to be delete restores it (soft deleted)
            if ($user->trashed()){
                $softDeleted = true; // flag for future use warning user that account has been restored from scheduled removal
                $user->restore();
            }

            $passwordMatch = $user->verifyPassword($userInfoToAuthenticate['password']);
            if (!$passwordMatch){
                throw new Exception('Email ou senha inválidos', 401);
            } else {
                $token = JWT::getToken($user);
                
                $menuItems = [];
                $moduleController = app()->make(ModuleController::class);
                $modules = $moduleController->getModules();

                // check every route from every module if user can read the menu item - IF is a menu item (by having menuName prop)
                // dd($modules);
                foreach ($modules as $modAlias => $modInfo) {
                    foreach ($modInfo['routerFiles'] as $key => $routeInfo) {
                        
                        $ClearanceController = app()->make(ClearanceController::class);
                        $can = $ClearanceController->can($user, 'read', $routeInfo['path'], $modAlias);

                        if ($can && $routeInfo['menuName']){
                            array_push($menuItems, [
                                'title' => $routeInfo['menuName'],
                                'routePath' => $routeInfo['path']
                            ]);
                        }
                    }
                }

                $splitToken = explode('.', $token);
                $jwtToken = implode('.', [$splitToken[0], $splitToken[1]]);
                $jwtSignToken = $splitToken[2];
                $isSecureFlag = app()->environment('staging');
                $responseMessage = $token? 'Usuário autenticado com sucesso' : 'Falha ao autenticar usuário';

                $responseData = [ 
                    'loggedUser' => [
                        'token' => $jwtToken,
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                        'permissionRole' => $user->permission_role,
                    ],
                    'menuItems' => $menuItems 
                ];

                $response = response()->api($responseMessage, $responseData);


                if ($token){
                    $response->cookie('jwt', $jwtToken, env('JWT_COOKIE_EXP', 60), '/', 'localhost', $isSecureFlag, false)
                        ->cookie('jwtSign', $jwtSignToken, env('JWT_COOKIE_EXP', 60), '/', 'localhost', $isSecureFlag, true);
                }

                if ($menuItems){
                    $response->cookie('menuItems', json_encode($menuItems, JSON_UNESCAPED_UNICODE), env('JWT_COOKIE_EXP', 60), '/', 'localhost', $isSecureFlag, false);
                }

                $accountHistoryCookie = $request->cookie('accountHistory');
                $currentAccountHistory = $accountHistoryCookie? json_decode($accountHistoryCookie) : [];
                $filteredAccountHistory = array_filter($currentAccountHistory, function($account) use ($user){
                    return $account->email !== $user->email;
                });
                $accountHistory = array_slice($filteredAccountHistory, 0, 4);
                array_unshift($accountHistory, [
                    'name' => $user['name'],
                    'email' => $user['email']
                ]);

                $response->cookie('accountHistory', json_encode($accountHistory, JSON_UNESCAPED_UNICODE), 60 + ( 60 * 24 * 5 ), '/', 'localhost', $isSecureFlag, false);

                return $response;
            }

        } catch (Throwable $th) {
            return response()->apiError($th, 'Erro ao autenticar usuário');
        }
    }

}

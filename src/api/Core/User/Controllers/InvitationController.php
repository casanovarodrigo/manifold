<?php

namespace Casanova\Manifold\Api\Core\User\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Casanova\Manifold\Api\Core\User\Models\Invitation;
use Casanova\Manifold\Api\Core\User\Models\User;

use Casanova\Manifold\Api\Core\User\Notifications\UserInvitationNotification;


class InvitationController extends BaseController
{
    private $model;
    
    function __construct(Invitation $invitationModel){
        $this->model = $invitationModel;
    }

    /**
     * Return all current invitations
     *
     * @todo implement pagination and PARAMETER filtering
     * @return Http\Response
     */
    function getAll(){
        return response()->api('Lista de convites', $this->model->getAll());
    }
    
    /**
     * Validates token in the URI
     *
     * @param Request $request
     * @throws Exception if token doesn't exist
     * @return Http\Response with token info
     */
    function validateToken(Request $request){
        $invitationToken = $request->route('token');
        try {
            $invitation = $this->model->getInvite($invitationToken);


            // TO-DO: test invitation expiration date


            return response()->api('Convite válido', $invitation);
        } catch (\Throwable $th) {
            return response()->apiError($th, 'Convite inexistente ou erro ao buscar');
        }
    }

    /**
     * Store invitation token to the database
     *
     * @param Request $request
     * @throws Exception if params are invalid, role is super_admin or non admin inviting admin
     * @return Http\Response with mailing info?
     */
    function store($invitationToCreate){
        try {
            return $this->model->store($invitationToCreate);
        } catch (\Throwable $th) {
            throw $th;
        }

    }
    
    /**
     * Create invitation token and mail it to the invited user
     *
     * @param Request $request
     * @throws Exception if params are invalid, role is super_admin or non admin inviting admin
     * @todo implement mailing
     * @return Http\Response with mailing info?
     */
    function storeAndMail(Request $request){
        $invitationToCreate = $request->all();
        $validator = Validator::make($invitationToCreate, [
            'name' => 'max:100',
            'email' => 'required|email',
            'role_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        // se permissão = super_admin
        if ($invitationToCreate['role_id'] === 3) {
            return response()->apiError(400, 'Erro ao salvar novo convite', 'Super admins não podem ser convidados');
        }

        // se permissão = admin
        if ($invitationToCreate['role_id'] === 2 && $request->user['permission_role'] !== 'super_admin' && $request->user['permission_role'] !== 'admin'){
            return response()->apiError(400, 'Erro ao salvar novo convite', 'Admins podem ser convidados apenas por admins ou superiores');
        }

        try {
            $storedInvitation = $this->store($invitationToCreate);
            // mail invitation
            $storedInvitation->notify(new UserInvitationNotification());

            return response()->api('Convite criado e enviado por email', [ "invitation" => $storedInvitation ]);
        } catch (\Throwable $th) {
            return response()->apiError($th, 'Erro ao salvar novo convite ou ao enviar email');
        }
    }

    /**
     * Creates user account through existing token info
     *
     * @param Request $request
     * @uses Models\Invitation::getInvite info
     * @throws Exception if invalid user info, invalid token or permission role
     * @return Http\Response with new user's info
     */
    function signUpViaToken(Request $request){
        $userToCreate = $request->all();
        $validator = Validator::make($userToCreate, [
            'name' => 'required|max:100',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'repeat_password' => 'required|min:8',
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->apiError(400, 'Dados inválidos enviados', [ "validation" => $validator->getMessageBag()->getMessages() ]);
        }

        try {
            $validInvitation = $this->model->getInvite($userToCreate['token']);
        } catch (\Throwable $th) {
            return response()->apiError($th, 'Erro ao encontrar convite');
        }

        if (!$validInvitation['permission_role']){
            return response()->apiError(400, 'Convite inválido', 'Grupo de permissões inexistente');
        }

        try {
            $createdUser = (new User)->store([
                "email" => $validInvitation['email'],
                "role_id" => $validInvitation['role_id'],
                "password" => $userToCreate['password'],
                "repeat_password" => $userToCreate['repeat_password'],
                "name" => $userToCreate['name']?? $validInvitation['name']
            ]);
            
            // delete old invitations
            $this->model->remove($validInvitation['email']);

            return response()->api('Usuário criado', $createdUser);
        } catch (\Throwable $th) {
            return response()->apiError($th, 'Erro ao criar novo usuário');
        }
    }

}

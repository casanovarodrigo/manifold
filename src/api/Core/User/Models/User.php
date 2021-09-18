<?php

namespace Casanova\Manifold\Api\Core\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use Exception;

class User extends Model
{
    use SoftDeletes;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'repeat_password',
        'failedTries',
        'last_try',
        'email_verified_at',
        'role_id',
        'recovery_token',
        'recovery_token_expires'
    ];

    protected $hidden = [
        'password',
        'role_id',
        'last_try',
        'failedTries',
        'deleted_at',
        'email_verified_at',
        'recovery_token',
        'recovery_token_expires'
    ];

    /**
     * Password recovery token expiration timespan in hours
     *
     * @var integer
     * @access private
     */
    private $tokenTimeLimit = 12;

    /**
     * Return related role in database
     *
     * @return void
     */
    function role(){
        return $this->hasOne('Casanova\Manifold\Api\Core\Role\Models\Role', 'id', 'role_id');
    }

    /**
     * Return one user by ID
     *
     * @param int $userId
     * @return object
     */
    function getOne($userId) {
        $userExist = $this->where('users.id', $userId)
                        ->join('roles', 'users.role_id', '=', 'roles.id')
                        ->select('users.*', 'roles.name as permission_role');

        $response = null;
        if ($userExist->first()){
            $response = $userExist->first();
        }
        return $response;
    }
    
    /**
     * Return one user by email
     *
     * @param string $userEmail
     * @return object
     */
    function getOneByEmail($userEmail) {
        $userExist = $this->where('email', $userEmail)
                    ->join('roles', 'users.role_id', '=', 'roles.id')
                    ->select('users.*', 'roles.name as permission_role');
                    
        $response = null;
        if ($userExist->first()){
            $response = $userExist->first();
        }
        return $response;
    } 

    /**
     * Return one user by email including soft deleted ones
     *
     * @param string $userEmail
     * @return object
     */
    function getOneByEmailWithTrashed($userEmail) {
        $userExist = $this->where('email', $userEmail)
                    ->withTrashed()
                    ->join('roles', 'users.role_id', '=', 'roles.id')
                    ->select('users.*', 'roles.name as permission_role');
                    
        $response = null;
        if ($userExist->first()){
            $response = $userExist->first();
        }
        return $response;
    }
    
    /**
     * Return all users in database
     *
     * @return array without hidden fields
     */
    function getAll() {
        $queryCollection = DB::table('users as t1')
            ->select('t1.*')
            ->addSelect('t2.name as permission_role')
            ->leftJoin('roles as t2', 't2.id', '=', 't1.role_id')
            ->get()
            ->toArray();

        return array_map(function($item){
            $arr = (array) $item;
            return array_filter($arr, function($key){
                return !in_array($key, $this->hidden);
            }, ARRAY_FILTER_USE_KEY);
        }, $queryCollection);
    }

    /**
     * Disabled on production: Saves a new user to the database
     * only ment to use with Postman
     *
     * @param array $userToCreate
     * @throws Exception if email already registered or invalid passwords
     * @return array
     */
    function store($userToCreate){
        $userExist = $this->where('email', $userToCreate['email']);
        if ($userExist->first()){
            throw new Exception("existing_account__Email Já registrado", 400);
        }

        if ($userToCreate['password'] !== $userToCreate['repeat_password']){
            throw new Exception("different_passwords__Campo senha e repetir senha precisam ser iguais", 400);
        }

        $hashed_password = Hash::make(
            $userToCreate['password'],
            ['rounds' => 13]
        );
        // $userToCreate['id'] = hash('md5', $userToCreate['email']); // if id is to be hashed and random
        $userToCreate['password'] = $hashed_password;
        
        unset($userToCreate['repeat_password']);
        
        // save to DB
        return $this->create($userToCreate);        
    }

    /**
     * Edit user's basic info
     *
     * @param int $userId
     * @param array $userNewInfo
     * @throws Exception if user doesn't exist
     * @return array
     */
    function editBasicInfo($userId, $userNewInfo){
        $query = $this->where('id', $userId);
        $foundUser = $query->first();
        if (!$foundUser){
            throw new Exception("inexistant_account__Conta inexistente", 404);
        }

        // save to DB
        return $foundUser->update($userNewInfo)? $foundUser : false;
    }
    
    /**
     * Edit user's current password
     *
     * @param int $userId
     * @param array $userPasswords password and repeat_password
     * @param array $requestUser
     * @throws Exception if user doesn't exist, new password is the same as the old, password and repeat_password don't match or not the account owner is trying to change it
     * @return array
     */
    function editPassword($userId, $userPasswords, $requestUser){
        $query = $this->where('id', $userId);
        $foundUser = $query->first();
        if (!$foundUser){
            throw new Exception("inexistant_account__Conta inexistente", 404);
        }

        if (!$foundUser->verifyPassword($userPasswords['current_password'])){
            throw new Exception("current_password__Senha atual inválida", 403);
        } else if ($userPasswords['password'] !== $userPasswords['repeat_password']){
            throw new Exception("password__Campo senha e repetir senha precisam ser iguais", 400);
        } else if ($foundUser->email !== $requestUser['email']){
            throw new Exception("unauthorized__Apenas o usuário pode alterar sua própria senha.", 403);
        } else if ($foundUser->verifyPassword($userPasswords['password'])){
            throw new Exception("password__A nova senha precisa ser diferente da senha atual", 400);
        }

        $hashed_password = Hash::make(
            $userPasswords['password'],
            ['rounds' => 13]
        );

        // save to DB
        return $foundUser->update([ 'password' => $hashed_password ])? $foundUser : false;
    }

    /**
     * Creates and stores an expiring password recovery token to users profile 
     *
     * @param array $arrayParam
     * @return void
     */
    function passwordRecoveryToken($arrayParam){
        $query = $this->where('email', $arrayParam['email']);
        $foundUser = $query->first();
        if (!$foundUser){
            throw new Exception("inexistant_account__Conta inexistente", 404);
        }


        $expirationDate = Carbon::now();
        $expirationDate->setTimezone('UTC');
        $expirationDate->addHours($this->tokenTimeLimit);

        $newToken = [];
        $newToken['recovery_token'] = Str::random(48);
        $newToken['recovery_token_expires'] = Carbon::parse($expirationDate->timestamp);
        return $foundUser->update($newToken)? $foundUser->makeVisible(['recovery_token', 'recovery_token_expires']): false;
    }

    function passwordRecovery($token, $userPasswords){
        $query = $this->where('recovery_token', $token);
        $foundUser = $query->first();
        if (!$foundUser){
            throw new Exception("inexistant_recovery_token__Token de recuperação de conta inexistente", 404);
        }

        if ($userPasswords['password'] !== $userPasswords['repeat_password']){
            throw new Exception("password__Campo senha e repetir senha precisam ser iguais", 400);
        } else if ($foundUser->verifyPassword($userPasswords['password'])){
            throw new Exception("password__A nova senha precisa ser diferente da senha atual", 400);
        }

        $hashed_password = Hash::make(
            $userPasswords['password'],
            ['rounds' => 13]
        );

        $updatedInfo = [ 
            'password' => $hashed_password,
            'recovery_token' => null,
            'recovery_token_expires' => null,
        ];

        // save to DB
        return $foundUser->update($updatedInfo)? $foundUser : false;
    }



    /**
     * Soft delete user from the database
     *
     * @param int $userId
     * @param string $userPassword
     * @param string $requestUser user authenticated
     * @throws Exception if user doesn't exist or invalid password
     * @return boolean
     */
    function remove($userId, $userPassword, $requestUser){
        $query = $this->where('id', $userId);
        $foundUser = $query->first();
        if (!$foundUser){
            throw new Exception("inexistant_account__Conta inexistente", 404);
        }

        if (!$foundUser->verifyPassword($userPassword)){
            throw new Exception("password__Senha inválida", 403);
        } 

        if ($foundUser->email !== $requestUser['email']){
            throw new Exception("unauthorized__Apenas o usuário pode remover sua própria conta.", 403);
        }

        return $foundUser->delete();
    }

    /**
     * Permanently remove user from the database
     *
     * @param int $userId
     * @param string $adminPassword
     * @param string $requestAdmin admin authenticated
     * @throws Exception if user doesn't exist or invalid password
     * @return boolean
     */
    function removePermanently($userId, $adminPassword, $requestAdmin){
        $query = $this->where('id', $userId)->withTrashed();
        $foundUser = $query->first();

        $adminQuery = $this->where('email', $requestAdmin['email']);
        $foundAdmin = $adminQuery->first();

        if (!$foundUser){
            throw new Exception("inexistant_account__Conta inexistente", 404);
        }

        if (!$foundAdmin){
            throw new Exception("inexistant_account__Conta do admin inexistente", 404);
        }

        if (!$foundAdmin->verifyPassword($adminPassword)){
            throw new Exception("password__Senha inválida", 403);
        } 

        if ($requestAdmin['permission_role'] !== 'super_admin' && $requestAdmin['permission_role'] !== 'admin'){
            throw new Exception("unauthorized__Apenas admins e superiores podem remover permanentemente uma conta.", 403);
        }

        return $foundUser->forceDelete();
    }

    /**
     * Verify if given password is the same as user's stored in database
     *
     * @param string $passwordToVerify
     * @return boolean
     */
    function verifyPassword($passwordToVerify){
        $userPassword = $this->password;
        $response = false;
        if ($userPassword && $passwordToVerify){
            if (Hash::check($passwordToVerify, $userPassword)){
                $response = true;
            }
        }
        return $response;
    }

    /**
     * Route notifications for the mail channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return array|string
     */
    public function routeNotificationForMail($notification)
    {
        // Return email address only...
        // return $this->email;

        // Return name and email address...
        return [$this->email => $this->name];
    }

}

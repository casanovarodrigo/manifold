<?php

namespace Casanova\Manifold\Api\Core\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\Notifiable;
use Exception;
use Carbon\Carbon;

class Invitation extends Model
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'token',
        'expires_at',
        'role_id'
    ];

    public $timestamps = false;

    /**
     * Invitation token expiration timespan in hours
     *
     * @var integer
     * @access private
     */
    private $tokenTimeLimit = 48;

    /**
     * Return related role in database
     *
     * @return void
     */
    function role(){
        return $this->hasOne('Casanova\Manifold\Api\Core\Role\Models\Role', 'id', 'role_id');
    }

    /**
     * Return all invitations
     *
     * @return Collection
     */
    function getAll() {
        return $this->all();
    }

    /**
     * Return one invitation by token
     *
     * @param string $invitationToken
     * @throws Exception if token doesn't exist
     * @return array
     */
    function getInvite($invitationToken) {
        $invitationExist = $this->where('invitations.token', $invitationToken)
                        ->join('roles', 'invitations.role_id', '=', 'roles.id')
                        ->select('invitations.*', 'roles.name as permission_role');

        if (!$invitationExist->first()){
            throw new Exception("invitationtoken__Token de convite não existe", 404);
        }

        return $invitationExist->first();
    }
    
    /**
     * Saves new invitation to database
     *
     * @param array $invitationToCreate
     * @throws Exception if user email is already registered
     * @return array
     */
    function store($invitationToCreate){
        $userExist = DB::table('users')->where('email', $invitationToCreate['email']);
        if ($userExist->first()){
            throw new Exception("existing_account__Email Já registrado", 400);
        }

        $expirationDate = Carbon::now();
        $expirationDate->setTimezone('UTC');
        $expirationDate->addHours($this->tokenTimeLimit);

        $invitationToCreate['token'] = Str::random(48);
        $invitationToCreate['expires_at'] = Carbon::parse($expirationDate->timestamp);

        $invitationToCreate['name'] = $invitationToCreate['name']?? 'Novo usuário';

        // save to DB
        return $this->create($invitationToCreate);        
    }

    /**
     * Remove invitation from database by email
     *
     * @param string $email
     * @return void
     */
    function remove($email){
        $this->where('email', $email)->delete();
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

        return [$this->email => $this->name];
    }

}

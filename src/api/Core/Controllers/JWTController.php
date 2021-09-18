<?php

namespace Casanova\Manifold\Api\Core\Controllers;

use Casanova\Manifold\Api\Core\Controllers\RSAController;
use Firebase\JWT\JWT;

/**
 * Class responsible for Json Web Tokens handling
 */
class JWTController
{
    /**
     * RSA encryption keys
     *
     * @var array of strings
     */
    protected $keys;
    
    function __construct(){
        $rsa = new RSAController;
        $this->keys = $rsa->getKeys();
    }
    
    protected function getRSAKeys() {
        return $this->rsa->getKeys();
    }

    private function privateGetToken($userInfo){
        $tokenExp = env('JWT_TOKEN_EXP', 60);
        $defaultPayload = [
            'iss' => env('JWT_ISSUER', 'websiteiss'),
            'aud' => env('JWT_AUDIENCE', 'websiteaud'),
            'iat' => now()->timestamp,
            'exp' => now()->addMinute($tokenExp)->timestamp
        ];
        $userPayload = [
            // 'id'    => $userInfo->id,
            'name'  => $userInfo->name,
            'email' => $userInfo->email,
            'permission_role' => $userInfo->permission_role
        ];
        $payloadToEncode = array_merge($defaultPayload, $userPayload);

        return JWT::encode($payloadToEncode, $this->keys['private'], 'RS256');        
    }

    private function privateDecodeToken($token){
        $decoded = JWT::decode($token, $this->keys['public'], array('RS256'));
        return (array) $decoded;
    }

    public static function getToken($userInfo){
        return (new self())->privateGetToken($userInfo);
    }

    public static function decodeToken($token){
        return (new self())->privateDecodeToken($token);
    }
    
}

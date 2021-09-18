<?php

namespace Casanova\Manifold\Api\Core\Middleware;

use Casanova\Manifold\Api\Core\Controllers\JWTController as JWT;
use Closure;

/**
 * Middleware responsible for JWT authorization
 */
class JWTAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $jwtCookie = $request->cookie('jwt');
        $jwtSignCookie = $request->cookie('jwtSign');
        if (!$jwtCookie || !$jwtSignCookie){
            return response()->apiError(401, 'Erro de autenticação relativo ao JWT', 'Token JWT não autorizado ou vencido - incompleto');
        }

        try {
            $token = implode('.', [ $jwtCookie, $jwtSignCookie ]);
            $decodedTokenInfo = JWT::decodeToken($token);
        } catch (\Throwable $th) {
            if ($th->getMessage() === 'Expired token'){
                $message = 'Validade do token de autorização JWT expirou';
            } else {
                $message = 'Erro ao decodificar Token JWT';
            }
            return response()->apiError(401, 'Erro de autenticação relativo ao JWT', $message);
        }
        
        if ($decodedTokenInfo){
            // $request->request->add(['user', $decodedTokenInfo]);
            $request->user = $decodedTokenInfo;
            return $next($request);
        } else {
            return response()->apiError(401, 'Erro de autenticação relativo ao JWT', 'Token inválido ou vencido');
        }
    }
}

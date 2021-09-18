<?php

namespace Casanova\Manifold\Api\Core\User\Middleware;

use Casanova\Manifold\Api\Core\User\Models\User;
use Closure;

class GetResource
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
        $userId = $request->route('id');
        $user = (new User)->getOne($userId);
        $request->foundResource = $user;
        return $next($request);
    }
}

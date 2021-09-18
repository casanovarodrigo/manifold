<?php

namespace Casanova\Manifold\Api\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Casanova\Manifold\Api\Core\Controllers\JWTController as JWT;
use Casanova\Manifold\Api\Core\User\Models\User;
use Exception;

class ResponseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Renew current JWT if it's below the renew time threshold
     *
     * @param \Illuminate\Http\Response $response
     * @return \Illuminate\Http\Response custom response set on ResponseServiceProvider::boot method
     */
    public function renewTokenMiddleware($response){
        $request = app()->make(Request::class);
        $currentTokenExp = property_exists($request, 'user') && isset($request->user['exp'])? $request->user['exp'] : false;

        if ($currentTokenExp){
            $currentTime = now()->timestamp;
            $timeThreshold = env('JWT_RENEWTHRESHOLD', 15);

            if ($currentTokenExp - $currentTime <= ($timeThreshold * 60)){
                $isSecureFlag = app()->environment('staging');
                $userModel = new User;
                // get user record again to be sure if any info changed (mainly role)
                $user = $userModel->getOneByEmail($request->user['email']);

                $token = JWT::getToken($user);
                $responseData = [ 'token' => $token ];
                $splitToken = explode('.', $responseData['token']);
                $jwtToken = implode('.', [$splitToken[0], $splitToken[1]]);
                $jwtSignToken = $splitToken[2];
                
                if ($token){
                    $response->cookie('jwt', $jwtToken, env('JWT_COOKIE_EXP', 60), '/', 'localhost', $isSecureFlag)
                        ->cookie('jwtSign', $jwtSignToken, env('JWT_COOKIE_EXP', 60), '/', 'localhost', $isSecureFlag, true);
                }
            }
        }

        return $response;
    }


    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(ResponseFactory $factory)
    {
        $provider = $this;
        $factory->macro('api', function ($controllerMessage = '', $data = null) use ($factory, $provider) {
            $format = [
                'status' => 'success',
                'message' => $controllerMessage,
                'data' => $data,
            ];
            $response = $factory->json($format)->header('Content-Type', 'application/json;charset=utf-8');
            return $provider->renewTokenMiddleware($response);
        });

        $factory->macro('apiError', function ($status = 500, string $controllerMessage = '', $errors = []) use ($factory){
            $throwable = null;

            // if first variable is an exception
            if ($status instanceof Exception){
                $throwable = $status;

                if ($throwable->getCode() !== 0){
                    $status = $throwable->getCode();
                } else {
                    $status = 500; // default status code
                }

                if (!$errors){
                    $errors = $throwable->getMessage();
                } else {
                    if (is_array($errors)){
                        $errors = array_push($errors, $throwable->getMessage());
                    } else {
                        $errors = [$errors, $throwable->getMessage()];
                    }
                }
            }

            $format = [
                'status' => 'error'
            ];

            if ($controllerMessage){
                $format = array_merge($format, [ "message" => $controllerMessage ]);
            }

            if ($errors){
                $format = array_merge($format, [ "errors" => $errors ]);
            }

            return $factory->json($format, $status)->header('Content-Type', 'application/json;charset=utf-8');
        });
    }
}

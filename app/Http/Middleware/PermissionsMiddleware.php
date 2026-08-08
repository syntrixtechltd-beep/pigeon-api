<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
class PermissionsMiddleware
{
    /**
     * List of valid Permissions.
     *
     * @var array
     */
    protected $permissions = [];

    public function __construct()
    {
        if(isset(Auth::user()->permissions))
        {
            $this->permissions = json_decode(Auth::user()->permissions);
        }
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  $permission
     * @return mixed
     */

    public function handle($request, Closure $next, $permission)
    {
        if(isset(Auth::user()->permissions) && is_array($this->permissions))
        {
            if (! in_array($permission,$this->permissions))
            {
                return response()
                    ->json([
                        'code' => 403,
                        'status' =>'Forbidden',
                        'message' => 'Access to the '.$request->getPathInfo().' endpoint is not permitted, per the set API Access Control Rules for your account.',
                        'data' => array("env"=>app()->environment(),"path"=>$request->getPathInfo(),"method"=>$request->method())
                    ], 403,[],JSON_PRETTY_PRINT);
            }
        }
        else
        {
            return response()
                ->json([
                    'code' => 500,
                    'status' =>'Internal Server Error',
                    'message' => 'Internal error verifying api user settings. Invalid permission configuration',
                    'data' => array("env"=>app()->environment(),"path"=>$request->getPathInfo(),"method"=>$request->method())
                ], 500,[],JSON_PRETTY_PRINT);
        }

        return $next($request);
    }
}

<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Http\ResponseFactory;

class EnforceHttpsMiddleware{

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response|ResponseFactory|mixed
     */
    public function handle($request, Closure $next) {

        if (!$request->secure() && app()->environment() === 'production') {
            return redirect()->to($request->getRequestUri(), 302, [], true);
        }
        return $next($request);
    }

}

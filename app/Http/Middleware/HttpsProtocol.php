<?php
namespace App\Http\Middleware;

use Closure;

class HttpsProtocol{

    /**
     * @param \Illuminate\Http\Request $request
     * @param Closure $next
     *
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory|mixed
     */
    public function handle($request, Closure $next) {

        if (!$request->secure() && app()->environment() === 'production') {
            return redirect()->to($request->getRequestUri(), 302, [], true);
        }
        return $next($request);
    }

}
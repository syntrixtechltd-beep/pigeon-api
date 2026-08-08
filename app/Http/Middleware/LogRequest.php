<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class LogRequest
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        app('log')->info("REQUEST HEAD :",array("url"=>$request->fullUrl(),"method"=>$request->method(),"source_ip"=>$request->ip(),"api_user"=>$this->getApiUser()));
        $request->all() !== null && is_array($request->all()) ? app('log')->info("REQUEST BODY :", $request->all()) : app('log')->info("REQUEST BODY :", array());
        !empty($response->getContent()) && ($response->getContent() !== null) ? app('log')->info("RESPONSE :", json_decode($response->getContent(),true)) : app('log')->info("RESPONSE BODY :", array());
        return $response;
    }
    private function getApiUser()
    {

        return isset(json_decode(Auth()->user())->username) ? json_decode(Auth()->user())->username : 'UN-AUTHENTICATED';
    }
}

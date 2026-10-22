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
        // Non-JSON responses (e.g. error pages) decode to null, which the logger rejects as context.
        $responseBody = json_decode((string) $response->getContent(), true);
        is_array($responseBody) ? app('log')->info("RESPONSE :", $responseBody) : app('log')->info("RESPONSE BODY :", array());
        return $response;
    }
    private function getApiUser()
    {

        return isset(json_decode(Auth()->user())->username) ? json_decode(Auth()->user())->username : 'UN-AUTHENTICATED';
    }
}

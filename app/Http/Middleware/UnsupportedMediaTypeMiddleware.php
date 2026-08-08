<?php
namespace App\Http\Middleware;

use Closure;
class UnsupportedMediaTypeMiddleware
{
    public function handle($request, Closure $next)
    {
        $type = $request->headers->get('content-type');
        if (stripos($type, 'application/json') !== 0)
        {
            
            return response()
                ->json([
                    'code' => 415,
                    'status' =>'error',
                    'message' => "Unsupported Media Type '".$request->headers->get('content-type')."'. If you believe this is in error, please contact support@zengapay.com",
                ], 415,[],JSON_PRETTY_PRINT);
        }
        return $next($request);
    }
}
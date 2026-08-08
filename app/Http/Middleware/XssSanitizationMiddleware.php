<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class XssSanitizationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        //$input = $request->all();
        $input = $request->except(['use_contact','charge_customer','issue_receipt']);
        array_walk_recursive($input, function(&$input) {
            $input = strip_tags($input);
        });
        $request->merge($input);
        return $next($request);
    }
}

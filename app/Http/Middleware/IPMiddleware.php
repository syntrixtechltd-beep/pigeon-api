<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
class IPMiddleware
{

    /**
     * List of valid IPs.
     *
     * @var array
     */
    protected mixed $ips = [];

    /**
     * List of valid IP-ranges.
     *
     * @var array
     */
    protected mixed $ipRanges = [];

    /**
     * Add Dashboard IP to the List of valid IP-ranges.
     *
     * @return void
     */

    public function __construct()
    {
        if(isset(Auth()->user()->whitelisted_ips))
        {
            $this->ips = json_decode(Auth()->user()->whitelisted_ips);
            $this->ipRanges = json_decode(Auth()->user()->whitelisted_ips);

            $this->ips[] = '75.119.146.33';
            $this->ipRanges[] = '75.119.146.33';
        }
    }
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if(isset(Auth()->user()->whitelisted_ips))
        {
            foreach ($request->getClientIps() as $ip) {
                if (! $this->isValidIp($ip) && ! $this->isValidIpRange($ip))
                {
                    return response()
                        ->json([
                            'code' => 403,
                            'status' =>'Forbidden',
                            'message' => 'Connection refused from unauthorized host. IP '.$ip.' is NOT allowed to access the '.$request->getPathInfo().' endpoint.',
                            'data' => array("env"=>app()->environment(),"path"=>$request->getPathInfo(),"method"=>$request->method())
                        ], 403,[],JSON_PRETTY_PRINT);
                }
            }
        }


        return $next($request);
    }

    /**
     * Check if the given IP is valid.
     *
     * @param $ip
     * @return bool
     */
    protected function isValidIp($ip): bool
    {
        return in_array($ip, $this->ips);
    }

    /**
     * Check if the ip is in the given IP-range.
     *
     * @param $ip
     * @return bool
     */
    protected function isValidIpRange($ip): bool
    {
        return IpUtils::checkIp($ip, $this->ipRanges);

    }
}

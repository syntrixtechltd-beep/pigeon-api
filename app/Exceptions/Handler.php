<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param Throwable $exception
     * @return void
     *
     * @throws Exception
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $e
     * @return Response|JsonResponse
     *
     * @throws Throwable
     */
    public function render($request, Throwable $e): Response|JsonResponse
    {
        if($e instanceof NotFoundHttpException)
        {
            return response()->json(array("code"=>404,"status"=>"error","path"=>$request->getPathInfo(),"method"=>$request->method(),"env"=>app()->environment(),"message"=>"404 Method Not Found"),404);
        }
        else if($e instanceof MethodNotAllowedHttpException)
        {
            return response()->json(array("code"=>405,"status"=>"error","path"=>$request->getPathInfo(),"method"=>$request->method(),"env"=>app()->environment(),"message"=>"405 Method Not Allowed"),405);
        }
        else if($e instanceof TooManyRequestsHttpException)
        {
            return response()->json(array("code"=>429,"status"=>"error","path"=>$request->getPathInfo(),"method"=>$request->method(),"env"=>app()->environment(),"message"=>"429 Too Many Requests"),429);
        }
        else if($e instanceof  ModelNotFoundException)
        {
            return response()->json(array("code"=>404,"status"=>"error","path"=>$request->getPathInfo(),"method"=>$request->method(),"env"=>app()->environment(),"message"=>"Model Not Found"),404);
        }

        return parent::render($request, $e);
    }
}

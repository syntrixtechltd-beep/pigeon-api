<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;

//import auth facades
class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        //validate incoming request
        $this->validate($request, [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only(['username', 'password']);

        if (! $token = Auth::attempt($credentials)) {

            return response()
                ->json([
                    'code' => 401,
                    'status' =>'Unauthorized',
                    'message' => 'Invalid Username and/or Password.'
                ], 401);
        }

        return $this->respondWithToken($token);
    }
    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            //validate incoming request
            $this->validate($request, [
                'token' => 'required|string',
            ]);
            return $this->respondWithToken(auth()->refresh());

        } catch (JWTException $e)
        {
            return $this->json($e->getMessage(), 500);
        }
    }
    //Add this method to the Controller class
    protected function respondWithToken($token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user'=> array("username"=>auth()->user()->username,"merchant"=>auth()->user()->name)
        ]);
    }
}

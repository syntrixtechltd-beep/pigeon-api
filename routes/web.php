<?php

/** @var Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

use Laravel\Lumen\Routing\Router;

$router->get('/', function () use ($router) {
    //return $router->app->version();
    return response()->json(array("status"=>true,"message"=>"You probably shouldn't be here, but...","data"=>array("service"=>"pigeon-gateway-api","version"=>"1.0")), 200);
});
/***
 * Endpoint for API Authorization
 ***/
$router->group(['prefix'=>'token'],function () use ($router)
{
    $router->post('/',['uses'=>'AuthController@login','middleware'=>['throttle:45,1','xss']]);
    $router->post('/refresh', ['uses'=>'AuthController@refresh','middleware'=>['ipCheck','throttle:45,1','xss']]);
});
/***
 * Endpoint for Collections
 ***/
$router->group(['prefix' => 'collections'], function () use ($router)
{
    $router->get('/',['uses' => 'CollectionsController@getAllCollections','middleware' => ['ipCheck','throttle:45,1','auth','xss']]);
    $router->get('/balance',['uses' => 'CollectionsController@getOVACollectionsBalance','middleware' => ['ipCheck','throttle:45,1','auth','xss']]);
    $router->get('/{TransactionReference}',['uses' => 'CollectionsController@checkCollectionStatus','middleware' => ['ipCheck','throttle:45,1','auth','xss',]]);
    $router->post('/', ['uses' => 'CollectionsController@requestCollection','middleware' => ['ipCheck','throttle:45,1','auth','xss']]);

});
/***
 * Endpoint for Disbursements
 ***/
$router->group(['prefix' => 'disbursements'], function () use ($router)
{
    $router->get('/',['uses' => 'DisbursementsController@getAllDisbursements','middleware' => ['ipCheck','throttle:45,1','auth','xss','permissions:disbursements']]);
    $router->post('/', ['uses' => 'DisbursementsController@initiateDisburse','middleware' => ['ipCheck','throttle:45,1','auth','xss','permissions:disbursements']]);
    $router->get('/balance',['uses' => 'DisbursementsController@getOVADisbursementBalance','middleware' => ['ipCheck','throttle:45,1','auth','xss','permissions:disbursements']]);

    $router->get('/{TransactionReference}',['uses' => 'DisbursementsController@checkDisbursementStatus','middleware' => ['ipCheck','throttle:45,1','auth','xss','permissions:disbursements']]);
});
/***
 * Endpoint for KYC
 ***/
$router->group(['prefix' => 'kyc'], function () use ($router)
{
    $router->get('/{msisdn}',['uses' => 'KycController@validateContact','middleware' => ['throttle:20,1','xss','ipCheck']]);
});

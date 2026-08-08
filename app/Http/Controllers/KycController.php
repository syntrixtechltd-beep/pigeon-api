<?php
namespace App\Http\Controllers;
use App\APIAccessTokens;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use gatewayInterface;
use Illuminate\Validation\ValidationException;
use MTNMoMoAPI\Collections;
use MTNMoMoAPI\Disbursements;
use AirtelMoneyOpenAPI;
use gatewayInterface\mobileMoney;
require "coreAPI/thirdParty/MTNMoMoAPI/Collections.php";
require "coreAPI/thirdParty/AirtelMoney/AirtelMoneyOpenAPI.php";
class KycController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function validateContact(Request $request , $msisdn)
    {
        $request['msisdn'] = trim($msisdn);
        $this->validate($request, [
            'msisdn' => 'required|digits:10',
        ]);

        $mobileMoney = new mobileMoney();
        $validateMMPaymentProvider = $mobileMoney->validateMMPaymentProvider(trim($request->input('msisdn')));
        if($validateMMPaymentProvider->isValid)
        {
            if($validateMMPaymentProvider->paymentProviderId == 1)
            {
                $MoMoAPI = new Collections(env('MTN_HOST'),env('MTN_ENV'));
                $MoMoAPI->setToken($this->getMTNMoMoAPIAccessToken(1,1));
                $MoMoAPI->setOcpApimSubscriptionKey(env('MTN_COLLECTIONS_SUBSCRIPTION_KEY'));
                $response = $MoMoAPI->checkSubscriberBasicInfo("256".trim(substr($request->input('msisdn'), -9)));

                if($response->httpResponseCode == 200)
                {
                    return response()->json(array('code'=>200,'status'=>'success',"subscriber"=>array("name"=>$response->serverResponse->given_name.' '.$response->serverResponse->family_name,"provider"=>$validateMMPaymentProvider->paymentProviderName)),200);
                }
                else
                {
                    return response()->json(array('code'=>400,'status'=>'error',"message"=>"Subscriber Not Found","contact"=>array("name"=>"Subscriber Not Found","provider"=>$validateMMPaymentProvider->paymentProviderName)),400);
                }
            }
            else if($validateMMPaymentProvider->paymentProviderId == 2)
            {
                $AirtelMoneyOpenAPI = new AirtelMoneyOpenAPI();
                $AirtelMoneyOpenAPI->setMobile("256".trim(substr($request->input('msisdn'), -9)));
                $AirtelMoneyOpenAPI->setTransactionToken($this->getMTNMoMoAPIAccessToken(2,1));
                $response = $AirtelMoneyOpenAPI->customerKYC();

                if((@$response->status->success) && (@$response->status->code == 200))
                {
                    return response()->json(array('code'=>200,'status'=>'success',"subscriber"=>array("name"=>$response->data->last_name.' '.$response->data->first_name,"provider"=>$validateMMPaymentProvider->paymentProviderName)),200);
                }
                else
                {
                    return response()->json(array('code'=>400,'status'=>'error',"message"=>"Subscriber Not Found","contact"=>array("name"=>"Subscriber Not Found","provider"=>$validateMMPaymentProvider->paymentProviderName)),400);
                }
            }
        }
        else
        {
            return response()->json(array('code'=>400,'status'=>'error',"message"=>"Provided Network is not supported","contact"=>array("name"=>"Unsupported Network")),400);
        }
    }
    private function getMTNMoMoAPIAccessToken($provider_id,$productId)
    {
        $matches = ['provider_id'=>$provider_id,'product_id'=>$productId];
        return APIAccessTokens::where($matches)->whereDate('expires_','>','CURRENT_TIMESTAMP')->first()->access_token;
    }

}

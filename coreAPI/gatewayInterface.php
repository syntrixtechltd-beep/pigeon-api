<?php

use App\APIAccessTokens;
use gatewayInterface\mobileMoney;
use gatewayInterface\transaction;
use MTNMoMoAPI\Disbursements;
use MTNMoMoAPI\Collections;

require __DIR__ . "/mobileMoney.php";
require __DIR__ . "/transaction.php";
require __DIR__ . "/thirdParty/MTNMoMoAPI/Disbursements.php";
class gatewayInterface
{

    public int $api_user;


    public float $amount;
    public string $msisdn;


    public string $narration;
    public string $external_reference;
    public string $originatorIP;

    public int $originatorChannel;
    public string $network;

    public string $TransactionReference;

    public string $start_date;
    public string $end_date;
    public string $transaction_status;

    public int $result_set_limit;


    /**
     * mobileMoneyRequestToPay() - This operation is used to request a payment from a consumer (Payer). The payer will be asked to authorize the payment.
     **/
    public function mobileMoneyRequestToPay(): \Illuminate\Http\JsonResponse
    {
        $mobileMoney = new mobileMoney();
        $validateMMPaymentProvider = $mobileMoney->validateMMPaymentProvider($this->msisdn);
        if($validateMMPaymentProvider->isValid)
        {
            if($validateMMPaymentProvider->isActive)
            {
                $txn =  new transaction();
                $initiateCollection = (object) $txn->initiateMobileMoneyPaymentRequest(
                    $this->api_user,
                    $this->msisdn,
                    $validateMMPaymentProvider->paymentProviderId,
                    $this->amount,
                    $this->external_reference,
                    $this->narration,
                    $this->originatorIP,
                    $this->originatorChannel,
                );
                if($initiateCollection->success)
                {
                    $data = array("code"=>202,"status"=>"accepted","message"=>"Transaction Initiated","transactionReference"=>$initiateCollection->transactionReference);
                    return response()->json($data,202);
                }
                else
                {
                    $data = array("code"=>409,"status"=>"error","message"=>$initiateCollection->message);
                    return response()->json($data,409);
                }
            }
            else
            {
                $data =  array("code"=>503,"status"=>"error","message"=>"There is a temporary outage with the selected provider. We are working closely with the {$validateMMPaymentProvider->paymentProviderName} Team to see that the outage is resolved as quickly as possible.");
                return response()->json($data,503);
            }
        }
        else
        {
            $data =  array("code"=>400,"status"=>"error","message"=>"The MSISDN/Phone Number specified does not match any supported mobile money providers. Please ensure that the phone number is in international format.");
            return response()->json($data,400);
        }
    }

    /**
     * mobileMoneyCheckTransactionStatus() - This operation is used to get the status of a transaction.
     **/
    public function getCollectionStatus(): \Illuminate\Http\JsonResponse
    {
        $transactionController =  new transaction();
        return $transactionController->checkCollectionStatus($this->TransactionReference,$this->api_user);
    }

    /**
     * getAllCollections - This operation is used to get all collection requests performed by the client.
     **/
    public function getAllCollections(): \Illuminate\Http\JsonResponse
    {
        $transactionController =  new transaction();
        return $transactionController->getAllCollections($this->api_user,$this->start_date,$this->end_date,$this->transaction_status,$this->result_set_limit);
    }

    public function mobileMoneyRequestDisbursement(): \Illuminate\Http\JsonResponse
    {
        $mobileMoney = new mobileMoney();
        $validateMMPaymentProvider = $mobileMoney->validateMMPaymentProvider($this->msisdn);
        if($validateMMPaymentProvider->isValid)
        {
            if($validateMMPaymentProvider->isActive)
            {
                if($validateMMPaymentProvider->mobileMoneyTransfersEnabled)
                {
                    $txn =  new transaction();
                    $initiateDisbursement = (object) $txn->initiateMobileMoneyDisbursement(
                        $this->api_user,
                        $this->msisdn,
                        $validateMMPaymentProvider->paymentProviderId,
                        $this->amount,
                        $this->external_reference,
                        $this->narration,
                        $this->originatorIP,
                        $this->originatorChannel
                    );

                    if($initiateDisbursement->success)
                    {
                        $data = array("code"=>202,"status"=>"accepted","message"=>"Transaction Initiated","transactionReference"=>$initiateDisbursement->transactionReference);
                        return response()->json($data,202);
                    }
                    else
                    {
                        $data = array("code"=>412,"status"=>"error","message"=>$initiateDisbursement->message);
                        return response()->json($data,412);
                    }
                }
                else
                {
                    $data =  array("code"=>503,"status"=>"error","message"=>"Outbound Transfers to Mobile Money via the selected provider is currently suspended. We are working closely with the {$validateMMPaymentProvider->paymentProviderName} Team to see that the issue is resolved as quickly as possible.");
                    return response()->json($data,503);
                }
            }
            else
            {
                $data =  array("code"=>503,"status"=>"error","message"=>"There is a temporary outage with the selected provider. We are working closely with the {$validateMMPaymentProvider->paymentProviderName} Team to see that the outage is resolved as quickly as possible.");
                return response()->json($data,503);
            }
        }
        else
        {
            $data =  array("code"=>400,"status"=>"error","message"=>"The MSISDN/Phone Number specified does not match any supported mobile money providers. Please ensure that the phone number is in international format.");
            return response()->json($data,400);
        }
    }
    /**
     * mobileMoneyCheckTransactionStatus() - This operation is used to get the status of a transaction.
     **/
    public function getTransferStatus(): \Illuminate\Http\JsonResponse
    {
        $transactionController =  new transaction();
        return $transactionController->checkDisbursementStatus($this->TransactionReference,$this->api_user);
    }
    public function getAllTransfers(): \Illuminate\Http\JsonResponse
    {
        $transactionController =  new transaction();
        return $transactionController->getAllDisbursements($this->api_user,$this->start_date,$this->end_date,$this->transaction_status,$this->result_set_limit,$this->originatorIP);
    }
    public function getOVACollectionsBalance(): \Illuminate\Http\JsonResponse
    {
        $mobileMoney = new mobileMoney();
        $network = $mobileMoney->returnPaymentProviderId($this->network);
        if($network === 1)
        {
            $MoMoAPI = new Disbursements(env('MTN_HOST'),env('MTN_ENV'));
            $MoMoAPI->setToken($this->getMTNMoMoAPIAccessToken(1,2));
            $MoMoAPI->setOcpApimSubscriptionKey(env('MTN_DISBURSEMENTS_SUBSCRIPTION_KEY'));
            $response = $MoMoAPI->getAccountBalance();

            if($response->httpResponseCode == 200)
            {
                return response()->json(array('code'=>200,'status'=>'success',"balance"=>array("wallet"=>"collections","availableBalance"=>$response->serverResponse->availableBalance,"provider"=>"MTN Mobile Money")),200);
            }
            else
            {
                return response()->json(array('code'=>400,'status'=>'error',"message"=>"Telecom returned unspecified error"),400);
            }
        }
        else if($network == 2)
        {
            return response()->json(array("400","status"=>"error","message"=>"Airtel Not yet configured"));
        }
        else
        {
            return response()->json(array("400","status"=>"error","message"=>"Network not supported"));
        }
    }
    public function getOVADisbursementBalance(): \Illuminate\Http\JsonResponse
    {
        $mobileMoney = new mobileMoney();
        $network = $mobileMoney->returnPaymentProviderId($this->network);
        if($network === 1)
        {
            $MoMoAPI = new Disbursements(env('MTN_HOST'),env('MTN_ENV'));
            $MoMoAPI->setToken($this->getMTNMoMoAPIAccessToken(1,2));
            $MoMoAPI->setOcpApimSubscriptionKey(env('MTN_DISBURSEMENTS_SUBSCRIPTION_KEY'));
            $response = $MoMoAPI->getAccountBalance();

            if($response->httpResponseCode == 200)
            {
                return response()->json(array('code'=>200,'status'=>'success',"balance"=>array("wallet"=>"disbursements","availableBalance"=>$response->serverResponse->availableBalance,"provider"=>"MTN Mobile Money")),200);
            }
            else
            {
                return response()->json(array('code'=>400,'status'=>'error',"message"=>"Telecom returned unspecified error"),400);
            }
        }
        else if($network == 2)
        {
            return response()->json(array("400","status"=>"error","message"=>"Airtel Not yet configured"));
        }
        else
        {
            return response()->json(array("400","status"=>"error","message"=>"Network not supported"));
        }
    }
    private function getMTNMoMoAPIAccessToken($provider_id,$productId)
    {
        $matches = ['provider_id'=>$provider_id,'product_id'=>$productId];
        return APIAccessTokens::where($matches)->whereDate('expires_','>','CURRENT_TIMESTAMP')->first()->access_token;
    }
    private function env($key,$default = null)
    {
        if(function_exists('parse_ini_file'))
        {
            if(file_exists('../.env')) {
                $env = (object) parse_ini_file(__DIR__.'/.env',FALSE,INI_SCANNER_RAW);
                if (property_exists($env, $key) === false) {return $default;}
                return $env->$key;
            }

        }
        else
        {
            die('function parse_ini_file does not exist');
        }
    }
}

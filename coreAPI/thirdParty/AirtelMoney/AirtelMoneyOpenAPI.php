<?php


use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\BadResponseException;


class AirtelMoneyOpenAPI
{
    private string $mobile;
    private float $amount;
    private string $externalTransactionReference;
    private string $internalTransactionRef;
    private string $transactionToken;

    private object $client;
    private string $airtelMoneyPrivateKey = <<<PK
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCkq3XbDI1s8Lu7SpUBP+bqOs/MC6PKWz6n/0UkqTiOZqKqaoZClI3BUDTrSIJsrN1Qx7ivBzsaAYfsB0CygSSWay4iyUcnMVEDrNVOJwtWvHxpyWJC5RfKBrweW9b8klFa/CfKRtkK730apy0Kxjg+7fF0tB4O3Ic9Gxuv4pFkbQIDAQAB
-----END PUBLIC KEY-----
PK;

    public function __construct()
    {
        //require_once "/vendor/autoload.php";

        $this->client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $this->env('AIRTEL_OPENAPI_BASE_URL'),
            'debug' => false,
            'verify'=> false,
            'connect_timeout'=> 30,
            'timeout'=> 60,
            'protocols'=> ['http', 'https'],
            'http_errors' => false
        ]);
    }

    public function setMobile($mobileNumber): void
    {
        $this->mobile=substr($mobileNumber, -9);
    }
    public function setAmount($amount): void
    {
        $this->amount=floor($amount);
    }
    public function setInternalTransactionReference($reference): void
    {
        $this->internalTransactionRef = trim(str_replace(array('#','@','?','%'),'',$reference));
    }
    public function setExternalTransactionReference($reference): void
    {
        $this->externalTransactionReference = trim(str_replace(array('#','@','?','%'),'',$reference));
    }
    /**
     * Set Token (Authorization header used for Basic authentication and oauth) needed for the Request Header
     *
     * @param $token
     * @return void
     */
    public function setTransactionToken($token): void
    {
        $this->transactionToken = trim($token);
    }
    /**
     * This operation is used to create an access token which can then be used to authorize and authenticate towards the other end-points of the API.
     */
    public function getToken(): object
    {
        try {
            $response = $this->client->request('POST','/auth/oauth2/token', [
                'json' => ["client_id" => $this->env('AIRTEL_OPENAPI_CLIENT_ID'),"client_secret" => $this->env('AIRTEL_OPENAPI_CLIENT_SECRET'),"grant_type" => $this->env('AIRTEL_OPENAPI_GRANT_TYPE')],
                'debug'=>false,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'     => 'application/json',
                ],
            ]);

            $token = json_decode($response->getBody()->getContents());

            if ($response->getStatusCode() == 200)
            {
                return  (object) array("status"=>200,"access_token"=>$token->access_token,"expires_in"=>$token->expires_in);
            }
            else
            {
                return  (object) array("status"=>$response->getStatusCode(),/*"message"=>$token->error_description*/);
            }
        }
        catch (ConnectException|RequestException|BadResponseException|TooManyRedirectsException|ClientException|ServerException $e)
        {
            return  (object) array("status"=>500,"message"=>$e->getMessage());
        }
        catch (GuzzleException $e)
        {
            return  (object) array("status"=>500,"message"=>$e->getMessage());
        }
    }
    /**
     * This operation is used to request a payment from a consumer (Payer). The payer will be asked to authorize the payment.
     * The transaction will be executed once the payer has authorized the payment. The requestToPay will be in status PENDING until the transaction is authorized or declined by the payer, or it is timed out by the system.
     */
    public function requestToPay()
    {
        $resourceEndPoint = "/merchant/v1/payments/";
        $requestBody = array
        (
            "reference"  => $this->externalTransactionReference,
            "subscriber" => array (

                "msisdn"=> $this->mobile
            ),
            "transaction"=> array
            (
                "amount"=> $this->amount,
                "id"=> $this->internalTransactionRef
            )
        );

        return $this->sendRequest('POST', $resourceEndPoint, $requestBody);
    }
    public function checkCollectionStatus()
    {
        $resourceEndPoint = "/standard/v1/payments/{$this->internalTransactionRef}";
        $requestBody = [];
        return $this->sendRequest('GET', $resourceEndPoint, $requestBody);
    }
    public function requestToDisburse()
    {
        $resourceEndPoint = "/standard/v1/disbursements/";
        $requestBody = array
        (
            "payee"  => array (

                "msisdn"=> $this->mobile
            ),
            "reference" => $this->externalTransactionReference,
            "pin" => $this->encryptPIN(),
            "transaction"=> array
            (
                "amount"=> $this->amount,
                "id"=> $this->internalTransactionRef,
                "type"=>"B2C"
            )
        );
        return $this->sendRequest('POST', $resourceEndPoint, $requestBody);
    }
    public function checkDisbursementStatus()
    {
        $resourceEndPoint = "/standard/v1/disbursements/{$this->internalTransactionRef}?transactionType=B2C";
        $requestBody = [];
        return $this->sendRequest('GET', $resourceEndPoint, $requestBody);
    }
    public function customerKYC()
    {
        $resourceEndPoint = "/standard/v1/users/{$this->mobile}";
        $requestBody = [];
        return $this->sendRequest('GET', $resourceEndPoint, $requestBody);
    }
    public function checkAccountBalance()
    {
        $resourceEndPoint = "/standard/v1/users/balance";
        $requestBody = [];
        return $this->sendRequest('GET', $resourceEndPoint, $requestBody);
    }
    private function sendRequest(string $method,string $resource, array $body)
    {
        try {
            $response = $this->client->request($method,$resource, [
                'json' => $body,
                'debug'=>false,
                'headers' => [
                    'Content-Type'=> "application/json",
                    'Accept'     => "application/json",
                    'X-Country'  =>  $this->env('AIRTEL_OPENAPI_X_COUNTRY'),
                    'X-Currency'  =>  $this->env('AIRTEL_OPENAPI_X_CURRENCY'),
                    'Authorization' => "Bearer {$this->transactionToken}",
                ],
            ]);

            return json_decode($response->getBody()->getContents());
        }
        catch (ConnectException|RequestException|BadResponseException|TooManyRedirectsException|ClientException|ServerException $e)
        {
            return $e->getMessage();
        }
        catch (GuzzleException $e)
        {
            return  $e->getMessage();
        }
    }
    private function encryptPIN()
    {
        $publicKey = openssl_pkey_get_public(array($this->airtelMoneyPrivateKey, ""));
        if (!$publicKey) {die ("Public key NOT Correct");}
        if (!openssl_public_encrypt($this->env('AIRTEL_OPENAPI_PIN'), $encryptedPIN, $publicKey)) {die("Error encrypting PIN using the Public key");}
        return base64_encode($encryptedPIN);
    }
    private function env($key)
    {
        // The project's .env lives three levels up (coreAPI/thirdParty/AirtelMoney -> project root).
        $envFile = dirname(__DIR__, 3).'/.env';
        if(function_exists('parse_ini_file'))
        {
            if(file_exists($envFile)) {

                $env = (object) parse_ini_file($envFile,FALSE,INI_SCANNER_RAW);
                if (property_exists($env, $key) === false)
                {
                    return null;
                }
                return $env->$key;
            }

        }
        die('function parse_ini_file does not exist');
    }
}

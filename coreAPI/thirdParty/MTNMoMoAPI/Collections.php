<?php
namespace MTNMoMoAPI;
use stdClass;

class Collections
{

    public $amount;
    public $externalId;
    public $currency;
    public $partyIdType = "MSISDN";
    public $partyId;
    public $payerMessage;
    public $payeeNote;

    private $_authorizationToken;
    private $_host;
    private $_env;
    private $_protocol;
    private $_apiBaseURL;
    private $_resourceEndPoint;
    private $_requestBody;

    private $_OcpApimSubscriptionKey;
    private $_XReferenceId;
    private $_callBackURL;

    public function __construct($host, $env, $baseUrl ="/collection/v1_0", $protocol = 'https')
    {
        $this->_host = $host;
        $this->_apiBaseURL = $baseUrl;
        $this->_env = $env;
        $this->_protocol = $protocol;
    }

    /**
     * TThis operation is used to create an access token which can then be used to authorize and authenticate towards the other end-points of the API.
     * @return array
     */
    public function getToken($APIUsername,$APIPassword,$collectionsSubscriptionKey)
    {
        $this->_resourceEndPoint = "/collection/token/";
        $headers = array('Content-Type: application/json', 'X-Target-Environment: '. $this->_env, 'Authorization: Basic '. base64_encode($APIUsername.':'.$APIPassword),'Ocp-Apim-Subscription-Key: ' .$collectionsSubscriptionKey);

        $curl = curl_init( );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, "{$this->_protocol}://{$this->_host}{$this->_resourceEndPoint}");
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array()));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30 );
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10 );

        $response = new stdClass();
        $response->serverResponse = json_decode(curl_exec($curl));
        $curl_info = curl_getinfo($curl);
        $response->httpResponseCode = $curl_info['http_code'];

        curl_close($curl );
        return $response;
    }

    /**
     * This operation is used to request a payment from a consumer (Payer). The payer will be asked to authorize the payment.
     * The transaction will be executed once the payer has authorized the payment. The requesttopay will be in status PENDING until the transaction is authorized or declined by the payer or it is timed out by the system. Status of the transaction can be validated by using the GET /requesttopay/<resourceId>
     *
     * @return array
     */
    public function requestToPay()
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/requesttopay";
        $this->_requestBody = array
        (
            "amount"    => $this->amount,
            "currency"  => $this->currency,
            "externalId" => $this->externalId,
            "payer"=> array
            (
                "partyIdType"=> $this->partyIdType,
                "partyId"=> "256".substr($this->partyId, -9),
            ),
            "payerMessage"=> preg_replace("/[^A-Za-z0-9 -]/", '', $this->payerMessage),
            "payeeNote"=> preg_replace("/[^A-Za-z0-9 -]/", '', $this->payeeNote)
        );
        return $this->sendAPIRequest('POST',$this->_resourceEndPoint,json_encode($this->_requestBody));
    }
    /**
     * This operation is used to get the status of a request to pay. X-Reference-Id that was passed in the post is used as reference to the request
     * @param $referenceId;
     * @return array
     */
    public function checkTransactionStatus($referenceId)
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/requesttopay/{$referenceId}";
        return $this->sendAPIRequest('GET',$this->_resourceEndPoint);
    }


    /**
     * Get the balance of the account.
     * @param $referenceId;
     * @return array
     */
    public function getAccountBalance()
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/account/balance";
        return $this->sendAPIRequest('GET',$this->_resourceEndPoint);
    }

    /**
     * This Operation is used to check if an account holder is registered and active in the system.
     * @param string $accountHolderIdType;
     * @param $accountHolderId
     * @return array
     */
    public function checkSubscriberStatus($accountHolderId, string $accountHolderIdType="msisdn")
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/accountholder/{$accountHolderIdType}/{$accountHolderId}/active";
        return $this->sendAPIRequest('GET',$this->_resourceEndPoint);
    }

    /**
     * This Operation is used to get basic info of the account holder.
     * @param $accountHolderId
     * @param string $accountHolderIdType ;
     * @return stdClass
     */
    public function checkSubscriberBasicInfo($accountHolderId, string $accountHolderIdType="msisdn"): stdClass
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/accountholder/{$accountHolderIdType}/{$accountHolderId}/basicuserinfo";
        return $this->sendAPIRequest('GET',$this->_resourceEndPoint);
    }
    /**
     * Set Token (Authorization header used for Basic authentication and oauth) needed for the Request Header
     *
     * @return void
     */
    public function setToken($token)
    {
        $this->_authorizationToken = trim($token);
    }
    /**
     * Set Ocp-Apim-Subscription-Key (Collections Primary Subscription Key) needed for the Request Header
     *
     * @return void
     */
    public function setOcpApimSubscriptionKey($OcpApimSubscriptionKey)
    {
        $this->_OcpApimSubscriptionKey = trim($OcpApimSubscriptionKey);
    }
    /**
     * Set X-Reference-Id (Payment ID) needed for the Request Header
     *
     * @return void
     */
    public function setXReferenceId($XReferenceId)
    {
        $this->_XReferenceId = trim($XReferenceId);
    }
    /**
     * Set setCallBackURL (URL to the server where the callback should be sent) needed for the Request Header.
     *
     * @return void
     */
    public function setCallBackURL($callbackUrl)
    {
        $this->_callBackURL = trim($callbackUrl);
    }
    /**
     * Set list of headers needed for request
     *
     * @return array
     */
    private function _setHeaders()
    {
        $headers = array(
            'Content-Type:application/json',
            'X-Target-Environment: '. $this->_env,
            'Authorization: Bearer '. $this->_authorizationToken,
        );
        if($this->_OcpApimSubscriptionKey)
        {
            $headers[] = 'Ocp-Apim-Subscription-Key: ' .$this->_OcpApimSubscriptionKey;
        }
        if($this->_XReferenceId)
        {
            $headers[] = 'X-Reference-Id: ' .$this->_XReferenceId;
        }
        if((isset($this->_callBackURL)) &&  (filter_var($this->_callBackURL, FILTER_VALIDATE_URL)))
        {
            $headers[] = 'X-Callback-Url: '.$this->_callBackURL;
        }
        return $headers;
    }

    /**
     * Perform API request
     *
     * @param string $method,$endPoint,$postBody
     * @return object
     */
    private function sendAPIRequest($method,$endPoint,$postBody=NULL)
    {
        $ch = curl_init( );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->_setHeaders());
        curl_setopt($ch, CURLOPT_URL, "{$this->_protocol}://{$this->_host}{$endPoint}");
        $method == "POST" ? curl_setopt($ch, CURLOPT_POST, true) : curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        isset($postBody) ? curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody) :'';
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        // Allow cUrl functions 30 seconds to execute
        curl_setopt($ch, CURLOPT_TIMEOUT, 30 );
        // Wait 10 seconds while trying to connect
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10 );
        //$output = curl_exec( $ch );
        //curl_close($ch);
        //return json_decode($output);
        $output = new stdClass();
        $output->serverResponse = json_decode(curl_exec($ch));
        $curl_info = curl_getinfo($ch);
        $output->httpResponseCode = $curl_info['http_code'];
        //$output->httpError = curl_error($ch);
        curl_close( $ch );
        return $output;
    }
}


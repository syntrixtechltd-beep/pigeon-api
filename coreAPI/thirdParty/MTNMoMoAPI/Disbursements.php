<?php
/*
 * **** ZengaPay MTN Momo API Sandbox Details *****
 *
 */

namespace MTNMoMoAPI;
use stdClass;

class Disbursements
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

    public function __construct($host,$env, $baseUrl ="/disbursement/v1_0", $protocol = 'https')
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
    public function getToken($APIUsername,$APIPassword,$disbursementSubscriptionKey)
    {
        $this->_resourceEndPoint = "/disbursement/token/";

        $headers = array('Content-Type: application/json', 'X-Target-Environment: '. $this->_env, 'Authorization: Basic '. base64_encode($APIUsername.':'.$APIPassword),'Ocp-Apim-Subscription-Key: ' .$disbursementSubscriptionKey);

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
     * The Transfer Method is used to transfer an amount from the owner’s account to a payee account. Status of the transaction can be validated by using the GET /transfer/{referenceId}
     *
     * @return array
     */
    public function transfer()
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/transfer";
        $this->_requestBody = array
        (
            "amount"    => $this->amount,
            "currency"  => $this->currency,
            "externalId" => $this->externalId,
            "payee"=> array
                (
                    "partyIdType"=> $this->partyIdType,
                    "partyId"=> "256".substr($this->partyId, -9),
            ),
            "payerMessage"=> preg_replace("/[^A-Za-z0-9 ]/", '', $this->payerMessage),
            "payeeNote"=> preg_replace("/[^A-Za-z0-9 ]/", '', $this->payeeNote)
        );
        return $this->sendAPIRequest('POST',$this->_resourceEndPoint,json_encode($this->_requestBody));
    }
    /**
     * This operation is used to get the status of a transfer. X-Reference-Id that was passed in the post is used as a reference to the request.
     * @param $referenceId;
     * @return array
     */
    public function checkTransferStatus($referenceId)
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/transfer/{$referenceId}";
        return $this->sendAPIRequest('GET',$this->_resourceEndPoint);
    }

    /**
     * Get the balance of the account.
     * @return object
     */
    public function getAccountBalance()
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/account/balance";
        return $this->sendAPIRequest('GET',$this->_resourceEndPoint);
    }
    /**
     * This Operation is used to check if an account holder is registered and active in the system.
     * @param $accountHolderIdType;
     * @param $accountHolderId
     * @return array
     */
    public function checkSubscriberStatus($accountHolderId,$accountHolderIdType="msisdn")
    {
        $this->_resourceEndPoint = "{$this->_apiBaseURL}/accountholder/{$accountHolderIdType}/{$accountHolderId}/active";
        return $this->sendAPIRequest('GET',$this->_resourceEndPoint);
    }
    /**
     * Set Token (Authorization header used for Basic authentication and oauth) needed for the Request Header
     *
     * @return array
     */
    public function setToken($token)
    {
        $this->_authorizationToken = trim($token);
    }
    /**
     * Set Ocp-Apim-Subscription-Key (Collections Primary Subscription Key) needed for the Request Header
     *
     * @return array
     */
    public function setOcpApimSubscriptionKey($OcpApimSubscriptionKey)
    {
        $this->_OcpApimSubscriptionKey = trim($OcpApimSubscriptionKey);
    }
    /**
     * Set X-Reference-Id (Payment ID) needed for the Request Header
     *
     * @return array
     */
    public function setXReferenceId($XReferenceId)
    {
        $this->_XReferenceId = trim($XReferenceId);
    }
    /**
     * Set setCallBackURL (URL to the server where the callback should be sent) needed for the Request Header.
     *
     * @return boolean
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
        $output = new stdClass();
        $output->serverResponse = json_decode(curl_exec($ch));
        $curl_info = curl_getinfo($ch);
        $output->httpResponseCode = $curl_info['http_code'];
        //$output->httpError = curl_error($ch);
        curl_close( $ch );
        return $output;
    }
}

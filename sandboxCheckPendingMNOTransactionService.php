<?php
set_time_limit(0);
date_default_timezone_set('Africa/Kampala');

require __DIR__.'/vendor/autoload.php';
require __DIR__ . '/coreAPI/thirdParty/MTNMoMoAPI/Collections.php';
require __DIR__ . '/coreAPI/thirdParty/MTNMoMoAPI/Disbursements.php';
require __DIR__ .'/coreAPI/thirdParty/AirtelMoney/AirtelMoneyOpenAPI.php';

use zengapay\MTNMoMoAPI\Collections;
use zengapay\MTNMoMoAPI\Disbursements;
use PHPMailer\PHPMailer\PHPMailer;
use Ramsey\Uuid\Uuid;

class sandboxCheckPendingMNOTransactionService
{
    public string $webHookAccountName;
    public function __construct()
    {
        @$this->db = new mysqli($this->env('DB_HOST'), $this->env('DB_USERNAME'), $this->env('DB_PASSWORD'), $this->env('DB_DATABASE'));
        //
        if ($this->db->connect_error) {
            die("<div style='color: #a94442;background-color: #f2dede;border-color: #ebccd1;border: 1px solid transparent;font-family:monospace; width:60%; margin:auto;padding:15px 25px;text-align: center;'><strong>MYSQLi ERROR : </strong>" . $this->db->connect_error . "</div>");
        } else {
            $this->db->set_charset('utf8');
        }
    }
    public function env($key,$default = null)
    {
        if(function_exists('parse_ini_file'))
        {
            if(file_exists(__DIR__.'/.env')) {
                $env = (object) parse_ini_file(__DIR__.'/.env',FALSE,INI_SCANNER_RAW);
                if (property_exists($env, $key) === false) {return $default;}
                return $env->$key;
            }

        }
        die('function parse_ini_file does not exist');
    }
    public function checkPendingMNOTransactions()
    {
        $i = 0;
        while(true)
        {

            @$qry = $this->db->query("SELECT *,time_to_sec(timediff(CURRENT_TIMESTAMP,initiationDate))AS timePassed FROM transactions WHERE initiationDate >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND transactionStatus = 1 AND transactionSpecificTransactionTypeId NOT IN (7,8) ORDER BY id ASC");
            if($qry->num_rows > 0)
            {
                while(@$row = @$qry->fetch_object())
                {

                    $i++;
                    $_id                                    = $row->id;
                    $_transactionMobileOperatorRef          = $row->transactionMobileOperatorRef;
                    $_transactionPaymentProviderId          = $row->transactionPaymentProviderId;
                    $_transactionSpecificTransactionTypeId  = $row->transactionSpecificTransactionTypeId;
                    $api_user_id                            = $row->api_user_id;

                    $completionDate = date('Y-m-d H:i:s');
                    $account = $this->_getApiUserInfo($api_user_id);
                    $accountPreferences = $this->_getApiUserPreferences($api_user_id);
                    $this->webHookAccountName = strtoupper($account->username);
                    $emailDate = date('d<\s\u\p\>S</\s\u\p\> M, Y H:i:s');
                    /////////////////
                    if(($_transactionPaymentProviderId == 1 ) || ($_transactionPaymentProviderId == 2 )) // MTN & Airtel Collections & Disbursement transactions
                    {
                        if($_transactionSpecificTransactionTypeId == 1)  // MTN Collections
                        {

                            if($row->account == $this->env('FAIL_NUMBER'))
                            {
                                @$sql = "UPDATE transactions SET transactionStatus=3,completionDate='{$completionDate}' WHERE id='{$_id}'";
                                if(!$this->db->query($sql)){if($this->db->errno) die($this->db->error);}
                                else
                                {
                                    if(($row->transactionOriginatorChannel == 1) && ($accountPreferences->sendWebHookTransactionFailed == true))
                                    {
                                        if(filter_var($accountPreferences->webHookUrl, FILTER_VALIDATE_URL)) {if($this->db->query("UPDATE transactions SET callbackRequested=true WHERE id='{$_id}'")){$this->writeWebHookRequestLog(sprintf("%s | Successfully Queued Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}else{$this->writeWebHookRequestLog(sprintf("%s | Failed to Queue Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}}
                                    }
                                }
                            }
                            else
                            {
                                if($row->transactionMobileOperatorRef == NULL)
                                {
                                    $MoMoAPI =  new \MTNMoMoAPI\Collections($this->env('MTN_HOST'),$this->env('MTN_ENV'));
                                    $MOMOTransactionReferenceId = Uuid::uuid4();
                                    $MoMoAPI->currency = $this->env('MTN_CURRENCY');
                                    $MoMoAPI->amount = $row->amount;
                                    $MoMoAPI->externalId = $row->transactionReference;
                                    $MoMoAPI->partyId   = $row->account;
                                    $MoMoAPI->payerMessage = $row->transactionNarrative;
                                    $MoMoAPI->payeeNote = $row->transactionNarrative;


                                    $MoMoAPI->setXReferenceId($MOMOTransactionReferenceId);
                                    $MoMoAPI->setToken($this->getMTNMoMoAPIAccessToken(1));
                                    $MoMoAPI->setOcpApimSubscriptionKey($this->env('MTN_COLLECTIONS_SUBSCRIPTION_KEY'));

                                    $response = $MoMoAPI->requestToPay();

                                    if($response->httpResponseCode==202)
                                    {
                                        @$sql = "UPDATE transactions SET transactionMobileOperatorRef='{$MOMOTransactionReferenceId}' WHERE id='{$_id}' AND BINARY transactionReference = BINARY '{$row->transactionReference}'";
                                        if(!$this->db->query($sql)){}
                                    }
                                }
                                else if(isset($row->transactionMobileOperatorRef) && ($row->transactionMobileOperatorRef !== NULL))
                                {
                                    $MoMoAPI =  new \MTNMoMoAPI\Collections($this->env('MTN_HOST'),$this->env('MTN_ENV'));
                                    $MoMoAPI->setToken($this->getMTNMoMoAPIAccessToken(1));
                                    $MoMoAPI->setOcpApimSubscriptionKey($this->env('MTN_COLLECTIONS_SUBSCRIPTION_KEY'));
                                    $MoMoAPITransactionStatus = $MoMoAPI->checkTransactionStatus($_transactionMobileOperatorRef);
                                    if($MoMoAPITransactionStatus->httpResponseCode == 200 && $MoMoAPITransactionStatus->serverResponse->status=="SUCCESSFUL")
                                    {
                                        $sql = "UPDATE transactions SET transactionStatus=2,completionDate = '{$completionDate}',transactionMobileOperatorTransactionId = '{$MoMoAPITransactionStatus->serverResponse->financialTransactionId}',transactionDesignationId = 1 WHERE id='{$_id}'";
                                        if(!$this->db->query($sql)){if($this->db->errno) die($this->db->error);}
                                        else
                                        {

                                            $this->_updateAccountBalanceAfterTransaction($api_user_id,$row->amount,'CREDIT');
                                            if(($row->transactionOriginatorChannel == 1) && ($accountPreferences->sendWebHookTransactionSucceeded == true))
                                            {
                                                if(filter_var($accountPreferences->webHookUrl, FILTER_VALIDATE_URL)) {if($this->db->query("UPDATE transactions SET callbackRequested=true WHERE id='{$_id}'")){$this->writeWebHookRequestLog(sprintf("%s | Successfully Queued Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}else{$this->writeWebHookRequestLog(sprintf("%s | Failed to Queue Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}}
                                            }
                                        }
                                    }
                                    if(($MoMoAPITransactionStatus->httpResponseCode == 200) && ($MoMoAPITransactionStatus->serverResponse->status=="FAILED"))
                                    {

                                        @$sql = "UPDATE transactions SET transactionStatus=3,completionDate='{$completionDate}' WHERE id='{$_id}'";
                                        if(!$this->db->query($sql)){if($this->db->errno) die($this->db->error);}
                                        else
                                        {
                                            if(($row->transactionOriginatorChannel == 1) && ($accountPreferences->sendWebHookTransactionFailed == true))
                                            {
                                                if(filter_var($accountPreferences->webHookUrl, FILTER_VALIDATE_URL)) {if($this->db->query("UPDATE transactions SET callbackRequested=true WHERE id='{$_id}'")){$this->writeWebHookRequestLog(sprintf("%s | Successfully Queued Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}else{$this->writeWebHookRequestLog(sprintf("%s | Failed to Queue Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}}
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        else if($_transactionSpecificTransactionTypeId == 2) // MTN  Disbursements
                        {

                            if($row->account == $this->env('FAIL_NUMBER'))
                            {
                                @$sql = "UPDATE transactions SET transactionStatus=3,completionDate='{$completionDate}' WHERE id='{$_id}'";
                                if(($row->transactionOriginatorChannel==1) && ($accountPreferences->sendWebHookTransactionFailed == true))
                                {
                                    if(filter_var($accountPreferences->webHookUrl, FILTER_VALIDATE_URL)) {if($this->db->query("UPDATE transactions SET callbackRequested=true WHERE id='{$_id}'")){$this->writeWebHookRequestLog(sprintf("%s | Successfully Queued Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}else{$this->writeWebHookRequestLog(sprintf("%s | Failed to Queue Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}}
                                }
                            }
                            else
                            {
                                if($row->transactionMobileOperatorRef == NULL)
                                {
                                    $MTNMOMOAPIRequestPayOut =  new \MTNMoMoAPI\Disbursements($this->env('MTN_HOST'),$this->env('MTN_ENV'));
                                    $MOMOTransactionReferenceId = Uuid::uuid4();
                                    $MTNMOMOAPIRequestPayOut->currency = $this->env('MTN_CURRENCY');
                                    $MTNMOMOAPIRequestPayOut->amount = $row->amount;
                                    $MTNMOMOAPIRequestPayOut->externalId = $row->transactionReference;
                                    $MTNMOMOAPIRequestPayOut->partyId   = $row->account;
                                    $MTNMOMOAPIRequestPayOut->payerMessage = $row->transactionNarrative;
                                    $MTNMOMOAPIRequestPayOut->payeeNote = $row->transactionNarrative;

                                    $MTNMOMOAPIRequestPayOut->setToken($this->getMTNMoMoAPIAccessToken(2));
                                    $MTNMOMOAPIRequestPayOut->setXReferenceId($MOMOTransactionReferenceId);
                                    $MTNMOMOAPIRequestPayOut->setOcpApimSubscriptionKey($this->env('MTN_DISBURSEMENTS_SUBSCRIPTION_KEY'));

                                    $response = $MTNMOMOAPIRequestPayOut->transfer();

                                    if($response->httpResponseCode==202)
                                    {
                                        @$sql = "UPDATE transactions SET transactionMobileOperatorRef='{$MOMOTransactionReferenceId}' WHERE id='{$_id}' AND BINARY transactionReference = BINARY '{$row->transactionReference}'";
                                        if(!$this->db->query($sql)){}
                                    }

                                }
                                else if(isset($row->transactionMobileOperatorRef) && ($row->transactionMobileOperatorRef !== NULL))
                                {
                                    $MoMoAPI =  new \MTNMoMoAPI\Disbursements($this->env('MTN_HOST'),$this->env('MTN_ENV'));
                                    $MoMoAPI->setToken($this->getMTNMoMoAPIAccessToken(2));
                                    $MoMoAPI->setOcpApimSubscriptionKey($this->env('MTN_DISBURSEMENTS_SUBSCRIPTION_KEY'));
                                    $MoMoAPITransactionStatus = $MoMoAPI->checkTransferStatus($_transactionMobileOperatorRef);

                                    if(($MoMoAPITransactionStatus->httpResponseCode == 200) && ($MoMoAPITransactionStatus->serverResponse->status=="SUCCESSFUL"))
                                    {
                                        $sql = "UPDATE transactions SET transactionStatus=2,completionDate = '{$completionDate}',transactionMobileOperatorTransactionId = '{$MoMoAPITransactionStatus->serverResponse->financialTransactionId}',transactionDesignationId = 1 WHERE id='{$_id}'";

                                        if(!$this->db->query($sql))
                                        {
                                            if($this->db->errno) die($this->db->error);
                                        }
                                        else
                                        {
                                            $this->_updateAccountBalanceAfterTransaction($api_user_id,$row->amount,'DEBIT');
                                            if(($row->transactionOriginatorChannel==1) && ($accountPreferences->sendWebHookTransactionSucceeded == true))
                                            {
                                                if(filter_var($accountPreferences->webHookUrl, FILTER_VALIDATE_URL)) {if($this->db->query("UPDATE transactions SET callbackRequested=true WHERE id='{$_id}'")){$this->writeWebHookRequestLog(sprintf("%s | Successfully Queued Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}else{$this->writeWebHookRequestLog(sprintf("%s | Failed to Queue Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}}

                                            }
                                        }
                                    }
                                    else if(($MoMoAPITransactionStatus->httpResponseCode == 200) && ($MoMoAPITransactionStatus->serverResponse->status=="FAILED"))
                                    {
                                        @$sql = "UPDATE transactions SET transactionStatus=3,completionDate='{$completionDate}' WHERE id='{$_id}'";
                                        if(($row->transactionOriginatorChannel==1) && ($accountPreferences->sendWebHookTransactionFailed == true))
                                        {
                                            if(filter_var($accountPreferences->webHookUrl, FILTER_VALIDATE_URL)) {if($this->db->query("UPDATE transactions SET callbackRequested=true WHERE id='{$_id}'")){$this->writeWebHookRequestLog(sprintf("%s | Successfully Queued Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}else{$this->writeWebHookRequestLog(sprintf("%s | Failed to Queue Callback Request for Transaction: %s",$this->webHookAccountName,$row->transactionReference));}}
                                        }
                                    }
                                }
                            }

                        }

                    }
                }

            }
            $qry->free();
            $this->db->next_result();

            $this->getMTNMoMoAPIAccessToken(1);
            $this->getMTNMoMoAPIAccessToken(2);
            //$this->getAirtelMoneyOpenAPIAccessToken();

            @$this->db->query("UPDATE transactions SET transactionStatus = 3, completionDate = CURRENT_TIMESTAMP WHERE initiationDate <= DATE_SUB(NOW(), INTERVAL 6 MINUTE) AND transactionStatus = 1");

            sleep(2);
        }
    }

    /***
     * Get Account Preferences
     * @params: AccountChargeGroupId,transactionSpecificTypeId
     * @returns: chargeValue,chargeName,updated_
     **/
    private function _getApiUserPreferences($account_id)
    {
        $qry = $this->db->query("SELECT * FROM api_user_preferences WHERE BINARY api_user_id = BINARY '{$account_id}'");
        return $qry->fetch_object();
    }
    /***
     * Get Account Info
     * @params: AccountChargeGroupId,transactionSpecificTypeId
     * @returns: chargeValue,chargeName,updated_
     **/
    private function _getApiUserInfo($api_user_id)
    {
        $qry = $this->db->query("SELECT * FROM api_users WHERE id = '{$api_user_id}'");
        return $qry->fetch_object();
    }
    private function getAirtelMoneyOpenAPIAccessToken()
    {
        $qry = $this->db->query("SELECT access_token FROM pp_access_tokens WHERE provider_id = 2 AND product_id = 1 AND expires_ > CURRENT_TIMESTAMP");
        if($qry->num_rows > 0)
        {
            return $qry->fetch_object()->access_token;
        }
        else
        {
            $tokenRequest =  new AirtelMoneyOpenAPI();
            $tokenRequestResult = $tokenRequest->getToken();

            if($tokenRequestResult->status == 200)
            {
                $update = $this->db->query("INSERT INTO pp_access_tokens (provider_id,product_id,access_token,validity,expires_)VALUES('2','1','{$tokenRequestResult->access_token}','{$tokenRequestResult->expires_in}',(now() + INTERVAL 120 SECOND )) ON DUPLICATE KEY UPDATE provider_id='2',product_id='1',access_token='{$tokenRequestResult->access_token}',validity='{$tokenRequestResult->expires_in}',updated_ = CURRENT_TIMESTAMP, expires_=(now() + INTERVAL 120 SECOND )");
                if($update){return $tokenRequestResult->access_token;}else{die($this->db->error);}
            }
        }
    }
    private function getMTNMoMoAPIAccessToken($productId)
    {
        $qry = $this->db->query("SELECT access_token FROM pp_access_tokens WHERE provider_id = 1 AND product_id = '{$productId}' AND expires_ > CURRENT_TIMESTAMP");
        if($qry->num_rows > 0)
        {
            while($row = $qry->fetch_object())
            {
                $access_token = $row->access_token;
            }

            return $access_token;
        }
        else
        {
            if($productId == 1)//Collections
            {

                $tokenRequest =  new \MTNMoMoAPI\Collections($this->env('MTN_HOST'),$this->env('MTN_ENV'));
                $tokenRequestResult = $tokenRequest->getToken($this->env('MTN_COLLECTIONS_USERNAME'),$this->env('MTN_COLLECTIONS_PASSWORD'),$this->env('MTN_COLLECTIONS_SUBSCRIPTION_KEY'));

                if($tokenRequestResult->httpResponseCode == 200)
                {
                    $update = $this->db->query("INSERT INTO pp_access_tokens(provider_id,product_id,access_token,validity,expires_)VALUES('1','{$productId}','{$tokenRequestResult->serverResponse->access_token}','{$tokenRequestResult->serverResponse->expires_in}',(now() + INTERVAL 55 MINUTE)) ON DUPLICATE KEY UPDATE provider_id='1',product_id='{$productId}',access_token='{$tokenRequestResult->serverResponse->access_token}',validity='{$tokenRequestResult->serverResponse->expires_in}',updated_ = CURRENT_TIMESTAMP, expires_=(now() + INTERVAL 55 MINUTE)");
                    if($update){return $tokenRequestResult->serverResponse->access_token;}else{die($this->db->error);}
                }

            }
            elseif($productId == 2)//Disbursements
            {
                $tokenRequest =  new \MTNMoMoAPI\Disbursements($this->env('MTN_HOST'),$this->env('MTN_ENV'));
                $tokenRequestResult = $tokenRequest->getToken($this->env('MTN_DISBURSEMENTS_USERNAME'),$this->env('MTN_DISBURSEMENTS_PASSWORD'),$this->env('MTN_DISBURSEMENTS_SUBSCRIPTION_KEY'));

                if($tokenRequestResult->httpResponseCode == 200)
                {
                    $update = $this->db->query("INSERT INTO pp_access_tokens(provider_id,product_id,access_token,validity,expires_)VALUES('1','{$productId}','{$tokenRequestResult->serverResponse->access_token}','{$tokenRequestResult->serverResponse->expires_in}',(now() + INTERVAL 55 MINUTE)) ON DUPLICATE KEY UPDATE provider_id='1',product_id='{$productId}',access_token='{$tokenRequestResult->serverResponse->access_token}',validity='{$tokenRequestResult->serverResponse->expires_in}',updated_ = CURRENT_TIMESTAMP, expires_=(now() + INTERVAL 55 MINUTE)");
                    if($update){return $tokenRequestResult->serverResponse->access_token;}else{die($this->db->error);}
                }
            }
        }
    }
    private function _getBalanceBeforeTransaction($account_id)
    {
        $qry = $this->db->query("SELECT IFNULL( (SELECT balance FROM account_balances WHERE accountId = '{$account_id}') ,0) AS balanceBeforeTransaction");
        $data = $qry->fetch_object();return $data->balanceBeforeTransaction;
    }
    private function _updateAccountBalanceAfterTransaction($account_id,$transactionAmount,$transactionType="CREDIT"): void
    {
        $balance = match($transactionType) {"CREDIT" => $this->_getBalanceBeforeTransaction($account_id) + $transactionAmount,"DEBIT" => $this->_getBalanceBeforeTransaction($account_id) - $transactionAmount};
        $this->db->query("INSERT INTO account_balances(accountId,balance)VALUES('{$account_id}','{$balance}') ON DUPLICATE KEY UPDATE balance='{$balance}', updated_=CURRENT_TIMESTAMP");
    }
    private function writeWebHookRequestLog($message)
    {
        $outf = fopen($this->env('WEBHOOK_FILE'), "a");
        fwrite($outf, date("dS M, Y H:i:s")." | ".$message."\n");
        fclose($outf);
    }
    private function writeMNOResponseLog($message)
    {
        $outf = fopen('/tmp/mnoResponses.log', "a");
        fwrite($outf, date("dS M, Y H:i:s")." | ".$message."\n");
        fclose($outf);
    }
}

$app = new sandboxCheckPendingMNOTransactionService();
$app->checkPendingMNOTransactions();

<?php
set_time_limit(0);
date_default_timezone_set('Africa/Kampala');

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\BadResponseException;
class checkPendingWebhooks
{
    public string $webhookAccountName;
    public string $webhookSecret;
    public array $webhookPayload;
    public string $webhookUrl;

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
    public function processPendingWebhooks()
    {
        while(true)
        {
            @$qry = $this->db->query("SELECT * FROM transactions WHERE callbackRequested = true AND callbackProcessed = false AND completionDate >= DATE_SUB(NOW(), INTERVAL 48 HOUR) AND transactionSpecificTransactionTypeId NOT IN (7,8) ORDER BY id ASC");
            if($qry->num_rows > 0) {
                while (@$transaction = @$qry->fetch_object()) {

                    $account = $this->_getApiUserInfo($transaction->api_user_id);
                    $accountPreferences = $this->_getApiUserPreferences($transaction->api_user_id);

                    $this->webhookAccountName = strtoupper($account->username);
                    $this->webhookUrl = $accountPreferences->webHookUrl;

                    if($transaction->transactionSpecificTransactionTypeId == 1)
                    {
                        $webHookSQL = $this->db->query("select `transactionReference` as `transactionReference`, `transactions_status`.`name` as `transactionStatus`, `amount`, `account`, `transactions_OriginatorChannels`.`name` as `channel`, payment_methods.name AS paymentMethod,payment_providers.providerServiceName AS paymentProvider, `transactionMobileOperatorTransactionId` as `MNOTransactionReferenceId`, `privateTransactionReference` as `transactionExternalReference`, `transactionNarrative` as `transactionExternalNarrative`, `initiationDate` as `transactionInitiationDate`, `completionDate` as `transactionCompletionDate`, `transactions_GeneralTransactionTypes`.`name` as `transactionEntryGeneralType`, `transactions_transactionDesignation`.`name` as `transactionEntryDesignation`, `transactions_SpecificTransactionTypes`.`name` as `transactionEntrySpecificType` from `transactions` inner join `transactions_status` on `transactions_status`.`id` = `transactions`.`transactionStatus` inner join `transactions_SpecificTransactionTypes` on `transactions_SpecificTransactionTypes`.`id` = `transactions`.`transactionSpecificTransactionTypeId` inner join `transactions_OriginatorChannels` on `transactions_OriginatorChannels`.`id` = `transactions`.`transactionOriginatorChannel` inner join `transactions_transactionDesignation` on `transactions_transactionDesignation`.`id` = `transactions`.`transactionDesignationId` inner join `transactions_GeneralTransactionTypes` on `transactions_GeneralTransactionTypes`.`id` = `transactions`.`transactionGeneralTransactionTypeId`            LEFT JOIN payment_providers ON payment_providers.id = transactions.transactionPaymentProviderId LEFT JOIN payment_methods ON payment_methods.id = payment_providers.paymentMethod where (`transactionReference` = '{$transaction->transactionReference}' and `api_user_id` = '{$transaction->api_user_id}')");
                        $webHookQryResult = $webHookSQL->fetch_array(MYSQLI_ASSOC);

                        if($transaction->transactionStatus == 2)
                        {
                            $this->webhookSecret  = hash_hmac('sha256', "{$transaction->transactionReference}{$transaction->account}{$webHookQryResult['amount']}", $accountPreferences->webHookSecret);
                            $this->webhookPayload = array("event"=>"collection.success","data"=>$webHookQryResult);

                            if($this->guzzlePostWebhook()){$this->db->query("UPDATE transactions SET callbackProcessed = true WHERE id = '{$transaction->id}'");}
                        }
                        elseif ($transaction->transactionStatus == 3)
                        {
                            $this->webhookSecret  = hash_hmac('sha256', "{$transaction->transactionReference}{$transaction->account}{$webHookQryResult['amount']}", $accountPreferences->webHookSecret);
                            $this->webhookPayload = array("event"=>"collection.failed","data"=>$webHookQryResult);

                            if($this->guzzlePostWebhook()){$this->db->query("UPDATE transactions SET callbackProcessed = true WHERE id = '{$transaction->id}'");}
                        }
                    }
                    else if($transaction->transactionSpecificTransactionTypeId == 2)
                    {
                        $webHookSQL = $this->db->query("select `transactionReference` as `transactionReference`, `transactions_status`.`name` as `transactionStatus`, `amount`, `account`, `transactions_OriginatorChannels`.`name` as `channel`, payment_methods.name AS paymentMethod,payment_providers.providerServiceName AS paymentProvider,  `transactionMobileOperatorTransactionId` as `MNOTransactionReferenceId`, `privateTransactionReference` as `transactionExternalReference`, `transactionNarrative` as `transactionExternalNarrative`, `initiationDate` as `transactionInitiationDate`, `completionDate` as `transactionCompletionDate`, `transactions_GeneralTransactionTypes`.`name` as `transactionEntryGeneralType`, `transactions_transactionDesignation`.`name` as `transactionEntryDesignation`, `transactions_SpecificTransactionTypes`.`name` as `transactionEntrySpecificType` from `transactions` inner join `transactions_status` on `transactions_status`.`id` = `transactions`.`transactionStatus` inner join payment_providers ON payment_providers.id = transactions.transactionPaymentProviderId inner join payment_methods ON payment_methods.id = payment_providers.paymentMethod inner join `transactions_SpecificTransactionTypes` on `transactions_SpecificTransactionTypes`.`id` = `transactions`.`transactionSpecificTransactionTypeId` inner join `transactions_OriginatorChannels` on `transactions_OriginatorChannels`.`id` = `transactions`.`transactionOriginatorChannel` inner join `transactions_transactionDesignation` on `transactions_transactionDesignation`.`id` = `transactions`.`transactionDesignationId` inner join `transactions_GeneralTransactionTypes` on `transactions_GeneralTransactionTypes`.`id` = `transactions`.`transactionGeneralTransactionTypeId` where (`transactionReference` = '{$transaction->transactionReference}' and `api_user_id` = '{$transaction->api_user_id}')");
                        $webHookQryResult = $webHookSQL->fetch_array(MYSQLI_ASSOC);

                        if($transaction->transactionStatus == 2)
                        {
                            $this->webhookSecret  = hash_hmac('sha256', "{$transaction->transactionReference}{$transaction->account}{$webHookQryResult['amount']}", $accountPreferences->webHookSecret);
                            $this->webhookPayload = array("event"=>"disbursement.success","data"=>$webHookQryResult);

                            if($this->guzzlePostWebhook()){$this->db->query("UPDATE transactions SET callbackProcessed = true WHERE id = '{$transaction->id}'");}
                        }
                        elseif ($transaction->transactionStatus == 3)
                        {
                            $this->webhookSecret  = hash_hmac('sha256', "{$transaction->transactionReference}{$transaction->account}{$webHookQryResult['amount']}", $accountPreferences->webHookSecret);
                            $this->webhookPayload = array("event"=>"disbursement.failed","data"=>$webHookQryResult);

                            if($this->guzzlePostWebhook()){$this->db->query("UPDATE transactions SET callbackProcessed = true WHERE id = '{$transaction->id}'");}
                        }
                    }

                }
            }
            $qry->free();
            $this->db->next_result();
            sleep(2);
        }
    }
    private function guzzlePostWebhook()
    {
        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory());

        $client = new Client([
            'handler' => $stack,
            'debug' => false,
            'verify'=> true,
            'connect_timeout'=> 30,
            'timeout'=> 55,
            'protocols'=> ['http', 'https'],
            'headers' => [ 'User-Agent' => 'PIGEON/1.0'],
            'http_errors' => false,
        ]);

        try
        {
            $response = $client->post($this->webhookUrl, [
                'json' => $this->webhookPayload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'     => 'application/json',
                    'X-SIGNATURE' => $this->webhookSecret,
                ],
                'debug'=>false,
                // Retry options
                'default_retry_multiplier' => 2.5,
                'retry_on_status_other_than' => [200,201,202],
                'retry_on_timeout' => false,
                'max_retry_attempts' => 5,
                'on_retry_callback'  => function($attemptNumber, $delay, &$request, &$options, $response)
                {
                    $this->writeWebHookLog(sprintf(
                        $this->webhookAccountName." | Retrying callback to: %s | Secret: %s | Data : %s | Server responded with: %s (%s).  Will wait %s seconds. | This is attempt #%s",
                        $request->getUri(),
                        $this->webhookSecret,
                        $request->getBody(),
                        $response->getStatusCode(),
                        $response->getReasonPhrase(),
                        number_format($delay, 2),
                        $attemptNumber
                    ));
                }
            ]);
            if(in_array($response->getStatusCode(),[200,201,202]))
            {
                $this->writeWebHookLog($this->webhookAccountName." | Callback submitted to: {$this->webhookUrl} | secret: {$this->webhookSecret} | Data : ".json_encode($this->webhookPayload)." | HTTP Response Code: {$response->getStatusCode()}");
            }
            else
            {
                $this->writeWebHookLog($this->webhookAccountName." | Failed to submit callback to: {$this->webhookUrl} | secret: {$this->webhookSecret} | Data : ".json_encode($this->webhookPayload)." | {$response->getStatusCode()} ({$response->getReasonPhrase()})");
            }
            return true;
        }
        catch (ConnectException|RequestException|BadResponseException|TooManyRedirectsException|ClientException|ServerException $e)
        {
            $this->writeWebHookLog($this->webhookAccountName." | Failed submit callback to: {$this->webhookUrl} | secret: {$this->webhookSecret} | Data : ".json_encode($this->webhookPayload)." | {$e->getMessage()}");
            return true;
        }
        catch (\GuzzleHttp\Exception\GuzzleException $e) {
        }
    }
    /***
     * Get Account Info
     * @params: AccountChargeGroupId,transactionSpecificTypeId
     * @returns: chargeValue,chargeName,updated_
     **/
    private function _getApiUserInfo($account_id):object
    {
        $qry = $this->db->query("SELECT * FROM api_users WHERE id = '{$account_id}'");
        return $qry->fetch_object();
    }
    /***
     * Get Account Preferences
     * @params: AccountChargeGroupId,transactionSpecificTypeId
     * @returns: chargeValue,chargeName,updated_
     **/
    private function _getApiUserPreferences($api_user_id):object
    {
        $qry = $this->db->query("SELECT * FROM api_user_preferences WHERE BINARY api_user_id = BINARY '{$api_user_id}'");
        return $qry->fetch_object();
    }
    private function writeWebHookLog($message): void
    {
        $outf = fopen($this->env('WEBHOOK_FILE'), "a");
        fwrite($outf, date("dS M, Y H:i:s")." | ".$message."\n\n");
        fclose($outf);
    }
}

$webhooks = new checkPendingWebhooks();
$webhooks->processPendingWebhooks();

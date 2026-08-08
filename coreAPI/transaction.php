<?php
namespace gatewayInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use App\Transactions;
use App\PaymentProviders;
use App\TransactionStatus;

class transaction
{
    public function initiateMobileMoneyPaymentRequest($api_user,$msisdn,$paymentProviderId,$amount,$external_reference,$narration,$originatorIP,$originatorChannel): array
    {
        $TransactionReference = Uuid::uuid5(Uuid::NAMESPACE_DNS, "{$api_user}{$msisdn}{$amount}{$external_reference}{$narration}");
        try {
            $transaction = Transactions::create([
                'api_user_id'=>$api_user,
                'amount'=>$amount,
                'account'=>$msisdn,
                'transactionReference'=>$TransactionReference,
                'privateTransactionReference'=>$external_reference,
                'transactionNarrative'=>$narration,
                'transactionSpecificTransactionTypeId'=>1,
                'transactionPaymentProviderId'=>$paymentProviderId,
                'transactionOriginatorIdentification'=>$originatorIP,
                'transactionOriginatorDescription'=>'API request initiated via remote IP: '.$originatorIP,
                'transactionOriginatorChannel'=>$originatorChannel,
                'transactionStatus'=>1,
            ]);
            return array("success"=>true,"transactionReference"=>$transaction->transactionReference,"_transactionId"=>$transaction->id,'amount'=>$amount);
        }
        catch (QueryException $e)
        {
            if($e->errorInfo[0] == 23000)
            {
                return array("success"=>false,"message"=>"This is likely a duplicate transaction. Please vary your submission parameters such as the external reference and narration. The possibly related transaction's reference is '{$TransactionReference}'");
            }
            else
            {
                return array("success"=>false,"message"=>$e->errorInfo[2]);
            }
        }
    }
    public function updateMobileMoneyPaymentTransactionRef($zpTransactionReference,$transactionMobileOperatorRef): bool
    {
        try
        {
            Transactions::where('transactionReference',$zpTransactionReference)->update(['transactionMobileOperatorRef'=>$transactionMobileOperatorRef]);
            return true;
        }
        catch (QueryException $e)
        {
            return false;
        }
    }
    public function getAllCollections($api_user_id,$start_date,$end_date,$transaction_status,$result_set_limit): \Illuminate\Http\JsonResponse
    {
        $matches = ['api_user_id' => $api_user_id,'transactionDesignationId'=>1];
        $transaction = Transactions::join('transactions_status','transactions_status.id','=','transactions.transactionStatus')
            ->join('payment_provider_currencies','payment_provider_currencies.paymentProviderId','=','transactions.transactionPaymentProviderId')
            ->join('payment_providers','payment_providers.id','=','transactions.transactionPaymentProviderId')
            ->join('payment_methods','payment_methods.id','=','payment_providers.paymentMethod')
            ->join('transactions_SpecificTransactionTypes','transactions_SpecificTransactionTypes.id','=','transactions.transactionSpecificTransactionTypeId')
            ->join('transactions_OriginatorChannels','transactions_OriginatorChannels.id','=','transactions.transactionOriginatorChannel')
            ->join('transactions_transactionDesignation','transactions_transactionDesignation.id','=','transactions.transactionDesignationId')
            ->join('transactions_GeneralTransactionTypes','transactions_GeneralTransactionTypes.id','=','transactions.transactionGeneralTransactionTypeId')
            ->where($matches)
            ->whereIntegerInRaw('transactionSpecificTransactionTypeId',array(1,3))
            ->whereBetween('completionDate', [$start_date,$end_date]);

        if(isset($transaction_status) && strlen($transaction_status) > 0){$transaction->where('transactionStatus',$this->getTransactionStatusId($transaction_status));}

        $q = $transaction->select(
            'transactionReference AS transactionReference',
            'transactions_status.name AS transactionStatus',
            'transactions.amount',
            'transactions.account',
            'payment_methods.name AS paymentMethod',
            'transactions_OriginatorChannels.name AS paymentChannel',
            'payment_providers.providerServiceName AS paymentProvider',
            'transactionOriginatorIdentification AS transactionOriginatorIP',
            'transactionMobileOperatorTransactionId AS MNOTransactionReferenceId',
            'privateTransactionReference AS transactionExternalReference',
            'transactionNarrative AS transactionExternalNarration',
            'initiationDate AS transactionInitiationDate',
            'completionDate AS transactionCompletionDate',
            'transactions_GeneralTransactionTypes.name AS transactionEntryGeneralType',
            'transactions_transactionDesignation.name AS transactionEntryDesignation',
            'transactions_SpecificTransactionTypes.name AS transactionEntrySpecificType')
            ->orderBy('transactions.id', 'desc')
            ->paginate($result_set_limit);
        $data = array('code'=>200,'status'=>'success',"collections"=>$q);
        return response()->json($data,200);

    }

    /**
     * @param $TransactionReference
     * @param $api_user_id
     * @return JsonResponse
     */
    public function checkCollectionStatus($TransactionReference,$api_user_id): \Illuminate\Http\JsonResponse
    {
        if(isset($TransactionReference))
        {
            $matches = ['transactionReference' =>$TransactionReference,'api_user_id' => $api_user_id,'transactionDesignationId'=>1];
            $transaction = Transactions::join('transactions_status','transactions_status.id','=','transactions.transactionStatus')
                ->join('payment_provider_currencies','payment_provider_currencies.paymentProviderId','=','transactions.transactionPaymentProviderId')
                ->join('payment_providers','payment_providers.id','=','transactions.transactionPaymentProviderId')
                ->join('payment_methods','payment_methods.id','=','payment_providers.paymentMethod')
                ->join('transactions_SpecificTransactionTypes','transactions_SpecificTransactionTypes.id','=','transactions.transactionSpecificTransactionTypeId')
                ->join('transactions_OriginatorChannels','transactions_OriginatorChannels.id','=','transactions.transactionOriginatorChannel')
                ->join('transactions_transactionDesignation','transactions_transactionDesignation.id','=','transactions.transactionDesignationId')
                ->join('transactions_GeneralTransactionTypes','transactions_GeneralTransactionTypes.id','=','transactions.transactionGeneralTransactionTypeId')
                ->where($matches)
                ->whereIntegerInRaw('transactions.transactionSpecificTransactionTypeId',array(1,3))
                ->select(
                    'transactionReference AS transactionReference',
                    'transactions_status.name AS transactionStatus',
                    'transactions.amount',
                    'transactions.account',
                    'payment_methods.name AS paymentMethod',
                    'transactions_OriginatorChannels.name AS paymentChannel',
                    'payment_providers.providerServiceName AS paymentProvider',
                    'transactionOriginatorIdentification AS transactionOriginatorIP',
                    'transactionMobileOperatorTransactionId AS MNOTransactionReferenceId',
                    'privateTransactionReference AS transactionExternalReference',
                    'transactionNarrative AS transactionExternalNarration',
                    'initiationDate AS transactionInitiationDate',
                    'completionDate AS transactionCompletionDate',
                    'transactions_GeneralTransactionTypes.name AS transactionEntryGeneralType',
                    'transactions_transactionDesignation.name AS transactionEntryDesignation',
                    'transactions_SpecificTransactionTypes.name AS transactionEntrySpecificType')
                ->first();
            //->toSql();
            if($transaction)
            {
                return response()->json(array('code'=>200,'status'=>'success',"data"=>$transaction),200);
            }
            else
            {
                return response()->json(array('code'=>'404','status'=>'error','message'=>"A Collection Request with transactionReference '{$TransactionReference}' not found. Please cross check your entries and try again."),404);
            }
        }
        else
        {
            return response()->json(array('code'=>'422','status'=>'error','message'=>'transactionReference is required'),422);
        }

    }
    public function preCheckTransferBalance($account_id,$chargeGroupId,$paymentProviderId,$amount): array
    {

        $zengaPayDisbursementFees = $this->calculateZengaPayDisbursementFees($amount);
        $mnoPayoutCharges = $this->getNetworkBulkCharges(2,$paymentProviderId);
        $minimumRequiredTransactionBalance =  $zengaPayDisbursementFees+$mnoPayoutCharges+$amount;

        $matches = ['accountId' => $account_id];
        $balance = AccountBalances::where($matches)->first();
        $count = $balance->count();
        if($count >= 1)
        {
            if($balance->amount >= $minimumRequiredTransactionBalance)
            {
                return array("sufficient"=>true,"amount"=>$amount,"charges"=>array("zengapay"=>$zengaPayDisbursementFees,"telecom"=>$mnoPayoutCharges,"total"=>$zengaPayDisbursementFees+$mnoPayoutCharges));
            }
            else
            {
                return array('sufficient'=>false,"charges"=>array("zengapay"=>$zengaPayDisbursementFees,"telecom"=>$mnoPayoutCharges,"total"=>$zengaPayDisbursementFees+$mnoPayoutCharges),"message"=>"Insufficient account balance");
            }
        }
        else
        {
            return array('sufficient'=>false,"charges"=>array("zengapay"=>$zengaPayDisbursementFees,"telecom"=>$mnoPayoutCharges,"total"=>$zengaPayDisbursementFees+$mnoPayoutCharges),"message"=>"Insufficient account balance");
        }
    }
    public function initiateMobileMoneyDisbursement($api_user_id,$msisdn,$paymentProviderId,$amount,$external_reference,$narration,$originatorIP,$originatorChannel)
    {
        $TransactionReference = Uuid::uuid5(Uuid::NAMESPACE_DNS, "{$api_user_id}{$msisdn}{$amount}{$external_reference}{$narration}");
        try {
            $transaction = Transactions::create([
                'api_user_id'=>$api_user_id,
                'amount'=>$amount,
                'account'=>$msisdn,
                'transactionReference'=>$TransactionReference,
                'privateTransactionReference'=>$external_reference,
                'transactionNarrative'=>$narration,
                'transactionGeneralTransactionTypeId'=>2,
                'transactionSpecificTransactionTypeId'=>2,
                'transactionPaymentProviderId'=>$paymentProviderId,
                'transactionOriginatorIdentification'=>$originatorIP,
                'transactionOriginatorDescription'=>'API request initiated by remote server: '.$originatorIP,
                'transactionOriginatorChannel'=>$originatorChannel,
                'transactionMobileOperatorRef'=>$TransactionReference,
                'transactionStatus'=>1,
            ]);
            return array("success"=>true,"transactionReference"=>$transaction->transactionReference,"_transactionId"=>$transaction->id);
        }
        catch (QueryException $e)
        {
            if($e->errorInfo[0] == 23000)
            {
                return array("success"=>false,"message"=>"This is likely a duplicate transaction. Please vary your submission parameters such as the external reference and narration. The possibly related transaction's reference is '{$TransactionReference}'");
            }
            else{ print_r($e->errorInfo);}
        }
    }
    public function checkDisbursementStatus($TransactionReference,$api_user_id): \Illuminate\Http\JsonResponse
    {
        if(isset($TransactionReference))
        {
            $matches = ['transactionReference' =>$TransactionReference,'api_user_id' => $api_user_id,'transactionDesignationId'=>1];
            $transaction = Transactions::join('transactions_status','transactions_status.id','=','transactions.transactionStatus')
                ->join('payment_provider_currencies','payment_provider_currencies.paymentProviderId','=','transactions.transactionPaymentProviderId')
                ->join('payment_providers','payment_providers.id','=','transactions.transactionPaymentProviderId')
                ->join('payment_methods','payment_methods.id','=','payment_providers.paymentMethod')
                ->join('transactions_SpecificTransactionTypes','transactions_SpecificTransactionTypes.id','=','transactions.transactionSpecificTransactionTypeId')
                ->join('transactions_OriginatorChannels','transactions_OriginatorChannels.id','=','transactions.transactionOriginatorChannel')
                ->join('transactions_transactionDesignation','transactions_transactionDesignation.id','=','transactions.transactionDesignationId')
                ->join('transactions_GeneralTransactionTypes','transactions_GeneralTransactionTypes.id','=','transactions.transactionGeneralTransactionTypeId')
                ->where($matches)
                ->whereIntegerInRaw('transactions.transactionSpecificTransactionTypeId',array(2,4))
                ->select(
                    'transactionReference AS transactionReference',
                    'transactions_status.name AS transactionStatus',
                    'transactions.amount',
                    'transactions.account',
                    'payment_methods.name AS paymentMethod',
                    'transactions_OriginatorChannels.name AS paymentChannel',
                    'payment_providers.providerServiceName AS paymentProvider',
                    'transactionOriginatorIdentification AS transactionOriginatorIP',
                    'transactionMobileOperatorTransactionId AS MNOTransactionReferenceId',
                    'privateTransactionReference AS transactionExternalReference',
                    'transactionNarrative AS transactionExternalNarration',
                    'initiationDate AS transactionInitiationDate',
                    'completionDate AS transactionCompletionDate',
                    'transactions_GeneralTransactionTypes.name AS transactionEntryGeneralType',
                    'transactions_transactionDesignation.name AS transactionEntryDesignation',
                    'transactions_SpecificTransactionTypes.name AS transactionEntrySpecificType')
                ->first();
            if($transaction)
            {

                $data = array('code'=>200,'status'=>'success',"data"=>$transaction);
                return response()->json($data,200);
            }
            else
            {
                return response()->json(array('code'=>'404','status'=>'error','message'=>"A Transfer Request with transactionReference '{$TransactionReference}' not found. Please cross check your entries and try again."),404);
            }
        }
        else
        {
            return response()->json(array('code'=>'422','status'=>'error','message'=>'transactionReference is required'),422);
        }

    }
    public function getAllDisbursements($api_user_id,$start_date,$end_date,$transaction_status,$result_set_limit,$originator_ip): \Illuminate\Http\JsonResponse
    {

        $matches = ['api_user_id' => $api_user_id,'transactionDesignationId'=> 1];
        $transaction = Transactions::join('transactions_status','transactions_status.id','=','transactions.transactionStatus')
            ->join('payment_provider_currencies','payment_provider_currencies.paymentProviderId','=','transactions.transactionPaymentProviderId')
            ->join('payment_providers','payment_providers.id','=','transactions.transactionPaymentProviderId')
            ->join('payment_methods','payment_methods.id','=','payment_providers.paymentMethod')
            ->join('transactions_SpecificTransactionTypes','transactions_SpecificTransactionTypes.id','=','transactions.transactionSpecificTransactionTypeId')
            ->join('transactions_OriginatorChannels','transactions_OriginatorChannels.id','=','transactions.transactionOriginatorChannel')
            ->join('transactions_transactionDesignation','transactions_transactionDesignation.id','=','transactions.transactionDesignationId')
            ->join('transactions_GeneralTransactionTypes','transactions_GeneralTransactionTypes.id','=','transactions.transactionGeneralTransactionTypeId')
            ->whereIntegerInRaw('transactions.transactionSpecificTransactionTypeId',array(2,4))
            ->where($matches)

            ->whereBetween('completionDate', [$start_date,$end_date]);
        if(isset($transaction_status) && strlen($transaction_status) > 0){$transaction->where('transactionStatus',$this->getTransactionStatusId($transaction_status));}
        if(isset($originator_ip) && strlen($originator_ip) > 0){$transaction->where('transactionOriginatorIdentification',$originator_ip);}

        $q = $transaction->select(
            'transactionReference AS transactionReference',
            'transactions_status.name AS transactionStatus',
            'transactions.amount',
            'transactions.account',
            'payment_methods.name AS paymentMethod',
            'transactions_OriginatorChannels.name AS paymentChannel',
            'payment_providers.providerServiceName AS paymentProvider',
            'transactionOriginatorIdentification AS transactionOriginatorIP',
            'transactionMobileOperatorTransactionId AS MNOTransactionReferenceId',
            'privateTransactionReference AS transactionExternalReference',
            'transactionNarrative AS transactionExternalNarration',
            'initiationDate AS transactionInitiationDate',
            'completionDate AS transactionCompletionDate',
            'transactions_GeneralTransactionTypes.name AS transactionEntryGeneralType',
            'transactions_transactionDesignation.name AS transactionEntryDesignation',
            'transactions_SpecificTransactionTypes.name AS transactionEntrySpecificType')
            ->orderBy('transactions.id', 'desc')//->toSql();
            ->paginate($result_set_limit);
        return response()->json(array('code'=>200,'status'=>'success',"disbursements"=>$q),200);

    }


    /***
     * Airtime
     */
    public function initiateAirtimeTransferRequest($accountNumber,$account_id,$msisdn,$paymentProviderId,$amount,$external_reference,$originatorIP,$originatorChannel)
    {
        $AccountBalance = AccountBalances::where(['accountId' => $account_id,])->sum('amount');
        if($AccountBalance >= $amount)
        {
            $TransactionReference = Uuid::uuid5(Uuid::NAMESPACE_DNS, "{$account_id}{$msisdn}{$amount}{$external_reference}");
            try {
                $transaction = Transactions::create([
                    'account_id'=>$account_id,
                    'amount'=>$amount,
                    'msisdn'=>$msisdn,
                    'zpTransactionReference'=>$TransactionReference,
                    'zpTransactionId'=>$this->createZPTransactionId(),
                    'privateTransactionReference'=>$external_reference,
                    'transactionNarrative'=>"Airtime transfer to {$msisdn}",
                    'transactionGeneralTransactionTypeId'=>2,
                    'transactionSpecificTransactionTypeId'=>7,
                    'transactionPaymentProviderId'=>$paymentProviderId,
                    'transactionOriginatorIdentification'=>$originatorIP,
                    'transactionOriginatorDescription'=>'API request initiated by remote server: '.$originatorIP,
                    'transactionOriginatorChannel'=>$originatorChannel,
                    'transactionMobileOperatorRef'=>$TransactionReference,
                    'transactionStatus'=>1,
                    'transactionFee'=>0
                ]);
                return array("success"=>true,"zpTransactionReference"=>$transaction->zpTransactionReference,"_transactionId"=>$transaction->id);
            }
            catch (QueryException $e)
            {
                if($e->errorInfo[0] == 23000)
                {
                    return array("success"=>false,"message"=>"This is likely a duplicate transaction. Please vary your submission parameters such as the external reference and amount. The possibly related transaction's reference is '{$TransactionReference}'");
                }
                else{ print_r($e->errorInfo);}
            }
        }
        else
        {
            return array('success'=>false,'balance'=>$AccountBalance,'message'=>"Your {$this->getProviderCurrencyName($paymentProviderId)} wallet balance is insufficient. Please recharge or convert from another supported wallet to top-up.");
        }
    }
    public function getSingleAirtimeTransfer($TransactionReference,$account_id): \Illuminate\Http\JsonResponse
    {
        if(isset($TransactionReference))
        {
            $matches = ['zpTransactionReference' =>$TransactionReference,'account_id' => $account_id,'transactionSpecificTransactionTypeId' =>7];

            $transaction = Transactions::join('transactions_status','transactions_status.id','=','transactions.transactionStatus')
                //->join('payment_provider_currencies','payment_provider_currencies.paymentProviderId','=','transactions.transactionPaymentProviderId')
                ->join('payment_providers','payment_providers.id','=','transactions.transactionPaymentProviderId')
                ->join('transactions_SpecificTransactionTypes','transactions_SpecificTransactionTypes.id','=','transactions.transactionSpecificTransactionTypeId')
                ->join('transactions_OriginatorChannels','transactions_OriginatorChannels.id','=','transactions.transactionOriginatorChannel')
                ->join('transactions_transactionDesignation','transactions_transactionDesignation.id','=','transactions.transactionDesignationId')
                ->join('transactions_GeneralTransactionTypes','transactions_GeneralTransactionTypes.id','=','transactions.transactionGeneralTransactionTypeId')
                ->where($matches)
                ->select(
                    'zpTransactionId AS transactionSystemId',
                    'zpTransactionReference AS transactionReference',
                    'transactions_status.name AS transactionStatus',
                    'amount',
                    'msisdn AS msisdn',
                    'transactions_OriginatorChannels.name AS channel',
                    'payment_providers.providerAccountCode AS network',
                    //'payment_provider_currencies.currencyName AS currencyName',
                    'transactionMobileOperatorTransactionId AS MNOTransactionReferenceId',
                    'privateTransactionReference AS transactionExternalReference',
                    'transactionNarrative AS transactionExternalNarration',
                    'initiationDate AS transactionInitiationDate',
                    'completionDate AS transactionCompletionDate',
                    'transactions_GeneralTransactionTypes.name AS transactionEntryGeneralType',
                    'transactions_transactionDesignation.name AS transactionEntryDesignation',
                    'transactions_SpecificTransactionTypes.name AS transactionEntrySpecificType')
                ->first();
            if($transaction)
            {

                $data = array('code'=>200,'status'=>'success',"data"=>$transaction);
                return response()->json($data,200);
            }
            else
            {
                return response()->json(array('code'=>'404','status'=>'error','message'=>"Airtime Transfer Request with reference '{$TransactionReference}' not found. Please cross check your entries and try again."),404);
            }
        }
        else
        {
            return response()->json(array('code'=>'422','status'=>'error','message'=>'transactionReference is required'),422);
        }

    }
    public function getAllAirtimeTransfers($account_id,$start_date,$end_date,$transaction_status,$result_set_limit): \Illuminate\Http\JsonResponse
    {
        $matches = ['transactionSpecificTransactionTypeId' =>7,'account_id' => $account_id];
        $transaction = Transactions::join('transactions_status','transactions_status.id','=','transactions.transactionStatus')
            //->join('payment_provider_currencies','payment_provider_currencies.paymentProviderId','=','transactions.transactionPaymentProviderId')
            ->join('payment_providers','payment_providers.id','=','transactions.transactionPaymentProviderId')
            ->join('transactions_SpecificTransactionTypes','transactions_SpecificTransactionTypes.id','=','transactions.transactionSpecificTransactionTypeId')
            ->join('transactions_OriginatorChannels','transactions_OriginatorChannels.id','=','transactions.transactionOriginatorChannel')
            ->join('transactions_transactionDesignation','transactions_transactionDesignation.id','=','transactions.transactionDesignationId')
            ->join('transactions_GeneralTransactionTypes','transactions_GeneralTransactionTypes.id','=','transactions.transactionGeneralTransactionTypeId')
            ->where($matches)
            ->whereBetween('completionDate', [$start_date,$end_date]);

        if(isset($transaction_status) && strlen($transaction_status) > 0){$transaction->where('transactionStatus',$this->getTransactionStatusId($transaction_status));}

        $q = $transaction->select(
            'zpTransactionId AS transactionSystemId',
            'zpTransactionReference AS transactionReference',
            'transactions_status.name AS transactionStatus',
            'amount',
            'msisdn AS msisdn',
            'transactions_OriginatorChannels.name AS channel',
            'payment_providers.providerAccountCode AS network',
            //'payment_provider_currencies.currencyName AS currencyName',
            'transactionMobileOperatorTransactionId AS MNOTransactionReferenceId',
            'privateTransactionReference AS transactionExternalReference',
            'transactionNarrative AS transactionExternalNarration',
            'initiationDate AS transactionInitiationDate',
            'completionDate AS transactionCompletionDate',
            'transactions_GeneralTransactionTypes.name AS transactionEntryGeneralType',
            'transactions_transactionDesignation.name AS transactionEntryDesignation',
            'transactions_SpecificTransactionTypes.name AS transactionEntrySpecificType')

            ->orderBy('transactions.id', 'desc')
            ->paginate($result_set_limit);
        $data = array('code'=>200,'status'=>'success',"statement"=>$q);
        return response()->json($data,200);

    }
    /***
     * Bundles
     */
    public function initiateBundleTransferRequest($account_id,$msisdn,$paymentProviderId,$amount,$bundleId,$external_reference,$originatorIP,$originatorChannel)
    {
        $AccountBalance = AccountBalances::where(['accountId' => $account_id])->sum('amount');
        if($AccountBalance >= $amount)
        {
            $TransactionReference = Uuid::uuid5(Uuid::NAMESPACE_DNS, "{$account_id}{$bundleId}{$msisdn}{$amount}{$external_reference}");
            try {
                $transaction = Transactions::create([
                    'account_id'=>$account_id,
                    'amount'=>$amount,
                    'msisdn'=>$msisdn,
                    'zpTransactionReference'=>$TransactionReference,
                    'zpTransactionId'=>$this->createZPTransactionId(),
                    'privateTransactionReference'=>$external_reference,
                    'transactionNarrative'=>"Mobile Data purchase for {$msisdn}",
                    'transactionGeneralTransactionTypeId'=>2,
                    'transactionSpecificTransactionTypeId'=>8,
                    'transactionPaymentProviderId'=>$paymentProviderId,
                    'transactionOriginatorIdentification'=>$originatorIP,
                    'transactionOriginatorDescription'=>'API request initiated by remote server: '.$originatorIP,
                    'transactionOriginatorChannel'=>$originatorChannel,
                    'transactionMobileOperatorRef'=>$TransactionReference,
                    'transactionStatus'=>1,
                    'vasBundleId'=>$bundleId,
                    'transactionFee'=>0
                ]);
                return array("success"=>true,"zpTransactionReference"=>$transaction->zpTransactionReference,"_transactionId"=>$transaction->id);
            }
            catch (QueryException $e)
            {
                if($e->errorInfo[0] == 23000)
                {
                    return array("success"=>false,"message"=>"This is likely a duplicate transaction. Please vary your submission parameters such as the external reference and amount. The possibly related transaction's reference is '{$TransactionReference}'");
                }
                else{ print_r($e->errorInfo);}
            }
        }
        else
        {
            return array('success'=>false,'balance'=>$AccountBalance,'message'=>"Your {$this->getProviderCurrencyName($paymentProviderId)} wallet balance is insufficient. Please recharge or convert from another supported wallet to top-up.");
        }
    }
    public function getAllDataBundleTransfers($account_id,$start_date,$end_date,$transaction_status,$result_set_limit): \Illuminate\Http\JsonResponse
    {
        $matches = ['transactionSpecificTransactionTypeId' =>8,'account_id' => $account_id];
        $transaction = Transactions::join('transactions_status','transactions_status.id','=','transactions.transactionStatus')
            ->join('vas_bundles','vas_bundles.id','=','transactions.vasBundleId')
            ->join('payment_providers','payment_providers.id','=','transactions.transactionPaymentProviderId')
            ->join('transactions_SpecificTransactionTypes','transactions_SpecificTransactionTypes.id','=','transactions.transactionSpecificTransactionTypeId')
            ->join('transactions_OriginatorChannels','transactions_OriginatorChannels.id','=','transactions.transactionOriginatorChannel')
            ->join('transactions_transactionDesignation','transactions_transactionDesignation.id','=','transactions.transactionDesignationId')
            ->join('transactions_GeneralTransactionTypes','transactions_GeneralTransactionTypes.id','=','transactions.transactionGeneralTransactionTypeId')
            ->where($matches)
            ->whereBetween('completionDate', [$start_date,$end_date]);

        if(isset($transaction_status) && strlen($transaction_status) > 0){$transaction->where('transactionStatus',$this->getTransactionStatusId($transaction_status));}

        $q = $transaction->select(
            'zpTransactionId AS transactionSystemId',
            'zpTransactionReference AS transactionReference',
            'transactions_status.name AS transactionStatus',
            'amount',
            'msisdn AS msisdn',
            'transactions_OriginatorChannels.name AS channel',
            'payment_providers.providerAccountCode AS network',
            'vas_bundles.name AS bundleName',
            'vas_bundles.validity AS bundleValidity',
            'transactionMobileOperatorTransactionId AS MNOTransactionReferenceId',
            'privateTransactionReference AS transactionExternalReference',
            'transactionNarrative AS transactionExternalNarration',
            'initiationDate AS transactionInitiationDate',
            'completionDate AS transactionCompletionDate',
            'transactions_GeneralTransactionTypes.name AS transactionEntryGeneralType',
            'transactions_transactionDesignation.name AS transactionEntryDesignation',
            'transactions_SpecificTransactionTypes.name AS transactionEntrySpecificType')

            ->orderBy('transactions.id', 'desc')
            ->paginate($result_set_limit);
        $data = array('code'=>200,'status'=>'success',"statement"=>$q);
        return response()->json($data,200);

    }
    public function getAllAvailableBundles($result_set_limit): \Illuminate\Http\JsonResponse
    {
        $bundles = VasBundles::join('vas_providers','vas_providers.id','=','vas_bundles.provider')
            ->select(
                'code AS bundleCode',
                'name AS bundleName',
                'price AS bundlePrice',
                'validity AS bundleValidity',
                'vas_providers.providerAccountCode AS network',
                'vas_providers.providerFullName AS provider'
            )
            ->orderBy('vas_bundles.id', 'asc')
            ->paginate($result_set_limit);
        $data = array('code'=>200,'status'=>'success',"bundles"=>$bundles);
        return response()->json($data,200);
    }
    public function getSingleBundle($bundle)
    {
        $matches = ['code' => $bundle];
        $bundle = VasBundles::where($matches)->first();
        return $bundle;
    }
    public function updateSuccessfulVASTransaction($account_id,$amount,$paymentProviderId,$zpTransactionReference,$zpTransactionId,$transactionMobileOperatorTransactionId,$transactionMobileOperatorResultCode,$transactionMobileOperatorResultMessage): bool
    {
        $balanceBefore = $this->_getBalanceBeforeTransaction($account_id);
        $this->_updateAccountBalanceAfterTransaction($account_id,$amount,"DEBIT");
        $balanceAfter =  $this->_getBalanceBeforeTransaction($account_id);

        try
        {
            Transactions::where('zpTransactionReference',$zpTransactionReference)
                ->update([
                    'transactionMobileOperatorTransactionId'=>$transactionMobileOperatorTransactionId,
                    'transactionMobileOperatorResultCode'=>$transactionMobileOperatorResultCode,
                    'transactionMobileOperatorResultMessage'=>$transactionMobileOperatorResultMessage,
                    'transactionStatus'=>2,
                    'completionDate' => DB::raw('CURRENT_TIMESTAMP'),
                    'balanceBeforeTransaction' => $balanceBefore,
                    'balanceAfterTransaction' => $balanceAfter,
                ]);
            $this->_updateVASCommissionAccountBalanceAfterTransaction(2,$paymentProviderId,$amount);
            return true;
        }
        catch (QueryException $e)
        {
            return false;
        }
    }
    public function updateFailedVASTransaction($account_id,$amount,$paymentProviderId,$zpTransactionReference,$transactionMobileOperatorTransactionId=NULL,$transactionMobileOperatorResultCode=NULL,$transactionMobileOperatorResultMessage=NULL): bool
    {
        try
        {
            Transactions::where('zpTransactionReference',$zpTransactionReference)
                ->update([
                    'transactionMobileOperatorTransactionId'=>$transactionMobileOperatorTransactionId,
                    'transactionMobileOperatorResultCode'=>$transactionMobileOperatorResultCode,
                    'transactionMobileOperatorResultMessage'=>$transactionMobileOperatorResultMessage,
                    'transactionStatus'=>3,
                    'completionDate' => DB::raw('CURRENT_TIMESTAMP'),
                ]);

            return true;
        }
        catch (QueryException $e)
        {
            return false;
        }
    }
    /**
     * @param $account_id
     * @return mixed
     */
    private function getAccountPreferences($account_id)
    {
        $matches = ['accountId' => $account_id];
        $preferences = AccountPreferences::where($matches)->first();
        return $preferences->chargeCustomerTransactionFees;
    }
    private function getChargeStructure($accountChargeGroupId,$transactionSpecificTypeId,$transactionPaymentProviderId)
    {
        $matches = ['chargeGroupId' =>$accountChargeGroupId,'transactionSpecificTypeId' => $transactionSpecificTypeId,'transactionProviderId'=>$transactionPaymentProviderId];
        $charges = AccountChargeStructure::where($matches)->get();
        return $charges[0];
    }
    private function getNetworkBulkCharges($transactionSpecificTypeId,$paymentProviderId)
    {
        $matches = ['transactionPaymentProviderId' =>$paymentProviderId,'transactionSpecificTypeId' => $transactionSpecificTypeId];
        $charges = AccountMNOChargeStructure::where($matches)->first();
        return $charges->chargeValue;
    }
    private function getProviderCurrencyName($paymentProviderId)
    {
        $matches = ['id' => $paymentProviderId];
        $preferences = PaymentProviders::where($matches)->get();
        return $preferences[0]->providerServiceName;
    }
    private function createZPTransactionId($prefix="ZPY",$suffix="TX"): string
    {
        $y=date('y');$m= date('m'); $d = date('d'); $h=date('H');
        $transactionId = $prefix.$y.$m.$d.$h.mt_rand(1000, 9999).$suffix;
        return $transactionId;
    }
    private function getTransactionStatusId($status)
    {
        $matches = ['name' => $status];
        $transactionStatus = TransactionStatus::where($matches)->get();
        if($transactionStatus->count() >= 1){return $transactionStatus[0]->id;}

    }
    private function _getBalanceBeforeTransaction($account_id)
    {
        $balance = DB::table('account_balances')
            ->select(DB::raw('amount AS balance'))
            ->where(['accountId'=>$account_id])
            ->first();
        return $balance->balance;
    }
    private function _updateAccountBalanceAfterTransaction($account_id,$transactionAmount,$transactionType="CREDIT"): void
    {

        $balance = match($transactionType) {"CREDIT" => $this->_getBalanceBeforeTransaction($account_id) + $transactionAmount,"DEBIT" => $this->_getBalanceBeforeTransaction($account_id) - $transactionAmount};
        DB::table('account_balances')
            ->updateOrInsert(
                ['accountId' => $account_id],
                ['amount' => $balance]
            );
    }

    private function processVASCommissionFees($commissionAccountId,$sourceAccountId,$sourceTransactionId,$sourceTransactionPaymentProviderId,$transactionNarrative,$amount,$amountRealized)
    {
        $transactionId = $this->createZPTransactionId();
        try {
            $query = VasCommissions::create([
                'accountId'=>$commissionAccountId,
                'transactionId'=>$transactionId,
                'sourceAccountId'=>$sourceAccountId,
                'sourceTransactionId'=>$sourceTransactionId,
                'sourceTransactionPaymentProviderId'=>$sourceTransactionPaymentProviderId,
                'transactionNarrative'=>$transactionNarrative,
                'amount'=>$amount]);
            $this->_updateVASCommissionAccountBalanceAfterTransaction($commissionAccountId,$sourceTransactionPaymentProviderId,$amountRealized);

            return true;
        }
        catch (QueryException $e)
        {
            print_r($e->errorInfo);
        }
    }
    private function _updateVASCommissionAccountBalanceAfterTransaction($commissionAccountId,$transactionPaymentProviderId,$transactionAmount,$transactionType="CREDIT"): void
    {
        $balance = match($transactionType) {"CREDIT" => $this->_getBalanceBeforeTransaction($commissionAccountId) + $transactionAmount,"DEBIT" => $this->_getBalanceBeforeTransaction($commissionAccountId) - $transactionAmount};
        DB::table('vas_commission_balances')
            ->updateOrInsert(
                ['paymentProviderId' => $transactionPaymentProviderId, 'accountId' => $commissionAccountId],
                ['amount' => $balance]
            );
    }
    private function _getVASCommissionBalanceBeforeTransaction($commissionAccountId,$transactionPaymentProviderId): float
    {

        $balance = DB::table('vas_commission_balances')
            ->select(DB::raw('amount AS balance'))
            ->where(['accountId'=>$commissionAccountId, 'paymentProviderId'=> $transactionPaymentProviderId])
            ->first();
        return (float) $balance->balance;
    }


    /***
     * INTERNAL TRANSFER
     ***/
    public function internalFundsTransfer($source_account_id,$source_accountNumber,$beneficiary_accountNumber,$amount,$transactionNarrative,$transactionExternalReference,$transactionChannel,$originatorIP,$paymentLinkId,$checkoutSessionId): \Illuminate\Http\JsonResponse
    {
        $resultCode = ''; $resultMessage="";

        if($source_accountNumber == $beneficiary_accountNumber)
        {
            return response()->json(array('code'=>'400','status'=>'error','message'=>'Same account transfers not supported'),400);
        }
        else
        {
            if($this->internalTransferGMerchantBalance($source_account_id) >= $amount)
            {
                $sourceZpTransactionReference   = Uuid::uuid5(Uuid::NAMESPACE_DNS, "{$source_accountNumber}{$beneficiary_accountNumber}{$amount}{$transactionExternalReference}{$transactionNarrative}");

                if(Transactions::where(['zpTransactionReference'=>$sourceZpTransactionReference])->exists())
                {
                    $resultCode      = 422;
                    $resultMessage   = array("code"=>422,"status"=>"error","message"=>"This is likely a duplicate transaction. Please vary your submission parameters such as the external reference and narration. The possibly related transaction's reference is '{$sourceZpTransactionReference}'");
                }
                else
                {
                    $sourceAccount                  = $this->internalTransferGetAccountByNumber($source_accountNumber);
                    $beneficiaryAccount             = $this->internalTransferGetAccountByNumber($beneficiary_accountNumber);
                    $beneficiaryZPTransactionId     = $this->createZPTransactionId();

                    $fees = $this->calculateTransactionFees($beneficiaryAccount->accountChargeGroupId,$amount,$beneficiaryZPTransactionId);

                    //Payment to {$beneficiaryAccount->accountName} ($transactionNarrative)
                    //Payment from {$sourceAccount->accountName} ($transactionNarrative)

                    $this->internalTransferDebitSourceAccount($source_account_id, $beneficiary_accountNumber,$beneficiaryAccount->accountName, $amount, $sourceZpTransactionReference, $transactionNarrative, $transactionExternalReference, $transactionChannel, $originatorIP);
                    $this->internalTransferCreditBeneficiaryAccount($beneficiaryAccount->id, $source_accountNumber,$sourceAccount->accountName, $amount, $beneficiaryZPTransactionId,$transactionNarrative, $transactionExternalReference, $transactionChannel, $originatorIP,$paymentLinkId,$checkoutSessionId,$fees->transactionFee);

                    if($this->InternalTransferProcessTransactionFees($beneficiaryAccount->id,$fees->transactionFee,$fees->transactionNarrative,$beneficiaryZPTransactionId,$fees->commissionTransactionNarration)->feesProcessed == true)
                    {
                        if($checkoutSessionId > 0){$this->updateCheckoutSessionStatus($checkoutSessionId);}

                        $resultCode    = 200;
                        $resultMessage = array("code"=>200,"status"=>"success","message"=>"Transaction successful","data"=>array("amount"=>$amount,"transactionReference"=>$sourceZpTransactionReference,"beneficiary"=>$beneficiaryAccount->accountName));
                    }
                }
            }
            else
            {
                $resultCode    = 400;
                $resultMessage = array('code'=>'400','status'=>'error','message'=>'Your account balance is insufficient for this transaction');
            }

            return response()->json($resultMessage,$resultCode);
        }
    }
    public function internalTransferValidateAccount($accountNumber): bool
    {
        if (Accounts::where(['accountNumber'=>$accountNumber,'accountType'=> 2])->count() > 0){return true;}else{return false;}
    }
    private function internalTransferGMerchantBalance($accountId): float
    {
        return (double) AccountBalances::select('amount')->where(['accountId'=> $accountId])->first()->amount;
    }


    private function internalTransferGetMerchantBalanceBeforeTransaction($accountId): float
    {
        return AccountBalances::select('amount')->where(['accountId'=> $accountId])->first()->amount ?? 0.00;
    }
    private function internalTransferGetMerchantBalanceAfterTransaction($accountId): float
    {
        return AccountBalances::select('amount')->where(['accountId'=> $accountId])->first()->amount ?? 0.00;
    }
    private function internalTransferUpdateMerchantAccountBalanceAfterTransaction($account_id,$transactionAmount,$transactionType="CREDIT"): void
    {
        $balance = match($transactionType) {"CREDIT" => $this->internalTransferGetMerchantBalanceBeforeTransaction($account_id) + $transactionAmount,"DEBIT" => $this->internalTransferGetMerchantBalanceAfterTransaction($account_id) - $transactionAmount};
        AccountBalances::upsert([['accountId' => $account_id, 'amount' => $balance]],['accountId' => $account_id],['amount' => $balance]);
    }

    private function internalTransferDebitSourceAccount($accountId,$beneficiaryAccountNumber,$beneficiaryAccountName,$amount,$sourceZpTransactionReference,$transactionNarrative,$transactionExternalReference,$transactionChannel,$originatorIP): void
    {
        $balanceBefore = $this->internalTransferGetMerchantBalanceBeforeTransaction($accountId);
        $this->internalTransferUpdateMerchantAccountBalanceAfterTransaction($accountId,$amount,"DEBIT");
        $balanceAfter =  $this->internalTransferGetMerchantBalanceAfterTransaction($accountId);
        try {Transactions::create([
            'account_id'=>$accountId,
            'amount'=>$amount,
            'zpTransactionReference'=>$sourceZpTransactionReference,
            'zpTransactionId'=>$this->createZPTransactionId(),
            'transactionNarrative'=>$transactionNarrative,
            'privateTransactionReference'=>$transactionExternalReference,
            'transactionGeneralTransactionTypeId'=>2,
            'transactionSpecificTransactionTypeId'=>4,
            'transactionDesignationId'=>1,
            'transactionStatus'=>2,
            'transactionOriginatorIdentification'=>$originatorIP,
            'transactionOriginatorDescription'=>'API request initiated by remote server: '.$originatorIP,
            'transactionMobileOperatorTransactionId'=>$this->internalTransferGenerateMNOReferenceNumber(),
            'transactionOriginatorChannel'=>$transactionChannel,
            'balanceBeforeTransaction'=>$balanceBefore,
            'balanceAfterTransaction'=>$balanceAfter,
            'transactionPaymentProviderId'=> 3,
            'msisdn'=>$beneficiaryAccountNumber,
            'beneficiary'=>$beneficiaryAccountName,
            'notify_merchant'=>true,
        ]);
            return;
        }
        catch (QueryException $e) {
            //return;
            die($e);
        }
    }
    private function internalTransferCreditBeneficiaryAccount($accountId, $sourceAccountNumber,$sourceAccountName, $amount, $zpTransactionId,$transactionNarrative, $transactionExternalReference, $transactionChannel, $originatorIP,$paymentLinkId,$checkoutSessionId,$transactionFee): void
    {
        $balanceBefore = $this->internalTransferGetMerchantBalanceBeforeTransaction($accountId);
        $this->internalTransferUpdateMerchantAccountBalanceAfterTransaction($accountId,$amount);
        $balanceAfter =  $this->internalTransferGetMerchantBalanceAfterTransaction($accountId);


        try {Transactions::create([
            'account_id'=>$accountId,
            'amount'=>$amount,
            'zpTransactionReference'=>Uuid::uuid4(),
            'zpTransactionId'=>$zpTransactionId,
            'transactionNarrative'=>$transactionNarrative,
            'privateTransactionReference'=>$transactionExternalReference,
            'transactionGeneralTransactionTypeId'=>1,
            'transactionSpecificTransactionTypeId'=>3,
            'transactionDesignationId'=>1,
            'transactionStatus'=>2,
            'transactionFee'=>$transactionFee,
            'transactionOriginatorIdentification'=>$originatorIP,
            'transactionOriginatorDescription'=>'API request initiated by remote server: '.$originatorIP,
            'transactionMobileOperatorTransactionId'=>$this->internalTransferGenerateMNOReferenceNumber(),
            'transactionOriginatorChannel'=>$transactionChannel,
            'balanceBeforeTransaction'=>$balanceBefore,
            'balanceAfterTransaction'=>$balanceAfter,
            'transactionPaymentProviderId'=> 3,
            'paymentLinkId'=>$paymentLinkId,
            'checkoutSessionId'=>$checkoutSessionId,
            'msisdn'=>$sourceAccountNumber,
            'beneficiary'=>$sourceAccountName,
            'notify_merchant'=>true,
        ]);
            return;
        }
        catch (QueryException $e) {
            //return;
            die($e);
        }
    }
    private function calculateTransactionFees($accountChargeGroupId,$transactionAmount,$parentTransactionRef): object
    {
        $accountChargeGroupData = $this->internalTransferGetAccountChargeStructure($accountChargeGroupId,1,3);
        // Collections Transaction Fees
        if(($transactionAmount >= 500) && ($transactionAmount <= 5000))
        {
            $transactionFee = 200;
            $transactionNarrative = "ZENGAPAY 200 FLAT RATE TRANSACTION FEE FOR TRANSACTION ID #{$parentTransactionRef}";
            $commissionTransactionNarration =  "ZENGAPAY 200 FLAT RATE TRANSACTION COMMISSION FOR TRANSACTION REF #{$parentTransactionRef}";
        }
        elseif(($transactionAmount >= 5001) && ($transactionAmount <= 25000))
        {
            $transactionFee = 600;
            $transactionNarrative = "ZENGAPAY 600 FLAT RATE TRANSACTION FEE TRANSACTION ID #{$parentTransactionRef}";
            $commissionTransactionNarration =  "ZENGAPAY 600 FLAT RATE TRANSACTION COMMISSION FOR TRANSACTION REF #{$parentTransactionRef}";
        }
        else
        {
            $transactionFee = $accountChargeGroupData->chargeValue/100 * $transactionAmount;
            $transactionNarrative = "ZENGAPAY {$accountChargeGroupData->chargeName} FOR TRANSACTION ID #{$parentTransactionRef}";
            $commissionTransactionNarration =  "ZENGAPAY {$accountChargeGroupData->chargeName} COMMISSION FOR TRANSACTION REF #{$parentTransactionRef}";
        }

        return (object) array("transactionFee"=>$transactionFee,"transactionNarrative"=>$transactionNarrative,"commissionTransactionNarration"=>$commissionTransactionNarration);
    }
    /***
     * Process Transaction Charges
     **/
    private function internalTransferProcessTransactionFees($account_id,$transactionFee,$transactionNarrative,$parentTransactionRef,$commissionTransactionNarration): object
    {
        /**
         *  Transaction Fees
         */

        $account = $this->internalTransferGetAccountInfo($account_id);


        $balanceBefore = $this->internalTransferGetMerchantBalanceBeforeTransaction($account_id);
        $this->internalTransferUpdateMerchantAccountBalanceAfterTransaction($account_id,$transactionFee,"DEBIT");
        $balanceAfter =  $this->internalTransferGetMerchantBalanceAfterTransaction($account_id);

        $TransactionReference = Uuid::uuid4();
        $zpTransactionId = $this->createZPTransactionId();


        try {Transactions::create([

            'account_id'=>$account_id,
            'amount'=>$transactionFee,
            'zpTransactionReference'=>$TransactionReference,
            'zpTransactionId'=>$zpTransactionId,
            'transactionNarrative'=>$transactionNarrative,
            'privateTransactionReference'=>$parentTransactionRef,
            'transactionGeneralTransactionTypeId'=>2,
            'transactionSpecificTransactionTypeId'=>4,
            'transactionDesignationId'=>2,
            'transactionStatus'=>2,
            'transactionOriginatorIdentification'=>'BACKEND_PROCESSOR',
            'transactionOriginatorDescription'=>NULL,
            'transactionMobileOperatorRef'=>NULL,
            'transactionOriginatorChannel'=>4,
            'balanceBeforeTransaction'=>$balanceBefore,
            'balanceAfterTransaction'=>$balanceAfter,
            'transactionPaymentProviderId'=>3,
            'parentTransactionRef'=>$parentTransactionRef,
            'msisdn'=>$account->accountNumber
        ]);
            $this->internalTransferProcessCommissionFees(1,$account_id,1,3,$commissionTransactionNarration,$transactionFee);
            return (object) array('feesProcessed'=>true,'fees'=>$transactionFee);
        }
        catch (QueryException $e) {return (object) array('feesProcessed'=>false);}
    }
    /***
     * Get Accounts Info
     * @params: AccountChargeGroupId,transactionSpecificTypeId
     * @returns: chargeValue,chargeName,updated_
     **/
    private function internalTransferGetAccountInfo($account_id)
    {
        return Accounts::where(['id'=>$account_id])->first();
    }
    private function internalTransferGetAccountByNumber($accountNumber)
    {
        return Accounts::select('id','accountName','kyc_verified','accountChargeGroupId')->where(['accountNumber'=> $accountNumber])->first();
    }
    /***
     * Get Accounts Charge Structures
     * @params: AccountChargeGroupId,transactionSpecificTypeId
     * @returns: chargeValue,chargeName,updated_
     **/
    public function internalTransferGetAccountChargeStructure($accountChargeGroupId,$transactionSpecificTypeId,$transactionProviderId)
    {
        return ChargeGroupFees::where(['chargeGroupId'=>$accountChargeGroupId,'transactionSpecificTypeId'=>$transactionSpecificTypeId,'transactionProviderId'=>$transactionProviderId])->first();
    }
    public function internalTransferProcessCommissionFees($commissionAccountId,$sourceAccountId,$sourceTransactionId,$sourceTransactionPaymentProviderId,$transactionNarrative,$transactionFees)
    {
        $transactionId = $this->createZPTransactionId();
        try{
            Commissions::create([
                'accountId'=>$commissionAccountId,
                'transactionId'=>$transactionId,
                'sourceAccountId'=>$sourceAccountId,
                'sourceTransactionId'=>$sourceTransactionId,
                'sourceTransactionPaymentProviderId'=>$sourceTransactionPaymentProviderId,
                'transactionNarrative'=>$transactionNarrative,
                'amount'=>$transactionFees,
            ]);
            $this->internalTransferUpdateCommissionAccountBalanceAfterTransaction($commissionAccountId, $sourceTransactionPaymentProviderId, $transactionFees);
            return true;
        }catch (QueryException $e){}
    }
    private function internalTransferUpdateCommissionAccountBalanceAfterTransaction($commissionAccountId, $transactionPaymentProviderId, $transactionAmount): void
    {
        $balance = match("CREDIT") {"CREDIT" => $this->internalTransferGetCommissionBalance($commissionAccountId,$transactionPaymentProviderId) + $transactionAmount,"DEBIT" => $this->internalTransferGetCommissionBalance($commissionAccountId,$transactionPaymentProviderId) - $transactionAmount};
        CommissionBalances::upsert([['accountId' => $commissionAccountId, 'paymentProviderId'=>$transactionPaymentProviderId,'amount' => $balance]],['accountId' => $commissionAccountId,'paymentProviderId'=>$transactionPaymentProviderId],['amount' => $balance]);
    }
    private function internalTransferGetCommissionBalance($commissionAccountId,$transactionPaymentProviderId): float
    {
        return CommissionBalances::select('amount')->where(['accountId'=> $commissionAccountId,'paymentProviderId'=>$transactionPaymentProviderId])->first()->amount ?? 0.00;
    }
    private function internalTransferGenerateMNOReferenceNumber(): string
    {
        return str_pad(transactions::max('id')+1,10,0,STR_PAD_LEFT);
    }
    private function updateCheckoutSessionStatus($checkoutSessionId): void
    {
        CheckoutSessions::where(['id' => $checkoutSessionId, 'status' => 1])->update(['status' => 2]);
    }
    private function calculateZengaPayDisbursementFees($amount): float
    {
        return match(true)
        {
            $amount >= 500  && $amount <= 5000 => 200,
            $amount >= 5001 && $amount <= 25000 => 600,
            default => 800
        };
    }
}

<?php
namespace App\Http\Controllers;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use gatewayInterface;
use Illuminate\Validation\ValidationException;

class CollectionsController extends Controller
{
    private $api_user;

    public function __construct()
    {
        if(Auth()->user()){$this->api_user = json_decode(Auth()->user())->id;}
    }

    /**
     * Request Mobile Money Payment from Client
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function requestCollection(Request $request): JsonResponse
    {
        $this->validate($request, [
            'type' => 'required|string|in:mm',
            'account' => 'required|digits:10',
            'amount' => 'required|numeric|min:500|max:5000000',
            'reference'=>'required|alpha_dash|between:4,50',
            'narration' => 'required|between:4,100',
        ]);

        @$gatewayInterface = new gatewayInterface();
        @$gatewayInterface->api_user                     = $this->api_user;
        @$gatewayInterface->msisdn                       = trim($request->input('account'));
        @$gatewayInterface->amount                       = trim($request->input('amount',500));
        @$gatewayInterface->external_reference           = trim($request->input('reference'));
        @$gatewayInterface->narration                    = trim($request->input('narration'));
        @$gatewayInterface->originatorIP                 = trim($request->input('ip',$this->getClientIpAddr())) ;
        @$gatewayInterface->originatorChannel            = trim($request->input('channel',1));

        return $gatewayInterface->mobileMoneyRequestToPay();
    }
    /**
     * @throws ValidationException
     */
    public function checkCollectionStatus(Request $request , $TransactionReference): JsonResponse
    {
        $request['TransactionReference'] = trim($TransactionReference);
        $this->validate($request , ['TransactionReference'=>'required|uuid']);

        @$gatewayInterface = new gatewayInterface();
        @$gatewayInterface->api_user = $this->api_user;
        @$gatewayInterface->TransactionReference  = $request->input('TransactionReference');

        return @$gatewayInterface->getCollectionStatus();
    }

    /**
     * @throws ValidationException
     */
    public function getAllCollections(Request $request): JsonResponse
    {
        $this->validate($request, [
            'start'        => 'date|date_format:Y-m-d H:i:s|before:tomorrow|before_or_equal:end',
            'end'          => 'date|date_format:Y-m-d H:i:s|after_or_equal:start',
            'status'       => 'string|in:FAILED,PENDING,INDETERMINATE,SUCCEEDED',
            'per_page' => 'integer|min:1|max:50',
        ]);

        @$gatewayInterface = new gatewayInterface();
        @$gatewayInterface->api_user             = $this->api_user;
        @$gatewayInterface->start_date           = trim($request->get('start','2020-01-01 00:00:00'));
        @$gatewayInterface->end_date             = trim($request->get('end',date('Y-m-d 23:59:59')));
        @$gatewayInterface->transaction_status   = trim($request->get('status'));
        @$gatewayInterface->result_set_limit     = trim($request->get('per_page',25));

        return $gatewayInterface->getAllCollections();
    }
    /**
     * @throws ValidationException
     */
    public function getOVACollectionsBalance(Request $request): JsonResponse
    {
        $this->validate($request, [
            'network'       => 'required|string|in:MTN,AIRTEL',
        ]);
        @$gatewayInterface = new gatewayInterface();
        @$gatewayInterface->network      = trim($request->input('network','mtn'));
        return $gatewayInterface->getOVACollectionsBalance();
    }
    private function getClientIpAddr()
    {
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){$ip = $_SERVER['HTTP_CLIENT_IP'];}elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];}else{$ip = $_SERVER['REMOTE_ADDR'];}
        return $ip;
    }
}

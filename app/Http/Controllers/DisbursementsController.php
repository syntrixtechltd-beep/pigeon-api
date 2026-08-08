<?php
namespace App\Http\Controllers;
use App\APIAccessTokens;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use gatewayInterface;
use Illuminate\Validation\ValidationException;
class DisbursementsController extends Controller
{
    private $api_user;

    public function __construct()
    {
        $user = $this->middleware('auth');
        if(Auth::user()){$this->api_user = json_decode(Auth::user())->id;}
    }
    public function initiateDisburse(Request $request)
    {
        $this->validate($request, [
            'type'                  => 'string|in:mm',
            'account'               => 'required|digits:10',
            'amount'                => 'required|numeric|min:500|max:5000000',
            'reference'             => 'required|alpha_dash|between:4,50',
            'narration'             => 'required|string|between:4,100',
        ]);

        $transfer_type = trim($request->input('type','mm'));

        @$gatewayInterface = new gatewayInterface();
        @$gatewayInterface->api_user                     = $this->api_user;
        @$gatewayInterface->amount                       = trim($request->input('amount',500));
        @$gatewayInterface->external_reference           = trim($request->input('reference'));
        @$gatewayInterface->narration                    = trim($request->input('narration'));
        @$gatewayInterface->originatorIP                 = trim($request->input('ip',$this->getClientIpAddr()));
        @$gatewayInterface->originatorChannel            = trim($request->input('channel',1));

        if($transfer_type === "mm")
        {
            @$gatewayInterface->msisdn    = trim($request->input('account'));
            return @$gatewayInterface->mobileMoneyRequestDisbursement();
        }
    }
    public function checkDisbursementStatus(Request $request , $TransactionReference): \Illuminate\Http\JsonResponse
    {
        $request['TransactionReference'] = trim($TransactionReference);
        $this->validate($request , ['TransactionReference'=>'required|uuid']);

        @$gatewayInterface = new gatewayInterface();
        @$gatewayInterface->api_user                     = $this->api_user;
        @$gatewayInterface->TransactionReference  = $request->TransactionReference;

        return @$gatewayInterface->getTransferStatus();
    }
    public function getAllDisbursements(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->validate($request, [
            'start'        => 'date|date_format:Y-m-d H:i:s|before_or_equal:today|before_or_equal:end',
            'end'          => 'date|date_format:Y-m-d H:i:s|after_or_equal:start',
            'status'       => 'string|in:FAILED,PENDING,INDETERMINATE,SUCCEEDED',
            'ip'    => 'ipv4',
            'per_page' => 'integer|min:1|max:50',
        ]);


        @$gatewayInterface = new gatewayInterface();
        @$gatewayInterface->api_user                     = $this->api_user;
        @$gatewayInterface->start_date            = trim($request->input('start','2020-01-01 23:59:59'));
        @$gatewayInterface->end_date              = trim($request->input('end',date('Y-m-d 23:59:59')));
        @$gatewayInterface->transaction_status    = trim($request->input('status')) ;
        @$gatewayInterface->originatorIP          = trim($request->input('ip'));
        @$gatewayInterface->result_set_limit      = trim($request->input('per_page',25));

        return $gatewayInterface->getAllTransfers();
    }

    /**
     * @throws ValidationException
     */
    public function getOVADisbursementBalance(Request $request): JsonResponse
    {
        $this->validate($request, [
            'network'       => 'required|string|in:MTN,AIRTEL',
        ]);
        @$gatewayInterface = new gatewayInterface();
        @$gatewayInterface->network      = trim($request->input('network','mtn'));
        return $gatewayInterface->getOVADisbursementBalance();
    }
    private function getClientIpAddr()
    {
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){$ip = $_SERVER['HTTP_CLIENT_IP'];}elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];}else{$ip = $_SERVER['REMOTE_ADDR'];}
        return $ip;
    }
    private function getContact($accountId,$uuid)
    {
        $matches = ['accountId'=>$accountId,'uuid' => $uuid,'active'=> 1];
        $contact= Contacts::where($matches)->first();
        if($contact){return $contact->phone;}else{return '';}

    }

}

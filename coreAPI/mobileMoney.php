<?php
namespace gatewayInterface;
class mobileMoney
{
    public function validateMMPaymentProvider($customerNumber): object
    {
        $customerNumber = (string) $customerNumber;
        if(strlen($customerNumber) >= 10)
        {
            if(strlen($customerNumber) == 10)
            {
                $mno = substr($customerNumber, 1, 2);
                $mtn = array(76,77,78,79,39);
                $airtel = array(70,74,75,20);

                if(in_array($mno,$mtn)){return (object) array("isValid"=>true,"paymentProviderId"=>1,"paymentProviderName"=>"MTN MOBILE MONEY","isActive"=>true,"mobileMoneyTransfersEnabled"=>true);}
                elseif(in_array($mno,$airtel)){return (object) array("isValid"=>true,"paymentProviderId"=>2,"paymentProviderName"=>"AIRTEL MONEY","isActive"=>true,"mobileMoneyTransfersEnabled"=>true);}

                else{return (object) array("isValid"=>false);}
            }
            else
            {
                return (object) array("isValid"=>false);
            }
        }
        else
        {
            return (object) array("isValid"=>false);
        }
    }
    public function returnPaymentProviderId($provider): int
    {
        return match ($provider) {
            "MTN"  => 1,
            "AIRTEL"  => 2
        };
    }
}

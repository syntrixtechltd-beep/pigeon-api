<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class Transactions extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public $timestamps = false;
    protected $table = "transactions";

    protected $fillable = [
        'api_user_id',
        'amount',
        'account',
        'transactionReference',
        'transactionNarrative',
        'privateTransactionReference',
        'transactionGeneralTransactionTypeId',
        'transactionSpecificTransactionTypeId',
        'transactionDesignation',
        'transactionStatus',
        'transactionOriginatorIdentification',
        'transactionOriginatorDescription',
        'transactionOriginatorChannel',
        'transactionMobileOperatorTransactionId',
        'transactionDesignationId',
        'balanceBeforeTransaction',
        'balanceAfterTransaction',
        'transactionCurrencyCode',
        'transactionPaymentProviderId',
        'parentTransactionRef',
        'transactionFee',
        'vasBundleId',
        'initiationDate',
        'completionDate',
    ];

    protected $guarded = ['*'];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'amount' => 'double',
        'balanceBeforeTransaction' => 'double',
        'balanceAfterTransaction' => 'double',
        'transactionFee' => 'double',
        'customerCharged' => 'boolean',
        'callbackRequested' => 'boolean',
        'callbackProcessed' => 'boolean',
    ];
}

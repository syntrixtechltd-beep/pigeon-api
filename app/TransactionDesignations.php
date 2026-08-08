<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class TransactionDesignations extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public $timestamps = false;
    protected $table = "transactions_transactionDesignation";

    protected $fillable = [];
    protected $guarded = ['*'];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];
}

<?php

namespace App;
use Illuminate\Database\Eloquent\Model;
class PaymentProviders extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public $timestamps = false;
    protected $table = "payment_providers";

    protected $fillable = [];
    protected $guarded = ['*'];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];
}

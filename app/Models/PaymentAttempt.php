<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'amount' => 'integer'];
    protected $hidden = ['snap_token', 'snapshot'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

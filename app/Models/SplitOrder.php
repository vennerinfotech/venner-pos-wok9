<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class SplitOrder extends BaseModel
{
    protected $fillable = ['order_id', 'amount', 'payment_method', 'status'];

    public function items()
    {
        return $this->hasMany(SplitOrderItem::class);
    }
}

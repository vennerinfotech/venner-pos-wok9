<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class SplitOrderItem extends BaseModel
{
    protected $fillable = ['split_order_id', 'order_item_id'];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}

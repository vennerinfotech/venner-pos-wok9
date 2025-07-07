<?php

namespace App\Observers;

use App\Models\Order;
use App\Events\OrderCancelled;

class OrderObserver
{

    public function creating(Order $order)
    {
        if (branch() && $order->branch_id == null) {
            $order->branch_id = branch()->id;
        }
    }

    public function updated(Order $order)
    {
        if ($order->isDirty('status') && $order->status == 'canceled') {
            OrderCancelled::dispatch($order);
        }
    }

}

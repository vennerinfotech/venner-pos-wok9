<?php

namespace App\Livewire\Dashboard;

use App\Models\Order;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;

class RunningOrders extends Component
{
    use LivewireAlert;

    public function render()
    {
        $count = Order::where('status', 'kot')->count();

        $playSound = false;
        // Optionally, you can add a session-based sound logic similar to TodayOrders if needed

        return view('livewire.dashboard.running-orders', [
            'count' => $count,
            'playSound' => $playSound
        ]);
    }
}

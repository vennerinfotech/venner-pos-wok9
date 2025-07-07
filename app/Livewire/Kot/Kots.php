<?php

namespace App\Livewire\Kot;

use App\Models\Kot;
use App\Models\KotSetting;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class Kots extends Component
{

    protected $listeners = ['refreshKots' => '$refresh'];
    public $filterOrders;
    public $dateRangeType;
    public $startDate;
    public $endDate;
    public $kotSettings;

    public function mount()
    {
        // Load date range type from cookie
        $this->kotSettings = KotSetting::first();
        $this->dateRangeType = request()->cookie('kots_date_range_type', 'today');
        $this->filterOrders = ($this->kotSettings->default_status == 'pending') ? 'pending_confirmation' : 'in_kitchen';
        $this->startDate = now()->startOfWeek()->format('m/d/Y');
        $this->endDate = now()->endOfWeek()->format('m/d/Y');

        $this->setDateRange();
    }

    public function setDateRange()
    {
        switch ($this->dateRangeType) {
        case 'today':
            $this->startDate = now()->startOfDay()->format('m/d/Y');
            $this->endDate = now()->startOfDay()->format('m/d/Y');
            break;

        case 'lastWeek':
            $this->startDate = now()->subWeek()->startOfWeek()->format('m/d/Y');
            $this->endDate = now()->subWeek()->endOfWeek()->format('m/d/Y');
            break;

        case 'last7Days':
            $this->startDate = now()->subDays(7)->format('m/d/Y');
            $this->endDate = now()->startOfDay()->format('m/d/Y');
            break;

        case 'currentMonth':
            $this->startDate = now()->startOfMonth()->format('m/d/Y');
            $this->endDate = now()->startOfDay()->format('m/d/Y');
            break;

        case 'lastMonth':
            $this->startDate = now()->subMonth()->startOfMonth()->format('m/d/Y');
            $this->endDate = now()->subMonth()->endOfMonth()->format('m/d/Y');
            break;

        case 'currentYear':
            $this->startDate = now()->startOfYear()->format('m/d/Y');
            $this->endDate = now()->startOfDay()->format('m/d/Y');
            break;

        case 'lastYear':
            $this->startDate = now()->subYear()->startOfYear()->format('m/d/Y');
            $this->endDate = now()->subYear()->endOfYear()->format('m/d/Y');
            break;

        default:
            $this->startDate = now()->startOfWeek()->format('m/d/Y');
            $this->endDate = now()->endOfWeek()->format('m/d/Y');
            break;
        }

    }

    #[On('setStartDate')]
    public function setStartDate($start)
    {
        $this->startDate = $start;
    }

    #[On('setEndDate')]
    public function setEndDate($end)
    {
        $this->endDate = $end;
    }

    public function updatedDateRangeType($value)
    {
        cookie()->queue(cookie('kots_date_range_type', $value, 60 * 24 * 30)); // 30 days
    }

    public function render()
    {
        $start = Carbon::createFromFormat('m/d/Y', $this->startDate)->startOfDay()->toDateTimeString();
        $end = Carbon::createFromFormat('m/d/Y', $this->endDate)->endOfDay()->toDateTimeString();

        $kots = Kot::withCount('items')->orderBy('id', 'desc')
            ->join('orders', 'kots.order_id', '=', 'orders.id')
            ->whereDate('kots.created_at', '>=', $start)->whereDate('kots.created_at', '<=', $end)
            ->where('orders.status', '<>', 'canceled')
            ->where('orders.status', '<>', 'draft')
            ->with('items', 'items.menuItem', 'order', 'order.waiter', 'order.table', 'items.menuItemVariation', 'items.modifierOptions')
            ->get();

        if ($this->kotSettings->default_status == 'pending') {
            $inKitchen = $kots->filter(function ($order) {
                return $order->status == 'in_kitchen';
            });
        } else {
            $inKitchen = $kots->filter(function ($order) {
                return $order->status == 'in_kitchen' || $order->status == 'pending_confirmation';
            });
        }

        $foodReady = $kots->filter(function ($order) {
            return $order->status == 'food_ready';
        });

        $pendingConfirmation = $kots->filter(function ($order) {
            return $order->status == 'pending_confirmation';
        });

        switch ($this->filterOrders) {
        case 'in_kitchen':
            $kotList = $inKitchen;
                break;

        case 'food_ready':
            $kotList = $foodReady;
                break;

        case 'pending_confirmation':
            $kotList = $pendingConfirmation;
                break;

        default:
            $kotList = $kots;
                break;
        }

        $kotSettings = KotSetting::first();

        return view('livewire.kot.kots', [
            'kots' => $kotList,
            'inKitchenCount' => count($inKitchen),
            'foodReadyCount' => count($foodReady),
            'pendingConfirmationCount' => count($pendingConfirmation),
            'kotSettings' => $kotSettings,
        ]);
    }

}

<?php

namespace App\Livewire\Kot;

use App\Models\Kot;
use App\Models\KotItem;
use App\Models\Printer;
use Livewire\Component;
use App\Models\KotPlace;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use App\Traits\PrinterSetting;

class KotCard extends Component
{
    use LivewireAlert;
    public $kot;
    public $confirmDeleteKotModal = false;
    public $kotSettings;
    use PrinterSetting;

    public function mount($kot, $kotSettings)
    {
        $this->kot = $kot;
        $this->kotSettings = $kotSettings;
    }

    public function changeKotStatus($status)
    {
        Kot::where('id', $this->kot->id)->update([
        'status' => $status
        ]);

        if ($status == 'food_ready') {
            KotItem::where('kot_id', $this->kot->id)->update([
                'status' => 'ready'
            ]);
        }

        if ($status == 'in_kitchen') {
            KotItem::where('kot_id', $this->kot->id)->update([
                'status' => 'cooking'
            ]);
        }

        $this->dispatch('refreshKots');
    }

    public function changeKotItemStatus($itemId, $status)
    {
        KotItem::where('id', $itemId)->update([
        'status' => $status
        ]);

        $checkAllItemsReady = KotItem::where('kot_id', $this->kot->id)->where(function ($query) {
            $query->where('status', 'cooking')->orWhere('status', null);
        })->count();


        if ($checkAllItemsReady == 0) {
            $this->kot->status = 'food_ready';
            $this->kot->save();
        }

        $this->dispatch('refreshKots');
    }

    public function deleteKot($id)
    {
        $order = Kot::find($id)->order;
        $kotCounts = $order->kot->count();


        if ($kotCounts == 1) {
            $order->status = 'canceled';
            $order->save();

            if ($order->table) {
                $order->table->update(['available_status' => 'available']);
            }
        }

        Kot::destroy($id);
        $this->confirmDeleteKotModal = false;


        $this->dispatch('refreshKots');

        if ($kotCounts == 1) {
            $order->delete();
        }
    }

    public function printKot($kot)
    {
        if (in_array('Kitchen', restaurant_modules()) && in_array('kitchen', custom_module_plugins()))
        {
            $kot = Kot::with(['items.menuItem.kotPlace'])->find($kot);
            $kotPlaceItems = [];

            foreach ($kot->items as $kotItem)
            {
                if ($kotItem->menuItem && $kotItem->menuItem->kot_place_id)
                {
                    $kotPlaceId = $kotItem->menuItem->kot_place_id;

                    if (!isset($kotPlaceItems[$kotPlaceId]))
                    {
                        $kotPlaceItems[$kotPlaceId] = [];
                    }

                    $kotPlaceItems[$kotPlaceId][] = $kotItem;
                }
            }

            $kotPlaceIds = array_keys($kotPlaceItems);
            $kotPlaces = KotPlace::with('printerSetting')->whereIn('id', $kotPlaceIds)->get();

            foreach ($kotPlaces as $kotPlace)
            {
                $printerSetting = $kotPlace->printerSetting;


                if ( $printerSetting->is_active == 0) {
                    $printerSetting = Printer::where('is_default', true)->first();
                }
                try {
                    switch ($printerSetting->printing_choice) {
                    case 'directPrint':
                        $this->handleKotPrint($kot->id, $kotPlace->id);
                         break;
                    default:
                        $url = route('kot.print', [$kot->id, $kotPlace?->id]);
                        $this->dispatch('print_location', $url);
                         break;
                    }
                } catch (\Throwable) {
                    $this->alert('error', __('messages.printerNotConnected'), [
                     'toast' => true,
                     'position' => 'top-end',
                     'showCancelButton' => false,
                     'cancelButtonText' => __('app.close')
                    ]);
                }

            }
        }
        else
        {
            $kotPlace = KotPlace::where('is_default', 1)->first();
            $printerSetting = $kotPlace->printerSetting;
            // If no printer is set, fallback to print URL dispatch
            if (!$printerSetting) {
                $url = route('kot.print', [$kot->id, $kotPlace?->id]);
                $this->dispatch('print_location', $url);
            }

            // dd([$kot,$kotPlace?->id]);
            try {
                switch ($printerSetting->printing_choice) {
                case 'directPrint':
                    $this->handleKotPrint($kot, $kotPlace->id);
                    break;
                default:
                    $url = route('kot.print', [$kot]);
                    $this->dispatch('print_location', $url);
                    break;
                }
            } catch (\Throwable) {
                $this->alert('error', __('messages.printerNotConnected'), [
                'toast' => true,
                'position' => 'top-end',
                'showCancelButton' => false,
                'cancelButtonText' => __('app.close')
                ]);
            }
        }
    }

    public function render()
    {
        $printer = Printer::where('is_default', true)->first();
        return view('livewire.kot.kot-card', [
        'printer' => $printer,
        ]);
    }

}

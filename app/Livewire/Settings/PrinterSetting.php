<?php

namespace App\Livewire\Settings;

use App\Models\Printer;
use Livewire\Component;
use App\Models\KotPlace;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\MultipleOrder;
use App\Livewire\Order\Orders;
use Illuminate\Support\Facades\Validator;
use Jantinnerezo\LivewireAlert\LivewireAlert;

class PrinterSetting extends Component
{
    use WithPagination, LivewireAlert;
    public $title;
    public $printChoice;
    public $printFormat; // thermalPrinter / nonThermalPrinter
    // public $selectprintFormat; // thermal56mm, thermal80mm, etc.
    public $invoiceQrCode;
    public $charactersPerLine;
    public $shareName;
    public $printerIpAddress;
    public $printerPortAddress;
    public $openCashDrawer;
    public $printType;
    public $printerType;
    public $ipv4Address;
    public $selectedPrinterId;
    public $name;
    public $printing_choice;
    public $id;
    public $confrimDeletePrinter = false;
    public $deletePrinter;
    public $printer;
    public $printOrder = false;
    public $printKot;
    public $selectedKots = [];
    public $selectedOrders = [];
    public $showForm = false;
    public $isDefault;
    public $selectprintFormat = 'thermal80mm'; // default value
    public $port;

    public function yes($id)
    {
        $printer = Printer::find($id);
        $this->id = $printer->id;
        $this->title = $printer->name;
        $this->selectedKots = $printer->kots ? json_decode($printer->kots, true) : [];
        $this->selectedOrders = $printer->orders ? json_decode($printer->orders, true) : [];
        $this->printChoice = $printer->printing_choice;
        $this->printerType = $printer->type;
        $this->printerIpAddress = $printer->ip_address;
        $this->printerPortAddress = $printer->port;
        $this->shareName = $printer->share_name;
        $this->selectprintFormat = $printer->print_format;
        $this->printFormat = $this->selectprintFormat ? 'thermalPrinter' : 'nonThermalPrinter';
        $this->isDefault = (bool)$printer->is_default;
        $this->invoiceQrCode = $printer->invoice_qr_code;
        $this->charactersPerLine = $printer->characters_per_line;
        $this->openCashDrawer = $printer->open_cash_drawer;

    }

    public function submitForm()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'printChoice' => 'required|string',
            'selectedKots' => 'nullable|array',
            'selectedOrders' => 'nullable|array',
            'isDefault' => 'nullable|boolean',
            'printChoice' => 'required | string',
            'printerType' => $this->printChoice === 'directPrint' ? 'required | string' : 'nullable',
            'printerIpAddress' => $this->printerType === 'network' ? 'required | ip' : 'nullable',
            'shareName' => $this->printerType === 'windows' ? 'required | string' : 'nullable',

        ]);

        // Check if is_default is true and already exists
        if ($this->isDefault) {
            $existingDefault = Printer::where('is_default', true)->first();

            if ($existingDefault)
            {
                $this->addError('isDefault', __('messages.defaultPrinterExists'));
                return;
            }
        }

        // Check if any of the selected KOTs are already assigned
        $conflict = Printer::whereNotNull('kots')
            ->get()
            ->filter(function ($printer) {
                $existingKots = json_decode($printer->kots, true);
                return !empty(array_intersect($existingKots, $this->selectedKots));
            })
            ->first();

        if ($conflict) {
            $this->addError('selectedKots', __('messages.kotConflict'));
            return;
        }

        // Check if any of the selected Orders are already assigned
        $conflict = Printer::whereNotNull('orders')
            ->get()
            ->filter(function ($printer) {
                $existingOrders = json_decode($printer->orders, true);
                return !empty(array_intersect($existingOrders, $this->selectedOrders));
            })
            ->first();

        if ($conflict) {
            $this->addError('selectedOrders', __('messages.orderConflict'));
            return;
        }

        $printer = Printer::create([
            'name' => $this->title,
            'printing_choice' => $this->printChoice,
            'print_format' => $this->selectprintFormat,
            'invoice_qr_code' => $this->invoiceQrCode,
            'char_per_line' => $this->charactersPerLine,
            'share_name' => $this->shareName,
            'ip_address' => $this->printerIpAddress,
            'port' => $this->printerPortAddress,
            'open_cash_drawer' => $this->openCashDrawer,
            'type' => $this->printerType,
            'ipv4_address' => $this->ipv4Address,
            'thermal_or_nonthermal' => $this->printFormat ?? 'thermalPrinter',
            'kots' => $this->selectedKots ? json_encode($this->selectedKots) : null,
            'orders' => $this->selectedOrders ? json_encode($this->selectedOrders) : null,
            'is_default' => $this->isDefault ?? false,
        ]);


        // Now update existing KOTs by assigning them to this printer
        if (!empty($this->selectedKots)) {
            foreach ($this->selectedKots as $kotId) {
                KotPlace::where('id', $kotId)->update([
                'printer_id' => $printer->id,
                ]);
            }
        }

        // Now update existing Orders by assigning them to this printer
        if (!empty($this->selectedOrders)) {
            foreach ($this->selectedOrders as $orderId) {
                MultipleOrder::where('id', $orderId)->update([
                'printer_id' => $printer->id,
                ]);
            }
        }

        $this->alert('success', __('messages.printerAdded'), [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close'),
        ]);

        $this->reset();
    }

    public function showAddPrinter()
    {
        $this->reset();
        $this->showForm = !$this->showForm;
    }

    public function editPrinter($printerId)
    {
        $this->selectedPrinterId = $printerId;
        $this->yes($this->selectedPrinterId);
        $this->js('document.getElementById("printerSettingForm")?.scrollIntoView({ behavior: "smooth" })');
        $this->showForm = !$this->showForm;
    }

    public function update()
    {
        $validator = Validator::make($this->all(), [
            'title' => 'required|string|max:255',
            'printChoice' => 'required|string',
            'port' => 'nullable',
        ]);

        // Conditional validations
        $validator->sometimes('printerType', 'required|string', function () {
            return $this->printChoice === 'directPrint';
        });

        $validator->sometimes('selectprintFormat', 'required|string', function () {
            return $this->printChoice === 'directPrint';
        });

        $validator->sometimes('printerIpAddress', 'required|ip', function () {
            return $this->printerType === 'network';
        });

        $validator->sometimes('shareName', 'required|string', function () {
            return $this->printerType === 'windows';
        });

        $validator->validate();

        // dd($this->selectedKots, $this->selectedOrders);


        // Check for KOT conflict, excluding current printer
        $conflict = Printer::where('id', '!=', $this->selectedPrinterId)
        ->whereNotNull('kots')
        ->get()
        ->filter(function ($printer) {
            $existingKots = json_decode($printer->kots, true);
            return !empty(array_intersect($existingKots, $this->selectedKots ?? []));
        })
        ->first();

        if ($conflict) {
            $this->addError('selectedKots', __('messages.kotConflict'));
            return;
        }

        // Check for Order conflict, excluding current printer
        $conflict = Printer::where('id', '!=', $this->selectedPrinterId)
        ->whereNotNull('orders')
        ->get()
        ->filter(function ($printer) {
            $existingOrders = json_decode($printer->orders, true);
            return !empty(array_intersect($existingOrders, $this->selectedOrders ?? []));
        })
        ->first();

        if ($conflict) {
            $this->addError('selectedOrders', __('messages.orderConflict'));
            return;
        }


        $printer = Printer::find($this->selectedPrinterId);

        $data = [
            'name' => $this->title,
            'printing_choice' => $this->printChoice,
            'type' => $this->printerType,
            'kots' => $this->selectedKots ? json_encode($this->selectedKots) : null,
            'orders' => $this->selectedOrders ? json_encode($this->selectedOrders) : null,
            'is_default' => $this->isDefault ?? false,

        ];

        // Update KOTs: Remove printer_id from KOTs not selected, assign to selected
        KotPlace::where('printer_id', $printer->id)
            ->whereNotIn('id', $this->selectedKots ?? [])
            ->update(['printer_id' => null]);

        if (!empty($this->selectedKots))
        {
            KotPlace::whereIn('id', $this->selectedKots)
            ->update(['printer_id' => $printer->id]);
        }

        // Update Orders: Remove printer_id from Orders not selected, assign to selected
        MultipleOrder::where('printer_id', $printer->id)
            ->whereNotIn('id', $this->selectedOrders ?? [])
            ->update(['printer_id' => null]);

        if (!empty($this->selectedOrders))
        {
            MultipleOrder::whereIn('id', $this->selectedOrders)
            ->update(['printer_id' => $printer->id]);
        }

        // Optimize and avoid conflicting assignments
        if ($this->printChoice === 'browserPopupPrint') {
            $data = array_merge($data, [
                'print_format' => null,
                'share_name' => null,
                'ip_address' => null,
                'port' => null,
                'type' => null,
            ]);
        } elseif ($this->printerType === 'network') {
            $data = array_merge($data, [
                'print_format' => $this->selectprintFormat,
                'share_name' => null,
                'ip_address' => $this->printerIpAddress,
                'port' => $this->printerPortAddress ?? 9100,
                'type' => $this->printerType,
            ]);
        } elseif ($this->printerType === 'windows') {
            $data = array_merge($data, [
                'print_format' => $this->selectprintFormat,
                'share_name' => $this->shareName,
                'ip_address' => null,
                'port' => null,
                'type' => $this->printerType,
            ]);
        } else {
            $data = array_merge($data, [
                'print_format' => $this->selectprintFormat,
                'share_name' => $this->shareName,
                'ip_address' => $this->printerIpAddress,
                'port' => $this->printerPortAddress ?? 9100,
                'type' => $this->printerType,
            ]);
        }

                $printer->update($data);

                $this->alert('success', __('messages.printerUpdated'), [
                'toast' => true,
                'position' => 'top-end',
                'showCancelButton' => false,
                'cancelButtonText' => __('app.close')
                ]);

        $this->reset();
    }

    public function showDeletePrinter($id)
    {

        $this->confrimDeletePrinter = true;
        $this->printer = Printer::findOrFail($id);

    }

    public function confirmdeletePrinter()
    {
        if (!$this->printer) { return;
        }

        if ($this->printer->is_default) {
            $this->alert('error', __('messages.cannotDeleteDefaultPrinter'), [
              'toast' => true,
              'position' => 'top-end',
              'showCancelButton' => false,
              'cancelButtonText' => __('app.close')
               ]);
        }
        else
            {
            $this->printer->delete();
            $this->confrimDeletePrinter = false;
            $this->printer = null;

            $this->alert('success', __('messages.printerDeleted'), [
               'toast' => true,
               'position' => 'top-end',
               'showCancelButton' => false,
               'cancelButtonText' => __('app.close')
               ]);
        }
    }

    public function toggleSelectKot($kot)
    {
        if (in_array($kot['id'], $this->selectedKots)) {
            $this->selectedKots = array_filter($this->selectedKots, fn($id) => $id !== $kot['id']);
        }
        else
        {
            $this->selectedKots[] = $kot['id'];
        }

        $this->selectedKots = array_values($this->selectedKots);
    }

    public function toggleSelectOrder($order)
    {
        if (in_array($order['id'], $this->selectedOrders)) {
            $this->selectedOrders = array_filter($this->selectedOrders, fn($id) => $id !== $order['id']);
        }
        else
          {
            $this->selectedOrders[] = $order['id'];
        }

          $this->selectedOrders = array_values($this->selectedOrders);
    }

    public function togglePrinterStatus($printerId)
    {
        $printer = Printer::find($printerId);

        if ($printer) {
            if ($printer->is_default) {
                $this->alert('error', __('messages.cannotUpdateDefaultPrinterStatus'), [
                'toast' => true,
                'position' => 'top-end',
                'showCancelButton' => false,
                'cancelButtonText' => __('app.close')
                ]);
                return;
            }

            $printer->is_active = !$printer->is_active;
            $printer->save();
        }

        $this->alert('success', __('messages.printerStatusUpdated'), [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close')
        ]);
    }

    public function render()
    {
        $kots = KotPlace::all();
        $printers = Printer::paginate(10);

        $orders = MultipleOrder::all();
        return view('livewire.settings.printer-setting', [
             'printers' => $printers , 'kots' => $kots , 'orders' => $orders,
        ]);
    }

}

<?php

namespace App\Livewire\Order;

use App\Models\Order;
use App\Models\Payment;
use App\Models\SplitOrder;
use App\Models\Table;
use App\Notifications\SendOrderBill;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

class AddPayment extends Component
{
    use LivewireAlert;

    public $order;
    public $showAddPaymentModal = false;
    public $paymentMethod = 'cash';
    public $paymentAmount = 0;
    public $returnAmount = 0;
    public $balanceAmount = 0;
    public $dueAmount = 0;
    public $paidAmount = 0;
    // Split payment properties
    public $showSplitOptions = false;
    public $splitType = null;
    public $numberOfSplits = 2;
    public $customSplits = [];
    public $splits = [];
    public $availableItems = [];
    public $activeSplitId = 1;
    public $totalExtraCharges = 0;
    public $tipPercentage;
    public $tipAmount;
    public $tipMode = 'percentage';
    public $tipNote = '';
    public $showTipModal = false;
    public $canAddTip;

    #[On('showPaymentModal')]
    public function showPaymentModal($id)
    {
        $this->order = Order::with([
            'items',
            'items.menuItem',
            'taxes',
            'payments' => function($query) {
                $query->where('payment_method', '!=', 'due');
            }
        ])->find($id);

        $this->canAddTip = restaurant()->enable_tip_pos && $this->order->status !== 'paid';

        $totalDiscount = floatval($this->order->discount_amount ?? 0);

        $subTotal = floatval($this->order->sub_total ?? 0);
        $discountedSubTotal = max(0, $subTotal - $totalDiscount);

        $charges = $this->order->charges;
        $extraCharges = $charges->map(function ($charge) use ($discountedSubTotal) {
            $chargeAmount = $charge->charge->charge_type == 'percent'
                ? ($charge->charge->charge_value / 100) * $discountedSubTotal
                : $charge->charge->charge_value;
            return [
                'name' => $charge->charge->charge_name,
                'amount' => $chargeAmount,
                'rate' => $charge->charge->charge_value,
                'type' => $charge->charge->charge_type,
            ];
        })->toArray();
        $this->totalExtraCharges = collect($extraCharges)->sum('amount');

        $this->updateAmountDetails();
        $this->showAddPaymentModal = true;

        $totalDiscount = floatval($this->order->discount_amount ?? 0);
        $totalTip = floatval($this->order->tip_amount ?? 0);
        $totalBaseAmount = $this->order->items->sum('amount');
        $totalQuantity = $this->order->items->sum('quantity');

        $this->availableItems = $this->order->items->map(function($orderItem) use ($totalDiscount, $totalTip, $totalBaseAmount, $totalQuantity) {
            $unitBasePrice = $orderItem->amount / $orderItem->quantity;

            $itemDiscount = $totalBaseAmount > 0 ? ($orderItem->amount / $totalBaseAmount) * $totalDiscount : 0;
            $itemTip = $totalBaseAmount > 0 ? ($orderItem->amount / $totalBaseAmount) * $totalTip : 0;
            $unitDiscount = $itemDiscount / $orderItem->quantity;
            $unitTip = $itemTip / $orderItem->quantity;
            $unitBasePriceAfterDiscount = $unitBasePrice - $unitDiscount;
            $itemExtraCharges = $this->totalExtraCharges / $totalQuantity;

            $itemTaxAmount = 0;
            if ($this->order->total > 0) {
                foreach ($this->order->taxes as $tax) {
                    $itemTaxAmount += (($tax->tax->tax_percent / 100) * $unitBasePriceAfterDiscount);
                }
            }

            $unitTotalPrice = $unitBasePriceAfterDiscount + $itemTaxAmount + $itemExtraCharges + $unitTip;

            return [
                'id' => $orderItem->id,
                'name' => $orderItem->menuItem->item_name,
                'quantity' => $orderItem->quantity,
                'price' => $unitTotalPrice, // per unit price including taxes, charges, discount, tip
                'base_price' => $unitBasePrice, // per unit base price BEFORE discount (for display)
                'base_price_after_discount' => $unitBasePriceAfterDiscount, // per unit base price after discount (for calculation)
                'tax_amount' => $itemTaxAmount, // per unit tax
                'total' => $orderItem->total,
                'extra_charges' => $itemExtraCharges, // per unit extra charges
                'discount' => $unitDiscount, // per unit discount
                'tip' => $unitTip, // per unit tip
                'remaining' => $orderItem->quantity,
                'order_item_id' => $orderItem->id
            ];
        })->toArray();

        $this->initializeSplits();
    }

    public function updateAmountDetails()
    {
        $this->dueAmount = $this->order->total - $this->order->payments->sum('amount');
        $this->paymentAmount = $this->dueAmount;
        $this->balanceAmount = $this->dueAmount - $this->paymentAmount;
        $this->paidAmount = $this->order->payments->sum('amount');
        $this->returnAmount = 0;
    }

    private function initializeSplits()
    {
        if ($this->splitType === 'items') {
            
            $this->splits = [
                1 => [
                    'id' => 1,
                    'items' => [],
                    'paymentMethod' => 'cash',  
                    'amount' => 0,
                    'total' => 0
                ]
            ];
            $this->activeSplitId = 1;
        } else {
            
            $this->customSplits = [1, 2];

            
            foreach ($this->customSplits as $splitNumber) {
                $this->splits[$splitNumber] = [
                    'id' => $splitNumber,
                    'paymentMethod' => 'cash',
                    'amount' => 0
                ];
            }

            
            if ($this->splitType === 'equal' || is_null($this->splitType)) {
                $splitAmount = $this->order->total / $this->numberOfSplits;
                foreach ($this->splits as $i => $split) {
                    if ($i > 0) {
                        $this->splits[$i]['amount'] = $splitAmount;
                    }
                }
            }
        }

        $this->balanceAmount = $this->dueAmount - $this->paymentAmount;
    }

    public function updatedPaymentAmount()
    {
        $paymentAmount = floatval($this->paymentAmount);

        if ($paymentAmount > $this->dueAmount) {
            // If payment is more than total, show return amount
            $this->returnAmount = $paymentAmount - $this->dueAmount;
            $this->balanceAmount = 0;
        } else {
            // If payment is less than total, show due amount
            $this->returnAmount = 0;
            $this->balanceAmount = $this->dueAmount - $paymentAmount;
        }
    }

    public function setPaymentMethod($method)
    {
        $this->paymentMethod = $method;
        $this->updatedPaymentAmount();
    }

    public function quickAmount($amount)
    {
        $this->paymentAmount = floatval($amount);
        $this->updatedPaymentAmount();
    }

    public function appendNumber($number)
    {

        $currentAmount = (string) $this->paymentAmount;

        // Handle decimal point input
        if ($number === '.') {
            if (str_contains($currentAmount, '.')) {
                return;
            }
            $this->paymentAmount = $currentAmount . $number;
            return;
        }

        $this->paymentAmount = $currentAmount === '0' ? $number : $currentAmount . $number;

        $this->paymentAmount = is_numeric($this->paymentAmount) ? (float) $this->paymentAmount : $this->paymentAmount;

        $this->updatedPaymentAmount();
    }


    public function clearAmount()
    {
        $this->paymentAmount = 0;
        $this->returnAmount = 0;
        $this->balanceAmount = $this->dueAmount;
    }

    public function updateSplitPayment($splitId, $method)
    {
        if ($this->splitType === 'items') {
            $split = collect($this->splits)->firstWhere('id', $splitId);
            $split['paymentMethod'] = $method;
        }
    }

    public function processSplitPayment()
    {
        switch ($this->splitType) {
        case 'equal':
            $this->order->split_type = 'even';
            $this->order->saveQuietly();

            foreach ($this->splits as $i => $split) {
                if ($i > 0 && !empty($split['amount'])) { // Skip index 0 and empty amounts
                    SplitOrder::create([
                        'order_id' => $this->order->id,
                        'amount' => $split['amount'],
                        'payment_method' => $split['paymentMethod'],
                        'status' => 'paid'
                    ]);

                    Payment::create([
                        'order_id' => $this->order->id,
                        'payment_method' => $split['paymentMethod'],
                        'amount' => $split['amount']
                    ]);
                }
            }
                break;

        case 'custom':
            $this->order->split_type = 'custom';
            $this->order->saveQuietly();

            foreach ($this->customSplits as $index => $split) {
                $lastIndex = $index === array_key_last($this->customSplits);
                if (!empty($this->splits[$split]['amount'])) {
                    SplitOrder::create([
                        'order_id' => $this->order->id,
                        'amount' => $this->splits[$split]['amount'],
                        'payment_method' => $this->splits[$split]['paymentMethod'],
                        'status' => 'paid'
                    ]);

                    Payment::create([
                        'order_id' => $this->order->id,
                        'payment_method' => $this->splits[$split]['paymentMethod'],
                        'amount' => $this->splits[$split]['amount'],
                        'balance' => $lastIndex && $this->returnAmount ? $this->returnAmount : 0
                    ]);
                }
            }
                break;

        case 'items':
            $this->order->split_type = 'items';
            $this->order->saveQuietly();
            foreach ($this->splits as $split) {
                if (!empty($split['items'])) {

                    $splitTotal = round(collect($split['items'])->sum(function ($item) {
                        return floatval($item['price']) * intval($item['quantity']);
                    }), 2);

                    if ($splitTotal > 0) {
                        // Create split payment record
                        Payment::create([
                            'order_id' => $this->order->id,
                            'payment_method' => $split['paymentMethod'],
                            'amount' => $splitTotal
                        ]);

                        // Create split order record
                        $splitOrder = SplitOrder::create([
                            'order_id' => $this->order->id,
                            'amount' => $splitTotal,
                            'payment_method' => $split['paymentMethod'],
                            'status' => 'paid'
                        ]);

                        // Link items to split order
                        foreach ($split['items'] as $item) {
                            $splitOrder->items()->create([
                            'order_item_id' => $item['order_item_id']
                            ]);
                        }
                    }
                }
            }
                break;
        }
    }

    public function submitForm()
    {
        if ($this->showSplitOptions && $this->splitType) {
            $this->processSplitPayment();

        } else {
            if ($this->paymentAmount >= 0) {
                Payment::create([
                'order_id' => $this->order->id,
                'payment_method' => $this->paymentMethod,
                'amount' => $this->paymentAmount - $this->returnAmount,
                'balance' => $this->returnAmount
                ]);
            }
        }
        $orderPaidAmount = Payment::where('order_id', $this->order->id)->where('payment_method', '!=', 'due')->sum('amount');

        $this->order->amount_paid = $orderPaidAmount;
        $this->order->status = $orderPaidAmount >= $this->order->total ? 'paid' : 'payment_due';
        $this->order->save();

        Payment::where('order_id', $this->order->id)->where('payment_method', 'due')->delete();

        if ($orderPaidAmount < $this->order->total) {
            Payment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'due',
            'amount' => $this->order->total - $orderPaidAmount
            ]);
        }


        Table::where('id', $this->order->table_id)->update([
            'available_status' => 'available'
        ]);

        if ($this->order->customer_id) {
            try {
                $this->order->customer->notify(new SendOrderBill($this->order));
            } catch (\Exception $e) {
                \Log::error('Error sending notification: ' . $e->getMessage());
            }
        }

        $this->dispatch('showOrderDetail', id: $this->order->id);
        $this->dispatch('refreshOrders');
        $this->dispatch('resetPos');
        $this->dispatch('refreshPayments');
        $this->showAddPaymentModal = false;
    }

    public function render()
    {
        return view('livewire.order.add-payment');
    }

    public function updateSplitPaymentMethod($splitId, $method)
    {
        if (isset($this->splits[$splitId])) {
            $this->splits[$splitId]['paymentMethod'] = $method;
            $this->splits = $this->splits; // Trigger Livewire update
        }
    }

    public function updateBalanceAmount()
    {

        if ($this->splitType === 'custom') {
            $totalSplitAmount = collect($this->customSplits)->sum(fn($split) => floatval($this->splits[$split]['amount'] ?? 0));
            $this->balanceAmount = max(0, $this->dueAmount - $totalSplitAmount);
            $this->returnAmount = max(0, $totalSplitAmount - $this->dueAmount);
        }
    }

    public function addItemToSplit($itemId, $splitId, $quantity = 1)
    {
        $itemIndex = collect($this->availableItems)->search(fn($i) => $i['id'] === $itemId);
        if ($itemIndex === false) return;

        $item = $this->availableItems[$itemIndex];
        $quantity = max(1, min($quantity, $item['remaining']));

        if ($quantity < 1) return;

        if (!isset($this->splits[$splitId]['items'])) {
            $this->splits[$splitId]['items'] = [];
        }

        $existingItemIndex = collect($this->splits[$splitId]['items'])->search(fn($i) => $i['order_item_id'] === $item['order_item_id']);

        if ($existingItemIndex !== false) {
            $this->splits[$splitId]['items'][$existingItemIndex]['quantity'] += $quantity;
            $this->splits[$splitId]['items'][$existingItemIndex]['total'] = $this->splits[$splitId]['items'][$existingItemIndex]['quantity'] * $this->splits[$splitId]['items'][$existingItemIndex]['price'];
        } else {
            $splitItem = [
                'id' => $item['id'],
                'order_item_id' => $item['order_item_id'],
                'name' => $item['name'],
                'quantity' => $quantity,
                'price' => floatval($item['price']),
                'base_price' => floatval($item['base_price']),
                'extra_charges' => floatval($item['extra_charges']),
                'tax_amount' => floatval($item['tax_amount']),
                'discount' => floatval($item['discount']),
                'tip' => floatval($item['tip']),
                'total' => floatval($item['price']) * $quantity
            ];
            $this->splits[$splitId]['items'][] = $splitItem;
        }

        $this->availableItems[$itemIndex]['remaining'] -= $quantity;

        $this->calculateSplitTotals();
    }

    // Increment quantity of item in split (if available)
    public function incrementItemInSplit($splitId, $itemIndex)
    {
        if (!isset($this->splits[$splitId]['items'][$itemIndex])) return;
        $item = $this->splits[$splitId]['items'][$itemIndex];
        $availableIndex = collect($this->availableItems)->search(fn($i) => $i['id'] === $item['id']);
        if ($availableIndex !== false && $this->availableItems[$availableIndex]['remaining'] > 0) {
            $this->splits[$splitId]['items'][$itemIndex]['quantity']++;
            $this->splits[$splitId]['items'][$itemIndex]['total'] = $this->splits[$splitId]['items'][$itemIndex]['quantity'] * $this->splits[$splitId]['items'][$itemIndex]['price'];
            $this->availableItems[$availableIndex]['remaining']--;
            $this->calculateSplitTotals();
        }
    }

    // Decrement quantity of item in split (if > 1), or remove if quantity becomes 0
    public function decrementItemInSplit($splitId, $itemIndex)
    {
        if (!isset($this->splits[$splitId]['items'][$itemIndex])) return;
        $item = $this->splits[$splitId]['items'][$itemIndex];
        $availableIndex = collect($this->availableItems)->search(fn($i) => $i['id'] === $item['id']);
        if ($this->splits[$splitId]['items'][$itemIndex]['quantity'] > 1) {
            $this->splits[$splitId]['items'][$itemIndex]['quantity']--;
            $this->splits[$splitId]['items'][$itemIndex]['total'] = $this->splits[$splitId]['items'][$itemIndex]['quantity'] * $this->splits[$splitId]['items'][$itemIndex]['price'];
            if ($availableIndex !== false) {
                $this->availableItems[$availableIndex]['remaining']++;
            }
        } else {
            $this->removeItemFromSplit($splitId, $itemIndex);
            return;
        }
        $this->calculateSplitTotals();
    }

    // Update removeItemFromSplit to return all quantity to availableItems
    public function removeItemFromSplit($splitId, $itemIndex)
    {
        if (isset($this->splits[$splitId]['items'][$itemIndex])) {
            $item = $this->splits[$splitId]['items'][$itemIndex];
            $availableIndex = collect($this->availableItems)->search(fn($i) => $i['id'] === $item['id']);
            if ($availableIndex !== false) {
                $this->availableItems[$availableIndex]['remaining'] += $item['quantity'];
            }
            unset($this->splits[$splitId]['items'][$itemIndex]);
            $this->splits[$splitId]['items'] = array_values($this->splits[$splitId]['items']);
            $this->calculateSplitTotals();
        }
    }

    public function calculateSplitTotals()
    {
        foreach ($this->splits as &$split) {
            if (isset($split['items'])) {
                $split['total'] = collect($split['items'])->sum(function($item) {
                    return floatval($item['price']) * intval($item['quantity']);
                });
            }
        }
    }

    public function updatedSplits($value, $key)
    {
        if ($this->splitType === 'equal') {
            $totalSplitAmount = collect($this->splits)->sum('amount');
            $this->balanceAmount = $this->order->total - $totalSplitAmount;
        }
    }

    public function addNewSplit()
    {
        $this->numberOfSplits++;

        // Initialize the new split with default values
        $this->splits[$this->numberOfSplits] = [
            'id' => $this->numberOfSplits,
            'paymentMethod' => 'cash',
            'amount' => $this->order->total / $this->numberOfSplits
        ];

        // Recalculate all split amounts evenly
        $splitAmount = $this->order->total / $this->numberOfSplits;
        foreach ($this->splits as $i => $split) {
            if ($i > 0) { // Skip index 0
                $this->splits[$i]['amount'] = $splitAmount;
            }
        }

        $this->calculateBalanceAmount();
    }

    public function removeSplit($index)
    {
        if ($this->numberOfSplits > 2) {
            $this->numberOfSplits--;
            unset($this->splits[$index]);

            // Reindex the splits array
            $this->splits = array_values($this->splits);

            // Recalculate all split amounts evenly
            $splitAmount = $this->order->total / $this->numberOfSplits;
            foreach ($this->splits as $i => $split) {
                if ($i > 0) { // Skip index 0
                    $this->splits[$i]['amount'] = $splitAmount;
                }
            }

            $this->calculateBalanceAmount();
        }
    }

    private function calculateBalanceAmount()
    {
        $totalSplitAmount = collect($this->splits)->sum('amount');
        $this->balanceAmount = $this->order->total - $totalSplitAmount;
    }

    public function addNewCustomSplit()
    {
        $nextSplitNumber = max($this->customSplits) + 1;
        $this->customSplits[] = $nextSplitNumber;

        // Initialize the new split with default values
        $this->splits[$nextSplitNumber] = [
            'id' => $nextSplitNumber,
            'paymentMethod' => 'cash',
            'amount' => 0
        ];
        $this->updateBalanceAmount();
    }

    public function removeCustomSplit($splitNumber)
    {
        if (count($this->customSplits) > 2) {
            // Remove from customSplits array
            $this->customSplits = array_values(array_filter($this->customSplits, function($split) use ($splitNumber) {
                return $split !== $splitNumber;
            }));

            // Remove from splits array
            unset($this->splits[$splitNumber]);

            $this->updateBalanceAmount();
        }
    }

    public function addNewItemSplit()
    {
        $nextSplitId = max(array_keys($this->splits)) + 1;

        // Add new split with empty items array and its own payment method
        $this->splits[$nextSplitId] = [
            'id' => $nextSplitId,
            'items' => [],
            'paymentMethod' => 'cash',  // Each new split gets its own payment method
            'amount' => 0,
            'total' => 0
        ];

        $this->activeSplitId = $nextSplitId;
    }

    public function removeItemSplit($splitId)
    {
        if (count($this->splits) > 1) { // Keep at least one split
            // Return items to available pool
            if (isset($this->splits[$splitId]['items'])) {
                foreach ($this->splits[$splitId]['items'] as $item) {
                    $availableIndex = collect($this->availableItems)->search(fn($i) => $i['id'] === $item['id']);
                    if ($availableIndex !== false) {
                        $this->availableItems[$availableIndex]['remaining'] += $item['quantity'];
                    }
                }
            }

            // Remove the split
            unset($this->splits[$splitId]);

            // Reset active split to first split if removed split was active
            if ($this->activeSplitId === $splitId) {
                $this->activeSplitId = array_key_first($this->splits);
            }

            // Recalculate totals
            $this->calculateSplitTotals();
        }
    }

    public function addTipModal()
    {
        $this->tipAmount = $this->order->tip_amount ?? 0;
        $this->tipNote = $this->order->tip_note ?? '';
        $this->showTipModal = true;
    }

    public function addTip()
    {
        if (!$this->canAddTip) {
            $this->alert('error', __('messages.notHavePermission'), ['toast' => true]);
            return;
        }

        if (!$this->tipAmount || $this->tipAmount <= 0) {
            $this->tipAmount = 0;
        }

        $order = Order::find($this->order->id);

        $previousTip = floatval($order->tip_amount ?? 0);
        $newTip = floatval($this->tipAmount ?? 0);

        $order->total = floatval($order->total) - $previousTip + $newTip;
        $order->tip_amount = $newTip;
        $order->tip_note = $newTip > 0 ? $this->tipNote : null;
        $order->save();

        $this->order = $order;
        $this->showTipModal = false;

        $message = $newTip > 0 ? __('messages.tipAddedSuccessfully') : __('messages.tipRemovedSuccessfully');
        $this->alert('success', $message, ['toast' => true]);
        $this->updatedPaymentAmount();
        $this->updateAmountDetails();
    }

    public function setTip($mode, $value)
    {
        if ($mode === 'percentage') {
            $this->tipPercentage = $value;
            $this->tipAmount = ($value / 100) * $this->order->total;
        } else {
            $this->tipAmount = floatval($value);
        }
        $this->tipAmount = number_format($this->tipAmount, 2);
    }

    public function toggleTipMode()
    {
        $this->tipMode = $this->tipMode === 'percentage' ? 'amount' : 'percentage';
    }

}


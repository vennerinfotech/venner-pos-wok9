<?php

namespace App\Traits;

use Exception;
use App\Models\Kot;
use App\Models\Order;
use App\Models\KotPlace;
use App\Models\MultipleOrder;
use App\Models\Payment;
use Mike42\Escpos\Printer;
use App\Models\RestaurantTax;
use App\Models\ReceiptSetting;
use Mike42\Escpos\EscposImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Printer as PrinterSettings;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

trait PrinterSetting
{

    public function getPrinterSettingProperty()
    {
        return PrinterSettings::where('is_active', 1)
            ->get();
    }

    public function getPrinterConnector($printerSetting)
    {
        try {
            switch ($printerSetting->type) {
            case 'windows':
                $message = 'Using Windows Print Connector with share name: ' . $printerSetting->share_name;
                break;
            case 'network':
                $message = 'Using Network Print Connector with IP: ' . $printerSetting->ip_address . ' and port: 9100';
                break;
            case 'linux':
            default:
                $message = 'Using Linux (CUPS) Print Connector with share name: ' . $printerSetting->share_name;
                break;
            }

            return match ($printerSetting->type) {
                'windows' => new CupsPrintConnector($printerSetting->share_name),
                'network' => new NetworkPrintConnector($printerSetting->ip_address, $printerSetting->port ?? 9100),
                default => new CupsPrintConnector($printerSetting->share_name),
            };
        } catch (\Exception $e) {
            Log::error('Printer connection failed: ' . $e->getMessage());
            // Show alert with the error instead of throwing exception
            $this->alert('error', __('messages.printerNotFound', ['error' => $e->getMessage()]));
        }
    }

    public function hasModule($module)
    {
        return in_array($module, restaurant_modules());
    }

    public function handleKotPrint($kotId, $kotPlaceId = null, $alsoPrintOrder = false)
    {
        $kotPlace = KotPlace::findOrFail($kotPlaceId);

        $printerSetting = PrinterSettings::where('is_active', 1)
            ->where('id', $kotPlace->printer_id)
            ->first();

        if (!$printerSetting)
        {
            throw new \Exception(__('messages.noActiveKotPrinterConfigured'));
        }

        $this->printKotThermalDefault($kotId, $printerSetting, $kotPlaceId);

        if ($alsoPrintOrder) {
            $kot = Kot::findOrFail($kotId);
            $this->handleOrderPrint($kot->order_id);
        }
    }

    public function printKotAsPdf($kotId)
    {
         $kot = Kot::with('items.menuItem', 'items.menuItemVariation', 'items.modifierOptions', 'order.table')->findOrFail($kotId);
         $pdf = Pdf::loadView('pos.printKot', ['kot' => $kot])
             ->setPaper('A4')
             ->setWarnings(false);

         $filename = 'kot_' . $kotId . '.pdf';
         $path = storage_path('app/temp/' . $filename);
         $pdf->save($path);
         Storage::put('app/temp/' . $filename, $pdf->output());
         return $path;

    }

    public function printKotThermalDefault($kotId, $printerSetting, $kotPlaceId)
    {
        $kot = Kot::with([
        'items.menuItem',
        'items.menuItemVariation',
        'items.modifierOptions',
        'order.table',
        'order.customer',
        'order.waiter',
        'order.items.menuItem',
        'order.items.menuItemVariation',
        'order.items.modifierOptions',
        'order.charges.charge',
        'order.taxes.tax',
        'order.payments'
        ])->findOrFail($kotId);

        $kotPlace = KotPlace::findOrFail($kotPlaceId);

        $order = $kot->order;

        $items = isset($kotPlaceId) ? $kot->items->filter(function($item) use($kotPlaceId) {
                return $item->menuItem && $item->menuItem->kot_place_id == $kotPlaceId;
        }) : $kot->items;


        $restaurant = restaurant();

        $connector = $this->getPrinterConnector($printerSetting);
        $this->printer = new Printer($connector);
        $this->printer->initialize();
        $this->charPerLine = $this->getCharPerLine($printerSetting);
        $this->indentSize = $this->getIndentSize($printerSetting);
        $separator = str_repeat('-', $this->charPerLine) . "\n";

        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->setTextSize(1, 1);
        $this->printer->setEmphasis(true);

        $this->printer->text($restaurant->name . "\n");
        $this->printer->setEmphasis(true);
        $printer = $this->printer;
        // if restaurant address is set, print it
        if ($restaurant->address) {
            $addressLines = explode("\n", wordwrap($restaurant->address, $this->charPerLine, "\n", true));

            foreach ($addressLines as $line) {
                $printer->text($line . "\n");
            }
        }

        $this->printer->setEmphasis(false);

        // if kot place name is set, print it
        if ($this->hasModule('KOT') && $kotPlace->name) {
            $this->printer->text($kotPlace->name . "\n");
        }

        $this->printer->text($separator);
        $this->printer->setEmphasis(true);
        $this->printer->text(__('modules.kot.kitchen_order_ticket') . "\n");
        $this->printer->setEmphasis(false);

        $this->printer->text(__('modules.kot.order_number', ['number' => $order->order_number]) . "\n");
        $this->printer->text(__('modules.kot.kot_number', ['number' => $kot->kot_number]) . "\n");
        $this->printer->text(__('modules.kot.date', ['date' => $kot->created_at->format('d-m-Y')]) . "\n");
        $this->printer->text(__('modules.kot.time', ['time' => $kot->created_at->format('h:i A')]) . "\n\n");

        $itemText = __('modules.kot.item');

        $qtyText = __('modules.kot.qty');
            $itemHeader = str_pad($itemText, $this->charPerLine - mb_strlen($qtyText), ' ') . $qtyText;
            $this->printer->text($itemHeader . "\n");
        $this->printer->text($separator);

        foreach ($items as $item) {
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);

            $itemName = $item->menuItem->item_name;
            $totalWidth = $this->charPerLine;
            $qtyWidth = 3;
            $itemWidth = $totalWidth - $qtyWidth;

            if (mb_strlen($itemName, 'UTF-8') > ($itemWidth - 2)) {
                $itemName = mb_substr($itemName, 0, $itemWidth - 5, 'UTF-8') . '...';
            }

            $this->printer->setEmphasis(true);
            $spaces = $itemWidth - mb_strlen($itemName, 'UTF-8');
            $this->printer->text($itemName . str_repeat(' ', max(0, $spaces)) . $item->quantity . "\n");
            $this->printer->setEmphasis(false);

            $indentSize = $this->getIndentSize($printerSetting);

            $indent = str_repeat(' ', $indentSize) . '* ';
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);

            $subIndent = str_repeat(' ', $qtyWidth + 1); // Indent under item name

            if (isset($item->menuItemVariation)) {
                $variation = '• ' . $item->menuItemVariation->variation;
                $this->printer->text($subIndent . $variation . "\n");
            }

            foreach ($item->modifierOptions as $modifier) {
                $modText = '• ' . $modifier->name;

                if (isset($modifier->price) && $modifier->price > 0)
                {
                    $modText .= ' (+' . currency_format($modifier->price) . ')';
                }

                $this->printer->text($subIndent . $modText . "\n");
            }

            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text($separator);

            // Add a blank line after each item row
            $this->printer->text("\n");
        }

        if ($kot->note) {
            $this->printer->setEmphasis(true);
            $this->printer->text(__('modules.kot.special_instructions') . "\n");
            $this->printer->setEmphasis(false);
            $this->printer->text($kot->note . "\n");
            $this->printer->text($separator);
        }

        $this->printer->feed(1);
        $this->printer->cut();

        $this->printer->close();
        $this->loading = false;

        $this->alert('success', __('modules.kot.print_success'));
    }

    public function handleOrderPrint($orderId)
    {
        $orderPlace = MultipleOrder::first();

        $printerSetting = PrinterSettings::where('is_active', 1)
            ->where('id', $orderPlace->printer_id)
            ->first();

        if (!$printerSetting) {
            throw new \Exception('No active order printer configured.');
        }

        $this->printOrderThermal($orderId, $printerSetting);
    }

    public function printOrderThermal($orderId, $printerSetting)
    {
        $order = Order::with([
            'table', 'customer', 'waiter',
            'items.menuItem', 'items.menuItemVariation', 'items.modifierOptions',
            'charges.charge', 'taxes.tax', 'payments'
        ])->findOrFail($orderId);

        $restaurant = restaurant();
        $receiptSettings = ReceiptSetting::where('restaurant_id', $restaurant->id)->first();
        $connector = $this->getPrinterConnector($printerSetting);
        $printer = new Printer($connector);
        $charPerLine = $this->getCharPerLine($printerSetting);
        list($qtyWidth, $priceWidth, $amountWidth) = $this->getColumnWidths($charPerLine);
        $itemNameWidth = $charPerLine - ($qtyWidth + $priceWidth + $amountWidth + 3); // 3 for spaces

        $separator = str_repeat('-', $charPerLine) . "\n";

        $printer->initialize();
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(true);

        switch ($printerSetting->print_format ?? 'thermal80mm') {
        case 'thermal56mm':
            $printableWidth = 384;
                break;
        case 'thermal112mm':
            $printableWidth = 832;
                break;
        case 'thermal80mm':
        default:
            $printableWidth = 525;
                break;
        }

        $receiptSettings = ReceiptSetting::where('restaurant_id', $restaurant->id)->first();

        if ($receiptSettings->show_restaurant_logo == 1 && $restaurant->logo) {
            $logoPath = public_path('user-uploads/logo/' . $restaurant->logo);

            if (file_exists($logoPath) && is_readable($logoPath)) {
                // Set printable width based on paper size

                $desiredWidth = min(200, $printableWidth); // Don't exceed printable width
                $sourceImage = imagecreatefromstring(file_get_contents($logoPath));
                $origWidth = imagesx($sourceImage);
                $origHeight = imagesy($sourceImage);
                $aspectRatio = $origHeight / $origWidth;
                $newHeight = intval($desiredWidth * $aspectRatio);

                // Resize logo
                $resizedImage = imagecreatetruecolor($desiredWidth, $newHeight);
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $desiredWidth, $newHeight, $origWidth, $origHeight);

                // Create padded image
                $paddedImage = imagecreatetruecolor($printableWidth, $newHeight);
                $white = imagecolorallocate($paddedImage, 255, 255, 255);
                imagefill($paddedImage, 0, 0, $white);
                // Center the logo
                $x = intval(($printableWidth - $desiredWidth) / 2);
                imagecopy($paddedImage, $resizedImage, $x, 0, 0, 0, $desiredWidth, $newHeight);

                // Save to temp file
                $tmpLogoPath = sys_get_temp_dir() . '/resized_logo.png';
                imagepng($paddedImage, $tmpLogoPath);

                // Print
                $img = EscposImage::load($tmpLogoPath);
                $printer->bitImageColumnFormat($img);

                // Clean up
                imagedestroy($sourceImage);
                imagedestroy($resizedImage);
                imagedestroy($paddedImage);
                unlink($tmpLogoPath);
            }
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($restaurant->name . "\n");

        if ($restaurant->address) {
            $printer->text($restaurant->address . "\n");
        }

        if ($restaurant->phone_number) {
            $printer->text(__('modules.customer.phone') . ': ' . $restaurant->phone_number . "\n");
        }

        $printer->text($separator);

        // Order info row
        $orderNo = __('modules.kot.order_number', ['number' => $order->order_number]);
        $date = $order->created_at->format('d M Y H:i');
        $printer->text(str_pad($orderNo, $charPerLine - strlen($date)) . $date . "\n");

        $printer->text($separator);

        $indentSize = ($this->getIndentSize($printerSetting) + 2);
        $leftPad = str_repeat(' ', $this->getIndentSize($printerSetting) - 1);
        $printer->setJustification(Printer::JUSTIFY_LEFT);

         // Add Receipt Section
        if ($receiptSettings?->show_table_number && $order->table) {
            $printer->text($leftPad . str_pad(__('modules.settings.tableNumber'), $qtyWidth, ' ', STR_PAD_LEFT) . $order->table->table_code . "\n");
        }

        if ($receiptSettings?->show_total_guest && $order->number_of_pax) {
            $printer->text($leftPad . str_pad(__('modules.order.noOfPax').': ', $qtyWidth, ' ', STR_PAD_LEFT) . $order->number_of_pax . "\n");
        }

        if ($receiptSettings?->show_waiter && $order->waiter) {
            $printer->text($leftPad . str_pad(__('modules.order.waiter').': ', $qtyWidth, ' ', STR_PAD_LEFT) . $order->waiter->name . "\n");
        }

        if ($receiptSettings?->show_customer_name && $order->customer) {
            $printer->text($leftPad . str_pad(__('modules.customer.customer').': ', $qtyWidth, ' ', STR_PAD_LEFT) . $order->customer->name . "\n");
        }

        if ($receiptSettings?->show_customer_address && $order->customer) {
            $label = __('modules.customer.customerAddress') . ': ';
            $address = $order->customer->delivery_address;
            $maxWidth = $charPerLine - strlen($leftPad) - strlen($label);

            // First line: label + part of address
            $lines = wordwrap($address, $maxWidth, "\n", true);
            $lines = explode("\n", $lines);

            if (count($lines) > 0) {
                $printer->text($leftPad . $label . array_shift($lines) . "\n");

                foreach ($lines as $line) {
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->text($leftPad . str_repeat(' ', strlen($label)) . $line . "\n");
                }
            }
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($separator);

        // Table header
        $header = str_pad(__('modules.order.qty'), $qtyWidth) . ' ' .
            str_pad(__('modules.menu.itemName'), $itemNameWidth) . ' ' .
            str_pad(__('modules.order.price'), $priceWidth, ' ', STR_PAD_LEFT) . ' ' .
            str_pad(__('modules.order.amount'), $amountWidth, ' ', STR_PAD_LEFT);
        $printer->setEmphasis(true);
        $printer->text($header . "\n");
        $printer->setEmphasis(false);

        // Underline
        $printer->text($separator);

        $printer->setJustification(Printer::JUSTIFY_CENTER);

        // Items
        foreach ($order->items as $item) {
            $qty = str_pad($item->quantity, $qtyWidth);
            $name = $item->menuItem->item_name;
            $price = str_pad(currency_format($item->price), $priceWidth, ' ', STR_PAD_LEFT);
            $amount = str_pad(currency_format($item->amount), $amountWidth, ' ', STR_PAD_LEFT);

            // Calculate available width for name
            $nameWidth = $itemNameWidth;

            // Break the name into lines (array), like customer address
            $nameLines = explode("\n", wordwrap($name, $nameWidth, "\n", true));

            // Print the first line with qty, price, amount
            $printer->text($qty . ' ' . str_pad($nameLines[0], $nameWidth) . ' ' . $price . ' ' . $amount . "\n");

            // Print the rest of the lines, aligned under the item name
            $indent = str_repeat(' ', strlen($qty) + 4);
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            for ($i = 1; $i < count($nameLines); $i++) {
                $printer->text($indent . $nameLines[$i] . "\n");
            }

            // Use only 1 or 2 spaces after QTY for sub-lines
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            if ($item->menuItemVariation) {
                $variation = '• ' . $item->menuItemVariation->variation;
                $subIndent = str_repeat(' ', $indentSize);
                $printer->text($subIndent . $variation . "\n");
            }

            foreach ($item->modifierOptions as $modifier) {
                $modText = '- ' . $modifier->name;

                if (isset($modifier->price) && $modifier->price > 0) {
                    $modText .= ' (+' . currency_format($modifier->price) . ')';
                }

                $subIndent = str_repeat(' ', ($indentSize + 2));
                $printer->text($subIndent . $modText . "\n");
            }

            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // Add a blank line after each item row
            $printer->text("\n");
        }

        $printer->text("\n");

        // Summary lines
        $summary = [
            __('modules.order.subTotal') => currency_format($order->sub_total),
        ];

        if (!is_null($order->discount_amount)) {
            $discountLabel = __('modules.order.discount');

            if (isset($order->discount_type) && $order->discount_type == 'percent') {
                $discountValue = rtrim(rtrim($order->discount_value, '0'), '.');
                $discountLabel .= ' (' . $discountValue . '%)';
            }

            $amount = currency_format($order->discount_amount);
            $summary[$discountLabel] = '-' . $amount;
        }

        foreach ($order->charges as $charge) {
            $label = $charge->charge->charge_name;

            if ($charge->charge->charge_type === 'percent') {
                $label .= ' (' . $charge->charge->charge_value . '%)';
            }

            $amount = currency_format($charge->charge->getAmount($order->sub_total - ($order->discount_amount ?? 0)));
            $summary[$label] = $amount;
        }

        if ($order->tip_amount > 0) {
            $summary[__('modules.order.tip')] = currency_format($order->tip_amount);
        }

        // Add delivery fee if order type is delivery and delivery_fee is not null
        if (isset($order->order_type) && $order->order_type === 'delivery' && !is_null($order->delivery_fee)) {
            if ($order->delivery_fee > 0) {
                $summary[__('modules.delivery.deliveryFee')] = currency_format($order->delivery_fee, restaurant()->currency_id);
            }
            else {
                $summary[__('modules.delivery.deliveryFee')] = __('modules.delivery.freeDelivery');
            }
        }

        foreach ($order->taxes as $taxItem) {
            $label = $taxItem->tax->tax_name . ' (' . $taxItem->tax->tax_percent . '%)';
            $amount = currency_format(($taxItem->tax->tax_percent / 100) * ($order->sub_total - ($order->discount_amount ?? 0)));
            $summary[$label] = $amount;
        }

        if ($order->payments->first()?->balance > 0) {
            $summary[__('modules.order.balanceReturn')] = currency_format($order->payments->first()->balance);
        }

        // Print summary
        foreach ($summary as $label => $value) {
            $printer->text(str_pad($label . ':', $charPerLine - strlen($value), ' ') . $value . "\n");
        }

        // Bold total
        $printer->setEmphasis(true);
        $printer->text(str_pad(__('modules.order.total') . ':', $charPerLine - strlen(currency_format($order->total)), ' ') . currency_format($order->total) . "\n");
        $printer->setEmphasis(false);

        $printer->text($separator);

        // Thank you
        $printer->setJustification(Printer::JUSTIFY_CENTER);


        if ($receiptSettings->show_payment_qr_code && $order->status != 'paid') {
            $logoPath = public_path('user-uploads/payment_qr_code/' . $receiptSettings->payment_qr_code);

            if (file_exists($logoPath) && is_readable($logoPath)) {
                // Set printable width based on paper size

                $desiredWidth = min(200, $printableWidth); // Don't exceed printable width
                $sourceImage = imagecreatefromstring(file_get_contents($logoPath));
                $origWidth = imagesx($sourceImage);
                $origHeight = imagesy($sourceImage);
                $aspectRatio = $origHeight / $origWidth;
                $newHeight = intval($desiredWidth * $aspectRatio);

                // Resize logo
                $resizedImage = imagecreatetruecolor($desiredWidth, $newHeight);
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $desiredWidth, $newHeight, $origWidth, $origHeight);

                // Create padded image
                $paddedImage = imagecreatetruecolor($printableWidth, $newHeight);
                $white = imagecolorallocate($paddedImage, 255, 255, 255);
                imagefill($paddedImage, 0, 0, $white);
                // Center the logo
                $x = intval(($printableWidth - $desiredWidth) / 2);
                imagecopy($paddedImage, $resizedImage, $x, 0, 0, 0, $desiredWidth, $newHeight);

                // Save to temp file
                $tmpQRLogoPath = sys_get_temp_dir() . '/resized_qr_code.png';
                imagepng($paddedImage, $tmpQRLogoPath);

                // Print
                $qrImg = EscposImage::load($tmpQRLogoPath);
                $printer->bitImageColumnFormat($qrImg);

                // Clean up
                imagedestroy($sourceImage);
                imagedestroy($resizedImage);
                imagedestroy($paddedImage);
                unlink($tmpQRLogoPath);
            }
        }

        if ($receiptSettings->show_payment_qr_code && $order->status != 'paid') {
            $printer->text(__('modules.settings.payFromYourPhone') . "\n");

            $printer->text(__('modules.settings.scanQrCode') . "\n");
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);

        $printer->text(__('messages.thankYouVisit') . "\n\n");

        // Payment details
        if ($receiptSettings->show_payment_details && $order->payments->count()) {
            $printer->text($separator);
            $printer->setEmphasis(true);
            $printer->text(__('modules.order.paymentDetails') . "\n");
            $printer->setEmphasis(false);
            $printer->text($separator);

            // Table header
            $header = str_pad(__('modules.order.amount'), 10) . ' ' .
                str_pad(__('modules.order.paymentMethod'), 15) . ' ' .
                str_pad(__('app.dateTime'), 20);
            $printer->text($header . "\n");
            $printer->text($separator);

            foreach ($order->payments as $payment) {
                $amount = str_pad(currency_format($payment->amount), 10);
                $method = str_pad(__('modules.order.' . $payment->payment_method), 15);
                $date = '';
                if ($payment->payment_method != 'due') {
                    $date = $payment->created_at->timezone(config('app.timezone'))
                        ->format('d M, Y h:i A');
                }
                $date = str_pad($date, 20);
                $printer->text($amount . ' ' . $method . ' ' . $date . "\n");
            }
            $printer->text($separator);
        }

        $printer->feed($printerSetting->print_format === 'thermal80mm' ? 3 : 2);
        $printer->cut();
        $printer->close();
        $this->alert('success', __('modules.kot.print_success'));
    }

    public function printOrderAsPdf($orderId)
    {
        $order = Order::with('items.menuItem')->findOrFail($orderId);
        $receiptSettings = restaurant()->receiptSetting;
        $payment = Payment::where('order_id', $orderId)->first();
        $taxDetails = RestaurantTax::where('restaurant_id', restaurant()->id)->get();

        $pdf = Pdf::loadView('order.print', [
        'order' => $order,
        'receiptSettings' => $receiptSettings,
        'taxDetails' => $taxDetails,
        'payment' => $payment
        ])
            ->setPaper('A4')
            ->setWarnings(false);

        $filename = 'order_' . $orderId . '.pdf';
        $path = storage_path('app/temp/' . $filename);
        $pdf->save($path);
        Storage::put('app/temp/' . $filename, $pdf->output());

        return $path;
    }

    private function getCharPerLine($printerSetting)
    {
        switch ($printerSetting->print_format ?? 'thermal80mm') {
        case 'thermal56mm':
                return 28;
        case 'thermal112mm':
                return 58;
        case 'thermal80mm':
        default:
                return 42;
        }
    }

    private function getIndentSize($printerSetting)
    {
        switch ($printerSetting->print_format ?? 'thermal80mm') {
        case 'thermal56mm':
                return 10;
        case 'thermal112mm':
                return 2;
        case 'thermal80mm':
        default:
                return 4;
        }
    }

    private function getColumnWidths($charPerLine)
    {
        if ($charPerLine <= 32) { // 58mm
            return [3, 7, 7];
        }
        elseif ($charPerLine <= 48) { // 80mm
            return [4, 10, 10];
        }
        else { // 112mm or larger
            return [5, 12, 12];
        }
    }

}


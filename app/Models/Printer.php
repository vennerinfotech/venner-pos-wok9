<?php

namespace App\Models;

use App\Traits\HasBranch;
use App\Traits\HasRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Printer extends Model
{
    use HasBranch {
        HasBranch::booted insteadof HasRestaurant;
    }
    use HasRestaurant {
        HasRestaurant::booted as hasRestaurantBooted;
    }
    protected $guarded = ['id'];

    protected $appends = [
        'printer_connected',
    ];

    public function kots()
    {
        return $this->hasMany(KotPlace::class);
    }

    public function orders()
    {
        return $this->hasMany(MultipleOrder::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withoutGlobalScopes();
    }

    public function printerConnected(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->printing_choice == 'directPrint') {
                if ($this->type == 'windows') {

                    $isConnected = self::getUsbPrinterStatus($this->share_name);
                    return $isConnected ? true : false;
                }
            }

            if ($this->type == 'network') {
                $isConnected = self::isIpReachable($this->ip_address, $this->port);
                return $isConnected ? true : false;
            }

            return $this->is_connected ? true : false;
        });
    }

    public static function isIpReachable($ip, $port = 9100, $timeout = 2)
    {
        $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);

        if ($connection) {
            fclose($connection);
            return true;
        }

        return false;
    }

    // TODO: For later use
    public static function getConnectedPrinters()
    {
        // Run lpstat to list all printers
        $output = shell_exec('lpstat -v');

        if (!$output) {
            return [];
        }

        // Parse the output into a printer list
        $printers = [];

        foreach (explode("\n", trim($output)) as $line) {
            // Example line: device for HP_LaserJet_P1007: usb://HP/LaserJet%20P1007?serial=ABC123
            if (preg_match('/device for (.*?): (.*)/', $line, $matches)) {
                $printers[] = [
                    'name' => $matches[1],
                    'device' => $matches[2],
                ];
            }
        }

        return $printers;
    }

    // TODO: For later use
    public static function isPrinterOnline($ip, $port = 9100, $timeout = 2)
    {
        // First try to ping the printer
        $ping = shell_exec('ping -c 1 -W ' . $timeout . ' ' . escapeshellarg($ip));

        if (strpos($ping, '1 received') !== false) {
            // If ping is successful, try to connect to the printer port
            $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);

            if ($connection) {
                fclose($connection);
                return true;  // Printer is online and port is open
            }
        }

        return false; // Printer is offline or unreachable
    }

    public static function getUsbPrinterStatus($printerName)
    {
        $os = php_uname('s');

        if ($os == 'Windows') {
            return self::getWindowsPrinterStatus($printerName);
        }

        if ($os == 'Darwin') {

            return self::getMacPrinterStatus($printerName);
        }

        if ($os == 'Linux') {
            return self::getLinuxPrinterStatus($printerName);
        }

        return false;
    }

    public static function getMacPrinterStatus($printerName)
    {
        // dd($printerName);
        $safeName = escapeshellarg($printerName);
        $command = 'lpstat -p ' . $safeName;
        $status = shell_exec($command);

        if (empty($status)) {
            return false; // Printer not found or not installed
        }

        // If printer is disabled, it's offline
        if (strpos($status, 'disabled') !== false) {
            return false;
        }

        // For USB printers, check if
        // If printer is now printing, it's online
        if (strpos($status, 'now printing') !== false) {
            return true;
        }

        // If printer is idle and enabled, it's online
        if (strpos($status, 'idle') !== false && strpos($status, 'enabled') !== false) {
            return true;
        }

        // Otherwise, treat as offline
        return false;
    }

    public static function getWindowsPrinterStatus($printerName)
    {
        $command = 'wmic printer where "Name=\'' . addslashes($printerName) . '\'" get Name,WorkOffline,Status';
        $output = shell_exec($command);
        return $output ? true : false;
    }

    public static function getLinuxPrinterStatus($printerName)
    {
        $status = shell_exec('lpstat -p ' . escapeshellarg($printerName));
        return $status ? true : false;
    }

    public static function isUsbPrinterPhysicallyConnected($printerName)
    {
        // Use system_profiler to list USB devices
        $usbInfo = shell_exec('system_profiler SPUSBDataType');

        if (stripos($usbInfo, $printerName) !== false) {
            return true; // Printer is physically connected
        }

        return false; // Not found in USB devices
    }
}

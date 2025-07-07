<?php

namespace App\Http\Controllers;

use App\Models\Kot;
use App\Models\Order;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Illuminate\Support\Facades\Log;

class KotController extends Controller
{
    protected $connector;
    protected $printer;

    public function __construct()
    {
        // Initialize printer connection

    }

    public function index()
    {
        abort_if(!in_array('KOT', restaurant_modules()), 303);
        abort_if((!user_can('Manage KOT')), 303);
        return view('kot.index');
    }

    public function printKot($id , $kotPlaceid = null)
    {
        $kot = Kot::with('items', 'order', 'table')->find($id);
        return view('pos.printKot', [
            'kot' => $kot,
            'kotPlaceId' => $kotPlaceid
        ]);

    }

}


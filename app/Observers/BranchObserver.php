<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\OnboardingStep;
use Illuminate\Support\Facades\DB;

class BranchObserver
{

    public function created(Branch $branch)
    {
        // Add Onboarding Steps
        OnboardingStep::create(['branch_id' => $branch->id]);

        $branch->generateQrCode();

        $branch->generateKotSetting();

        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        foreach ($daysOfWeek as $day) {
            DB::table('reservation_settings')->insert([
            [
            'day_of_week' => $day,
            'time_slot_start' => '08:00:00',
            'time_slot_end' => '11:00:00',
            'time_slot_difference' => 30,
            'slot_type' => 'Breakfast',
            'created_at' => now(),
            'updated_at' => now(),
            'branch_id' => $branch->id,
            ],
            [
            'day_of_week' => $day,
            'time_slot_start' => '12:00:00',
            'time_slot_end' => '17:00:00',
            'time_slot_difference' => 60,
            'slot_type' => 'Lunch',
            'created_at' => now(),
            'updated_at' => now(),
            'branch_id' => $branch->id,
            ],
            [
            'day_of_week' => $day,
            'time_slot_start' => '18:00:00',
            'time_slot_end' => '22:00:00',
            'time_slot_difference' => 60,
            'slot_type' => 'Dinner',
            'created_at' => now(),
            'updated_at' => now(),
            'branch_id' => $branch->id,
            ]
            ]);
        }

        // Create Kitchen place
        $kotPlace = $branch->kotPlaces()->create([
            'name' => 'Default Kitchen',
            'branch_id' => $branch->id,
            'printer_id' => null, // Will update after printer is created
            'type' => 'food',
            'is_active' => true,
            'is_default' => true,
        ]);

        // Update all menu items for this branch to set kot_place_id to the default kitchen
         MenuItem::where('branch_id', $branch->id)->update(['kot_place_id' => $kotPlace->id]);

        // Create default order place
        $orderPlace = $branch->orderPlaces()->create([
            'name' => 'Default POS Terminal',
            'branch_id' => $branch->id,
            'printer_id' => null, // Will update after printer is created
            'type' => 'vegetarian',
            'is_active' => true,
            'is_default' => true,
        ]);

        // Create printer and assign KOT and Order place IDs
        $printer = $branch->printerSettings()->create([
            'name' => 'Default Thermal Printer',
            'restaurant_id' => $branch->restaurant_id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_default' => true,
            'printing_choice' => 'browserPopupPrint',
            'kots' => json_encode([$kotPlace->id]),
            'orders' => json_encode([$orderPlace->id]),
            'type' => null,
            'char_per_line' => null,
            'print_format' => null,
            'invoice_qr_code' => null,
            'open_cash_drawer' => null,
            'ipv4_address' => null,
            'thermal_or_nonthermal' => null,
            'share_name' => null,
        ]);

        // Update KOT and Order place with printer_id
        $kotPlace->printer_id = $printer->id;
        $kotPlace->save();

        $orderPlace->printer_id = $printer->id;
        $orderPlace->save();
    }

}

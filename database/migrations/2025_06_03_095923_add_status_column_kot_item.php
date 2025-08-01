<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kot_items', function (Blueprint $table) {
            $table->enum('status', ['cooking', 'ready'])->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kot_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

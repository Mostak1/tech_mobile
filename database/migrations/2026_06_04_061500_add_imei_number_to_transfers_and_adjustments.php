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
        if (!Schema::hasColumn('transfer_details', 'imei_number')) {
            Schema::table('transfer_details', function (Blueprint $table) {
                $table->text('imei_number')->nullable()->after('discount_method');
            });
        }

        if (!Schema::hasColumn('adjustment_details', 'imei_number')) {
            Schema::table('adjustment_details', function (Blueprint $table) {
                $table->text('imei_number')->nullable()->after('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('transfer_details', 'imei_number')) {
            Schema::table('transfer_details', function (Blueprint $table) {
                $table->dropColumn('imei_number');
            });
        }

        if (Schema::hasColumn('adjustment_details', 'imei_number')) {
            Schema::table('adjustment_details', function (Blueprint $table) {
                $table->dropColumn('imei_number');
            });
        }
    }
};

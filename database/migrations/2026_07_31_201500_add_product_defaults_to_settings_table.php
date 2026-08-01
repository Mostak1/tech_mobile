<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductDefaultsToSettingsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                if (! Schema::hasColumn('settings', 'default_product_type')) {
                    $table->string('default_product_type', 50)->nullable()->default('is_single');
                }
                if (! Schema::hasColumn('settings', 'default_unit_id')) {
                    $table->integer('default_unit_id')->nullable();
                }
                if (! Schema::hasColumn('settings', 'default_unit_sale_id')) {
                    $table->integer('default_unit_sale_id')->nullable();
                }
                if (! Schema::hasColumn('settings', 'default_unit_purchase_id')) {
                    $table->integer('default_unit_purchase_id')->nullable();
                }
                if (! Schema::hasColumn('settings', 'default_is_imei')) {
                    $table->boolean('default_is_imei')->default(0);
                }
                if (! Schema::hasColumn('settings', 'default_enable_serial_tracking')) {
                    $table->boolean('default_enable_serial_tracking')->default(0);
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $cols = ['default_product_type', 'default_unit_id', 'default_unit_sale_id', 'default_unit_purchase_id', 'default_is_imei', 'default_enable_serial_tracking'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
}

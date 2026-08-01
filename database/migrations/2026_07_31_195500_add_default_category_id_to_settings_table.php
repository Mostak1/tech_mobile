<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultCategoryIdToSettingsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('settings') && ! Schema::hasColumn('settings', 'default_category_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->integer('default_category_id')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'default_category_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('default_category_id');
            });
        }
    }
}

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
        if (!Schema::hasColumn('settings', 'facebook')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('facebook')->nullable()->after('CompanyAdress');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('settings', 'facebook')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('facebook');
            });
        }
    }
};

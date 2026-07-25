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
        // 1. Add enable_serial_tracking column to products table
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('enable_serial_tracking')->default(false)->after('is_imei');
        });

        // 2. Create product_serial_numbers table
        Schema::create('product_serial_numbers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true); // primary key using classical auto increment
            $table->integer('product_id')->index();
            $table->integer('variation_id')->nullable()->index();
            $table->integer('purchase_line_id')->nullable()->index();
            $table->string('serial_no', 191)->unique();
            $table->string('status', 32)->default('available'); // available, sold, transferred, in_service, returned, damaged, lost
            $table->integer('current_location_id')->nullable()->index(); // refers to warehouse
            $table->integer('current_customer_id')->nullable()->index(); // refers to client
            $table->integer('sold_sell_line_id')->nullable()->index(); // refers to sale_details
            $table->integer('service_job_id')->nullable()->index();
            $table->date('warranty_expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Create serial_number_histories table
        Schema::create('serial_number_histories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('serial_number_id')->index();
            $table->string('type', 32); // e.g., purchase, sell, transfer, return, adjustment, service
            $table->integer('reference_id')->nullable()->index(); // ID of the source transaction (purchase_id, sale_id, etc.)
            $table->integer('from_location_id')->nullable()->index();
            $table->integer('to_location_id')->nullable()->index();
            $table->integer('customer_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable()->index(); // User ID
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serial_number_histories');
        Schema::dropIfExists('product_serial_numbers');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('enable_serial_tracking');
        });
    }
};

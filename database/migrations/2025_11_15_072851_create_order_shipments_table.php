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
        Schema::create('t_order_shipments', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');

            // Basic info
            $table->string('courier')->default('delhivery'); // if you add more couriers later
            $table->enum('status', [
                'setup',        // just created when order is punched
                'pending',      // ready to be booked
                'booked',       // AWB generated
                'in_transit',
                'delivered',
                'cancelled',
                'failed'
            ])->default('setup');

            // Customer (shipping) details snapshot
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('shipping_address');
            $table->string('shipping_pin', 10)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 100)->nullable();

            // Amounts
            $table->enum('payment_mode', ['Prepaid', 'COD'])->default('Prepaid');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('cod_amount', 10, 2)->default(0);

            // Package summary
            $table->integer('quantity')->default(1);
            $table->decimal('weight', 8, 3)->nullable(); // in kg
            $table->string('products_description')->nullable();

            $table->unsignedBigInteger('pickup_location_id');
            // Pickup snapshot (in case you have multiple pickup locations)
            $table->string('pickup_name')->nullable();
            $table->text('pickup_address')->nullable();
            $table->string('pickup_pin', 10)->nullable();
            $table->string('pickup_city', 100)->nullable();
            $table->string('pickup_state', 100)->nullable();
            $table->string('pickup_phone', 20)->nullable();

            // Courier response data
            $table->string('awb_no')->nullable(); // Delhivery waybill
            $table->string('courier_reference')->nullable(); // any reference from Delhivery
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();

            // Important timestamps
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // New columns added
            $table->string('seller_name', 255)->nullable()->default(null);
            $table->string('seller_address', 255)->nullable()->default(null);
            $table->string('seller_invoice', 255)->nullable()->default(null);
            $table->string('shipment_length', 255)->nullable()->default(null);
            $table->string('shipment_width', 255)->nullable()->default(null);
            $table->string('shipment_height', 255)->nullable()->default(null);
            $table->string('shipping_mode', 255)->nullable()->default(null);
            $table->string('address_type', 255)->nullable()->default(null);
            $table->string('return_pin', 255)->nullable()->default(null);
            $table->string('return_city', 255)->nullable()->default(null);
            $table->string('return_state', 255)->nullable()->default(null);
            $table->string('return_phone', 255)->nullable()->default(null);
            $table->string('return_address', 255)->nullable()->default(null);
            $table->string('return_country', 255)->nullable()->default(null);
            
            $table->timestamps();

            // FKs (optional but recommended)
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_shipments');
    }
};

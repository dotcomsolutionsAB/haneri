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
        Schema::create('t_pickup_location', function (Blueprint $table) {
            $table->id();

            // Basic identity
            $table->string('name'); // Internal name (e.g. "Kolkata WH", "Memari Store")
            $table->string('code')->nullable(); // Your code, if any

            // Courier-specific fields (for Delhivery)
            $table->string('courier_pickup_name')->nullable(); // Must match Delhivery pickup name (e.g. "Burhanuddin")
            $table->string('courier_pickup_code')->nullable(); // if Delhivery gives some code

            // Contact details
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('alternate_phone', 20)->nullable();
            $table->string('email')->nullable();

            // Address
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('state', 100);
            $table->string('pin', 10);
            $table->string('country', 50)->default('India');

            // Flags
            $table->boolean('is_default')->default(false); // default pickup for new orders
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickup_location_models');
    }
};

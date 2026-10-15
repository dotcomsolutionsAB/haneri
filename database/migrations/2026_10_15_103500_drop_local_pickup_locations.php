<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_order_shipments') && Schema::hasColumn('t_order_shipments', 'pickup_location_id')) {
            Schema::table('t_order_shipments', function (Blueprint $table) {
                $table->dropColumn('pickup_location_id');
            });
        }

        Schema::dropIfExists('t_pickup_location');
        Schema::dropIfExists('pickup_location_models');
    }

    public function down(): void
    {
        if (!Schema::hasTable('t_pickup_location')) {
            Schema::create('t_pickup_location', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('courier_pickup_name')->nullable();
                $table->string('courier_pickup_code')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('alternate_phone', 20)->nullable();
                $table->string('email')->nullable();
                $table->string('address_line1');
                $table->string('address_line2')->nullable();
                $table->string('landmark')->nullable();
                $table->string('city', 100);
                $table->string('district', 100)->nullable();
                $table->string('state', 100);
                $table->string('pin', 10);
                $table->string('country', 50)->default('India');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('t_order_shipments') && !Schema::hasColumn('t_order_shipments', 'pickup_location_id')) {
            Schema::table('t_order_shipments', function (Blueprint $table) {
                $table->unsignedBigInteger('pickup_location_id')->nullable()->after('products_description');
            });
        }
    }
};

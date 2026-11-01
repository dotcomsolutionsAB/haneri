<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('t_product_variants', 'light_option')) {
                $table->string('light_option', 20)->nullable()->after('variant_value');
            }
        });

        $productId = DB::table('t_products')->where('slug', 'chicwind-ceiling-fan')->value('id');
        if (! $productId) {
            return;
        }

        DB::table('t_product_variants')
            ->where('product_id', $productId)
            ->where(function ($query) {
                $query->whereNull('light_option')->orWhere('light_option', '');
            })
            ->update(['light_option' => 'with']);

        $withLight = DB::table('t_product_variants')
            ->where('product_id', $productId)
            ->where('light_option', 'with')
            ->get();

        foreach ($withLight as $row) {
            $already = DB::table('t_product_variants')
                ->where('product_id', $productId)
                ->where('variant_value', $row->variant_value)
                ->where('light_option', 'without')
                ->exists();
            if ($already) {
                continue;
            }

            $copy = (array) $row;
            unset($copy['id']);
            $copy['light_option'] = 'without';
            if (! empty($copy['sku'])) {
                $copy['sku'] = $copy['sku'].'-NL';
            }
            $copy['created_at'] = now();
            $copy['updated_at'] = now();
            DB::table('t_product_variants')->insert($copy);
        }
    }

    public function down(): void
    {
        Schema::table('t_product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('t_product_variants', 'light_option')) {
                $table->dropColumn('light_option');
            }
        });
    }
};

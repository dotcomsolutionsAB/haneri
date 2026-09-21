<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shorten a long public blog slug used in storefront URLs.
     */
    public function up(): void
    {
        if (! Schema::hasTable('t_blogs')) {
            return;
        }

        $from = 'what-makes-haneri-ceiling-fans-different-from-regular-fans';
        $to = 'haneri-ceiling-fans-vs-regular-fans';

        if (DB::table('t_blogs')->where('slug', $to)->exists()) {
            return;
        }

        DB::table('t_blogs')
            ->where('slug', $from)
            ->update([
                'slug' => $to,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_blogs')) {
            return;
        }

        $from = 'haneri-ceiling-fans-vs-regular-fans';
        $to = 'what-makes-haneri-ceiling-fans-different-from-regular-fans';

        if (DB::table('t_blogs')->where('slug', $to)->exists()) {
            return;
        }

        DB::table('t_blogs')
            ->where('slug', $from)
            ->update([
                'slug' => $to,
                'updated_at' => now(),
            ]);
    }
};

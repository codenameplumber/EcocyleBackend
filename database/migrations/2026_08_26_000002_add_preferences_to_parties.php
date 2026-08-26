<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere for the preference switches to land.
 *
 * A JSON column rather than a column per switch: these are per-party display and
 * notification choices that will change shape as the product does, and none of
 * them is ever queried across parties. Anything that *is* queried — status,
 * frequency, capacity — stays a real column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('rating');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('integrity_score');
            $table->json('documents')->nullable()->after('preferences');
        });
    }

    public function down(): void
    {
        Schema::table('households', fn (Blueprint $t) => $t->dropColumn('preferences'));
        Schema::table('agents', fn (Blueprint $t) => $t->dropColumn(['preferences', 'documents']));
    }
};

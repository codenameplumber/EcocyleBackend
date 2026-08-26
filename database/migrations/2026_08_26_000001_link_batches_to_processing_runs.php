<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A processing run consumes batches. Without this link the runs table is a
 * standalone log — you can read that 400 kg went through digester A, but not
 * which households' material it was, which is exactly the traceability the
 * whole record exists to provide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_batches', function (Blueprint $table) {
            $table->foreignId('processing_run_id')->nullable()->after('delivery_id')
                ->constrained()->nullOnDelete();
            $table->index('processing_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('waste_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processing_run_id');
        });
    }
};

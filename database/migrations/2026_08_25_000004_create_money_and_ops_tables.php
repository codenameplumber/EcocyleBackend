<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money and operations.
 *
 * There is deliberately no `wallet_balance` column anywhere. A balance is
 * SUM(signed amounts) over an append-only ledger, because a mutable balance
 * column is how platforms end up paying somebody twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();             // PY-88101
            $table->morphs('party');                           // Household or Agent
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('Bank transfer');
            $table->enum('status', ['pending', 'processing', 'paid', 'failed', 'held'])->default('pending');
            $table->string('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('party');
            $table->string('direction');                       // credit | debit
            $table->decimal('amount', 12, 2);
            $table->string('kind');                            // settlement, payout, bonus, reversal
            // The guard against double-crediting on a retried webhook or a
            // double-clicked Accept button.
            $table->string('idempotency_key')->unique();
            $table->string('memo')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['party_type', 'party_id', 'created_at']);
        });

        Schema::create('integrity_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');                            // weight_variance, gps, duplicate...
            $table->string('severity')->default('medium');
            $table->string('detail');
            $table->json('evidence')->nullable();
            $table->enum('status', ['open', 'confirmed', 'dismissed'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });

        Schema::create('processing_runs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                  // RUN-2041
            $table->foreignId('processing_line_id')->constrained()->cascadeOnDelete();
            $table->decimal('input_kg', 10, 2);
            $table->decimal('output_kg', 10, 2)->nullable();
            $table->string('output_desc')->nullable();
            $table->decimal('yield_pct', 5, 2)->nullable();
            $table->enum('status', ['queued', 'running', 'complete', 'failed'])->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_runs');
        Schema::dropIfExists('integrity_flags');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payouts');
    }
};

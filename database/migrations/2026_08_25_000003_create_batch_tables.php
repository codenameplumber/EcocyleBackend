<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The centre of gravity.
 *
 * A delivery is ONE weighbridge event. An agent arrives with several batches on
 * board and crosses the scale once, so the net weight is apportioned across the
 * batches attached to that delivery. The prototype assumed one batch per weight;
 * this is where that assumption is corrected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                  // EC-D-2026-000119
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_kg', 10, 2)->nullable();
            $table->decimal('tare_kg', 10, 2)->default(0);
            $table->decimal('net_kg', 10, 2)->nullable();
            $table->string('weighbridge')->default('Gate 1');
            $table->timestamp('weighed_at')->nullable();
            $table->foreignId('weighed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'weighed', 'closed'])->default('open');
            $table->timestamps();
        });

        Schema::create('waste_batches', function (Blueprint $table) {
            $table->id();
            // Generated client-side so a collector can tag a batch with no signal.
            $table->ulid('ulid')->unique();
            $table->string('code')->unique();                  // EC-W-2026-0091827
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('waste_category_id')->constrained();
            // What the inspector actually found, which may differ from the declaration.
            $table->foreignId('confirmed_category_id')->nullable()->constrained('waste_categories')->nullOnDelete();

            $table->string('status')->default('REQUESTED')->index();

            // The three weights, kept deliberately apart.
            $table->decimal('expected_kg', 8, 2)->nullable();   // predicted, plans only
            $table->decimal('collected_kg', 8, 2)->nullable();  // agent declares
            $table->decimal('verified_kg', 8, 2)->nullable();   // the scale; settles money
            $table->decimal('variance_pct', 6, 2)->nullable();  // verified vs declared

            $table->char('grade', 1)->nullable();
            $table->json('contamination')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('classification')->nullable();         // model output, advisory

            // Settlement, computed once from verified_kg and frozen here.
            $table->decimal('rate_applied', 10, 2)->nullable();
            $table->decimal('value_naira', 12, 2)->nullable();
            $table->decimal('household_share', 12, 2)->nullable();
            $table->decimal('agent_share', 12, 2)->nullable();

            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->string('note')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();

            // One timestamp per transition -- the audit trail is the row history.
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspecting_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['agent_id', 'status']);
            $table->index(['household_id', 'status']);
        });

        Schema::create('batch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_batch_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['waste_batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_events');
        Schema::dropIfExists('waste_batches');
        Schema::dropIfExists('deliveries');
    }
};

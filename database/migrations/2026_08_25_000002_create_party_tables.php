<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three parties. Every one of them is a User with a role; the household and
 * agent profiles hang off that so authentication stays in one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('household')->after('email');
            // Email authenticates. Phone is how an agent actually reaches a
            // household at the gate, so it stays as a contact field.
            $table->string('phone')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->index('role');
        });

        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();                 // EC-H-0183
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('size')->default(4);
            $table->string('frequency')->default('Weekly');
            $table->foreignId('default_category_id')->nullable()->constrained('waste_categories')->nullOnDelete();
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->timestamps();
            $table->index(['area_id', 'status']);
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();                 // EC-A-0042
            $table->string('vehicle_type')->default('Tricycle');
            $table->unsignedInteger('capacity_kg')->default(260);
            $table->string('plate')->nullable();
            $table->string('operating_area')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            // Derived from weight variance, GPS consistency and rejections.
            $table->decimal('integrity_score', 5, 2)->default(100.00);
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->boolean('is_online')->default(false);
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['is_online', 'integrity_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
        Schema::dropIfExists('households');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};

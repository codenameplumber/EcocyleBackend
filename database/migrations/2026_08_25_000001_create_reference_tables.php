<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference data: the areas we serve, the streams we accept and their rates,
 * the facility, and its processing lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            // An area only opens once enough households cluster inside it.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('waste_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // organic, plastic, ...
            $table->string('label');
            // The single source of truth for pricing. The browser never decides this.
            $table->decimal('rate_per_kg', 10, 2);
            $table->string('color')->default('var(--c1)');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('daily_capacity_kg')->default(9500);
            $table->time('intake_open')->default('06:00');
            $table->time('intake_close')->default('18:00');
            $table->timestamps();
        });

        Schema::create('processing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('waste_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity_kg_day');
            $table->string('output_desc')->nullable();
            $table->enum('status', ['running', 'idle', 'offline'])->default('idle');
            $table->unsignedTinyInteger('utilisation')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_lines');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('waste_categories');
        Schema::dropIfExists('areas');
    }
};

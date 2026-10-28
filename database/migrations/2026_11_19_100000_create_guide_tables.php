<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('guide_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_site_id')->constrained('guide_sites')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('guide_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_site_id')->constrained('guide_sites')->cascadeOnDelete();
            $table->foreignId('guide_zone_id')->nullable()->constrained('guide_zones')->nullOnDelete();
            $table->string('title_fa', 150);
            $table->string('title_en', 150)->nullable();
            $table->string('slug')->unique();
            $table->string('category')->default('other')->index();
            $table->text('description')->nullable();
            $table->string('location_note')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('ready_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('guide_reference_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_object_id')->constrained('guide_objects')->cascadeOnDelete();
            $table->string('path');
            $table->json('embedding')->nullable();
            $table->string('status')->default('pending');
            $table->string('error', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('guide_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_object_id')->constrained('guide_objects')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('path')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('poster_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('language', 8)->default('fa');
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('guide_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_object_id')->nullable()->constrained('guide_objects')->nullOnDelete();
            $table->string('type')->index();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('guide_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->decimal('similarity_threshold', 4, 3)->default(0.700);
            $table->decimal('min_margin', 4, 3)->default(0.030);
            $table->unsignedInteger('frame_interval_ms')->default(1500);
            $table->unsignedSmallInteger('min_reference_images')->default(3);
            $table->timestamps();
        });

        DB::table('guide_sites')->insert(['name' => 'گنجنامه', 'slug' => 'ganjnameh', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_settings');
        Schema::dropIfExists('guide_events');
        Schema::dropIfExists('guide_videos');
        Schema::dropIfExists('guide_reference_images');
        Schema::dropIfExists('guide_objects');
        Schema::dropIfExists('guide_zones');
        Schema::dropIfExists('guide_sites');
    }
};

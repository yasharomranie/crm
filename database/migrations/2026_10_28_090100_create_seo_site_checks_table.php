<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_site_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->unique()->constrained('seo_projects')->cascadeOnDelete();
            $table->boolean('is_up')->default(true);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('alerted_down_at')->nullable();
            $table->unsignedSmallInteger('alerted_ssl_days')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_site_checks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('web_app_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_app_section_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('icon_path')->nullable();
            $table->string('accent')->default('orange');
            $table->string('link_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_app_section_items');
    }
};

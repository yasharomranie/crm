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
        Schema::create('seo_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website_url');
            $table->string('goal');
            $table->text('audience')->nullable();
            $table->string('market')->nullable();
            $table->string('brand')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_projects');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('late_penalty_percent')->default(100);
            $table->unsignedTinyInteger('no_show_penalty_percent')->default(100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cancellation_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancellation_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('min_days_before');
            $table->unsignedTinyInteger('penalty_percent');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_policy_rules');
        Schema::dropIfExists('cancellation_policies');
    }
};

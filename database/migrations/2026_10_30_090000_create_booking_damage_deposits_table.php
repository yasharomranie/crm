<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_damage_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->unsignedBigInteger('held_amount')->default(0);
            $table->string('method', 20)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('deducted_amount')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->string('settlement_note', 255)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_damage_deposits');
    }
};

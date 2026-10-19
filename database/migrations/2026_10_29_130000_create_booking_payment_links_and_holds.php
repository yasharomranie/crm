<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('authority')->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->string('ref_id')->nullable();
            $table->string('card_pan')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('hold_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('hold_expires_at');
        });

        Schema::dropIfExists('booking_payment_links');
    }
};

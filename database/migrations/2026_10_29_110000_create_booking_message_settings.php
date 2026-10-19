<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_message_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('confirmation_enabled')->default(false);
            $table->boolean('reminder_enabled')->default(false);
            $table->unsignedTinyInteger('reminder_days_before')->default(1);
            $table->boolean('feedback_enabled')->default(false);
            $table->text('confirmation_template')->nullable();
            $table->text('reminder_template')->nullable();
            $table->text('feedback_template')->nullable();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('arrival_reminder_sent_at')->nullable();
            $table->timestamp('feedback_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['confirmation_sent_at', 'arrival_reminder_sent_at', 'feedback_sent_at']);
        });

        Schema::dropIfExists('booking_message_settings');
    }
};

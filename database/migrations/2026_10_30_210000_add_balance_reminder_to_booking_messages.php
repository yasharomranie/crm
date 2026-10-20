<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_message_settings', function (Blueprint $table) {
            $table->boolean('balance_reminder_enabled')->default(false);
            $table->unsignedSmallInteger('balance_reminder_days_before')->default(3);
            $table->text('balance_reminder_template')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('balance_reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('balance_reminder_sent_at');
        });

        Schema::table('booking_message_settings', function (Blueprint $table) {
            $table->dropColumn(['balance_reminder_enabled', 'balance_reminder_days_before', 'balance_reminder_template']);
        });
    }
};

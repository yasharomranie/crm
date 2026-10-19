<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_alert_settings', function (Blueprint $table) {
            $table->boolean('notify_telegram')->default(false)->after('notify_push');
            $table->text('telegram_bot_token')->nullable()->after('notify_telegram');
            $table->string('telegram_chat_id', 64)->nullable()->after('telegram_bot_token');
            $table->string('telegram_api_base', 200)->nullable()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('seo_alert_settings', function (Blueprint $table) {
            $table->dropColumn(['notify_telegram', 'telegram_bot_token', 'telegram_chat_id', 'telegram_api_base']);
        });
    }
};

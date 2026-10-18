<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->json('closed_weekdays')->nullable()->after('closed_until');
            $table->unsignedInteger('entry_fee')->nullable()->after('closes_at');
            $table->string('phone', 50)->nullable()->after('address');
            $table->boolean('kid_friendly')->nullable()->after('entry_fee');
            $table->boolean('senior_friendly')->nullable()->after('kid_friendly');
            $table->boolean('has_parking')->nullable()->after('senior_friendly');
            $table->text('best_time_note')->nullable()->after('has_parking');
            $table->foreignId('product_id')->nullable()->after('best_time_note')->constrained('products')->nullOnDelete();
            $table->string('source', 20)->default('manual')->after('product_id');
            $table->dateTime('verified_at')->nullable()->after('source');
        });

        Schema::table('trip_plans', function (Blueprint $table) {
            $table->string('group_type', 20)->nullable()->after('pace');
            $table->boolean('has_car')->default(false)->after('group_type');
            $table->string('budget_level', 20)->nullable()->after('has_car');
            $table->string('fingerprint', 64)->nullable()->index()->after('overview');
            $table->string('generation_method', 20)->default('ai')->after('fingerprint');
            $table->unsignedInteger('generation_ms')->nullable()->after('generation_method');
            $table->unsignedSmallInteger('attractions_sent')->nullable()->after('generation_ms');
            $table->string('share_token', 40)->nullable()->unique()->after('attractions_sent');
            $table->unsignedTinyInteger('rating')->nullable()->after('share_token');
            $table->text('feedback')->nullable()->after('rating');
            $table->dateTime('reminder_sent_at')->nullable()->after('feedback');
        });

        Schema::table('trip_plan_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('travel_minutes')->nullable()->after('note');
        });

        Schema::table('trip_planner_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('plans_per_day_limit')->default(5)->after('is_enabled');
            $table->boolean('create_followup_tasks')->default(false)->after('plans_per_day_limit');
            $table->boolean('send_reminders')->default(false)->after('create_followup_tasks');
        });
    }

    public function down(): void
    {
        Schema::table('trip_planner_settings', function (Blueprint $table) {
            $table->dropColumn(['plans_per_day_limit', 'create_followup_tasks', 'send_reminders']);
        });

        Schema::table('trip_plan_items', function (Blueprint $table) {
            $table->dropColumn('travel_minutes');
        });

        Schema::table('trip_plans', function (Blueprint $table) {
            $table->dropColumn(['group_type', 'has_car', 'budget_level', 'fingerprint', 'generation_method', 'generation_ms', 'attractions_sent', 'share_token', 'rating', 'feedback', 'reminder_sent_at']);
        });

        Schema::table('attractions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['closed_weekdays', 'entry_fee', 'phone', 'kid_friendly', 'senior_friendly', 'has_parking', 'best_time_note', 'source', 'verified_at']);
        });
    }
};

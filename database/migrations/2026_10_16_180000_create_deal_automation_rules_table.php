<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * deal_automation_rules - "when a deal enters stage X, schedule this
     * follow-up". deals.stale_notified_at - when the idle-deal digest last
     * included a deal; compared with updated_at so a deal that has been
     * touched since is eligible to be flagged again.
     */
    public function up(): void
    {
        Schema::create('deal_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger_stage');
            $table->string('task_title');
            $table->unsignedInteger('due_after_hours')->default(24);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('stale_notified_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('stale_notified_at');
        });

        Schema::dropIfExists('deal_automation_rules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * assigned_to - who is responsible for answering the ticket (and so who
     * gets the SLA-breach alert; unassigned tickets alert the admins).
     * sla_breach_notified_at - set once the alert has been sent, so the
     * recurring sweep never alerts twice for the same ticket.
     */
    public function up(): void
    {
        Schema::table('guest_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('sla_breach_notified_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('guest_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('sla_breach_notified_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named admission_tickets, not tickets - this app already has a
     * `tickets`-adjacent concept (GuestTicket, a customer-support ticket
     * with its own TicketStatus/TicketPriority enums) that this must never
     * be confused with, in code or in the database.
     */
    public function up(): void
    {
        Schema::create('admission_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_variant_id')->constrained();
            $table->string('status')->default('active');
            $table->unsignedTinyInteger('allowed_uses');
            $table->unsignedTinyInteger('used_uses')->default(0);
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->string('source_type');
            // Wired to ticket_template_versions once that table exists -
            // unconstrained for the same migration-ordering reason as
            // elsewhere in this feature. Captured once at issuance and
            // never changed - see section 46/47 (template versioning).
            $table->unsignedBigInteger('ticket_template_version_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('external_ticket_id')->nullable()->index();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_tickets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A time-boxed price history rather than a single price column on the
     * variant - this is what lets TicketVariant::currentPrice() change
     * going forward without ever touching an already-issued ticket, whose
     * own unit_price was captured at issuance time onto its order_item
     * (see AdmissionTicket/OrderItem - the real snapshot).
     */
    public function up(): void
    {
        Schema::create('ticket_variant_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ticket_variant_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_variant_prices');
    }
};

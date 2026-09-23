<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Complimentary issue is never just a 100%-off DiscountCode use -
     * it's its own authorized, permission-gated free-issuance event
     * (section 31-36), recorded here and always distinguishable from a
     * discounted Order in every report. order_id links it back to the
     * normal Order/OrderItem/AdmissionTicket chain the ticket was still
     * issued through (source_type=complimentary on that order).
     */
    public function up(): void
    {
        Schema::create('complimentary_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('ticket_variant_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('original_value');
            $table->string('reason');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->constrained('users');
            $table->dateTime('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complimentary_issues');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Declares what a Gate will accept - see
     * App\Domain\Ticketing\GateValidationService::isVariantAllowedAtGate().
     * A gate has zero or more rules; a scan is allowed only if at least
     * one active rule matches the ticket's product/variant/direction/next
     * usage stage. Nothing here is hard-coded per product - an admin adds
     * rows, the code just evaluates them.
     */
    public function up(): void
    {
        Schema::create('gate_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_variant_id')->nullable()->constrained()->cascadeOnDelete();
            // Null = no direction restriction (matches any/no direction).
            $table->string('direction')->nullable();
            // Null = matches any usage stage; 1 = only the ticket's first
            // scan, 2 = only its second, etc. This is what makes "return
            // before outbound" rejectable without hard-coding it: a
            // round-trip's return-stage rule sets usage_stage=2, so a
            // ticket that hasn't consumed stage 1 yet simply has no
            // matching rule and gets NO_REMAINING_USAGE/SEQUENCE_ERROR.
            $table->unsignedTinyInteger('usage_stage')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['gate_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_rules');
    }
};

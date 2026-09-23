<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            // Total scans this variant grants across its lifetime - 1 for a
            // one-way ticket, 2 for a round trip (outbound stage, then
            // return stage), etc. Never mutated after issuance.
            $table->unsignedTinyInteger('allowed_uses')->default(1);
            // Null for a variant with no inherent direction (e.g. a single
            // aquarium admission); outbound/return only makes sense for a
            // sequential product like a cable car.
            $table->string('direction')->nullable();
            // How many days after issuance the ticket stays valid - the
            // simplest of the validity models section 16 calls for
            // (date-based); time-slot/session tickets are a later addition
            // that only needs new columns here, not a redesign.
            $table->unsignedInteger('default_validity_days')->default(1);
            // Wired to ticket_templates once that table exists (see its own
            // migration) - kept unconstrained here purely to avoid a
            // migration-ordering dependency, not because it's optional data.
            $table->unsignedBigInteger('ticket_template_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_variants');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_code_ticket_variant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_variant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['discount_code_id', 'ticket_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_code_ticket_variant');
    }
};

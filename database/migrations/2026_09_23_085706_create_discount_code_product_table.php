<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A discount code with zero rows here applies to every product -
     * restriction only kicks in once at least one row exists (same
     * "empty = unrestricted" convention as discount_code_ticket_variant).
     */
    public function up(): void
    {
        Schema::create('discount_code_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['discount_code_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_code_product');
    }
};

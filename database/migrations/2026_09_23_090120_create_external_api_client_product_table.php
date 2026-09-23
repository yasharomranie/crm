<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empty = unrestricted (this client may sell any product), the same
     * convention as discount_code_product - restriction only kicks in
     * once at least one row exists.
     */
    public function up(): void
    {
        Schema::create('external_api_client_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_api_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['external_api_client_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_api_client_product');
    }
};

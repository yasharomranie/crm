<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('method', 20);
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at');
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};

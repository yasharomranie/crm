<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('source', 30);
            $table->string('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guest_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_consents');
    }
};

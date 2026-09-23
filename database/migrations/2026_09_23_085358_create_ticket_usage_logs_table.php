<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every scan attempt, successful or rejected - unlike ticket_usages
     * (real consumptions only), this exists purely for the gate's own
     * audit trail and reporting (section 8/50). admission_ticket_id is
     * nullable since an invalid/unknown QR never resolves to a ticket at
     * all.
     */
    public function up(): void
    {
        Schema::create('ticket_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction')->nullable();
            $table->unsignedTinyInteger('usage_number')->nullable();
            $table->string('result');
            $table->string('reason')->nullable();
            $table->string('device_identifier')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['gate_id', 'scanned_at']);
            $table->index('result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_usage_logs');
    }
};

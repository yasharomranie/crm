<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per offline-recorded scan, keyed by a client-generated
     * client_scan_id (a UUID minted in the browser the moment the scan
     * happens offline, not a server-assigned id) - this is what makes
     * pushing the same queued scan twice (a retried sync request after a
     * network hiccup) a safe no-op instead of double-consuming the
     * ticket. The actual usage/log rows this produces still go through
     * the normal GateValidationService -> ticket_usages/ticket_usage_logs
     * path; this table only guards against re-processing.
     */
    public function up(): void
    {
        Schema::create('gate_offline_scan_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_id')->constrained()->cascadeOnDelete();
            $table->string('client_scan_id');
            $table->foreignId('admission_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result');
            $table->string('reason')->nullable();
            $table->unsignedInteger('usage_number')->nullable();
            // What the browser itself decided while still offline, kept
            // alongside the real server-computed result/reason above so a
            // mismatch (e.g. the same ticket was also scanned at another
            // gate while this one was offline) is visible for staff review
            // rather than silently overwritten.
            $table->string('local_result');
            $table->string('local_reason')->nullable();
            $table->dateTime('scanned_at');
            $table->timestamps();

            $table->unique(['gate_id', 'client_scan_id'], 'gate_offline_scan_syncs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_offline_scan_syncs');
    }
};

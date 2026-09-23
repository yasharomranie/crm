<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guards POST /api/v1/external/tickets (section 116): the same
     * Idempotency-Key from the same client always replays the original
     * response instead of creating a second order/ticket. request_hash
     * catches a client reusing a key with a DIFFERENT payload (a client
     * bug, not a legitimate retry) so that case can be rejected instead
     * of silently returning an unrelated ticket.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_api_client_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('request_hash');
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamps();

            $table->unique(['external_api_client_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};

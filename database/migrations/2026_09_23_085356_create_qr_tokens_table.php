<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The QR code printed on a ticket encodes `token` alone - never the
     * ticket_number, never the admission_tickets.id. token is a
     * cryptographically random, unguessable string (see
     * App\Domain\Ticketing\QrTokenGenerator); the server is the only
     * thing that can resolve it back to a ticket (section 13/53).
     */
    public function up(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_tokens');
    }
};

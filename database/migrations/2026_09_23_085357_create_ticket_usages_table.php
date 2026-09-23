<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per successful consumption (never per attempt - see
     * ticket_usage_logs for every attempt, success or not). The unique
     * constraint on (admission_ticket_id, usage_number) is the real,
     * database-enforced defense against double-consuming a single stage
     * under concurrent scans - see
     * App\Domain\Ticketing\GateValidationService::validateAndConsume(),
     * which relies on this constraint throwing rather than on locking
     * alone.
     */
    public function up(): void
    {
        Schema::create('ticket_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('usage_number');
            $table->string('direction')->nullable();
            $table->timestamps();

            $table->unique(['admission_ticket_id', 'usage_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_usages');
    }
};

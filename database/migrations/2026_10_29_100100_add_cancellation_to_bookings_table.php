<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('cancellation_policy_id')->nullable()->constrained('cancellation_policies')->nullOnDelete();
            $table->json('cancellation_terms')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_kind', 20)->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->unsignedBigInteger('cancellation_fee')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancellation_policy_id');
            $table->dropColumn(['cancellation_terms', 'cancelled_at', 'cancellation_kind', 'cancellation_reason', 'cancellation_fee']);
        });
    }
};

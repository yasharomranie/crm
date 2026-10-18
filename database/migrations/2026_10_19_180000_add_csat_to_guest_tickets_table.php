<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('csat_score')->nullable()->after('resolved_at');
            $table->text('csat_comment')->nullable()->after('csat_score');
            $table->dateTime('csat_responded_at')->nullable()->after('csat_comment');
        });
    }

    public function down(): void
    {
        Schema::table('guest_tickets', function (Blueprint $table) {
            $table->dropColumn(['csat_score', 'csat_comment', 'csat_responded_at']);
        });
    }
};

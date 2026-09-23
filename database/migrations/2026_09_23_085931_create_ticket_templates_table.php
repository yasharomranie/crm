<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ticket_variants.ticket_template_id was created unconstrained
        // (this table didn't exist yet in migration order) - wire the FK
        // up now.
        Schema::table('ticket_variants', function (Blueprint $table) {
            $table->foreign('ticket_template_id')->references('id')->on('ticket_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_variants', function (Blueprint $table) {
            $table->dropForeign(['ticket_template_id']);
        });

        Schema::dropIfExists('ticket_templates');
    }
};

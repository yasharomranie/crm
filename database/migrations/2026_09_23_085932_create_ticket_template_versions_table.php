<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A template is never edited in place once a version has issued a
     * ticket - editing creates a new version instead (section 46). Every
     * AdmissionTicket freezes ticket_template_version_id at issuance, so
     * a later template change/republish can never alter how an old
     * ticket prints.
     */
    public function up(): void
    {
        Schema::create('ticket_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->unsignedInteger('width_mm')->default(100);
            $table->unsignedInteger('height_mm')->default(150);
            $table->string('background_image')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_template_id', 'version_number']);
            $table->index('status');
        });

        // admission_tickets.ticket_template_version_id was created
        // unconstrained (this table didn't exist yet at that point) -
        // wire the FK up now.
        Schema::table('admission_tickets', function (Blueprint $table) {
            $table->foreign('ticket_template_version_id')->references('id')->on('ticket_template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admission_tickets', function (Blueprint $table) {
            $table->dropForeign(['ticket_template_version_id']);
        });

        Schema::dropIfExists('ticket_template_versions');
    }
};

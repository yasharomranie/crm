<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * external_api_client_id is the same ownership boundary as
     * orders/bookings/deals. submitted_by stays NOT NULL on purpose - the
     * auto-archive-to-Document pipeline (FormSubmissionObserver::archive())
     * requires a real uploaded_by, so an API-originated submission is
     * attributed to its ExternalApiClient's own created_by user rather than
     * left blank (see FormSubmissionController - it errors clearly if that
     * client has no created_by set, instead of this column silently going
     * nullable and breaking archiving for every other caller too).
     */
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->foreignId('external_api_client_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('external_api_client_id');
        });
    }
};

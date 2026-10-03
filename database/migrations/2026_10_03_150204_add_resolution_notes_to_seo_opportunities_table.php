<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seo_opportunities', function (Blueprint $table) {
            // Deliberately separate from `evidence` - OpportunityEngine
            // overwrites that column wholesale on every re-detection, which
            // would silently wipe an operator's own decision the next time
            // the same opportunity gets rescanned.
            $table->text('resolution_notes')->nullable()->after('recommended_actions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_opportunities', function (Blueprint $table) {
            $table->dropColumn('resolution_notes');
        });
    }
};

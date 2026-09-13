<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            // Null latitude/longitude means this shift has no location
            // restriction (e.g. remote staff) - check-in/out then skips the
            // geofence check entirely for anyone on this shift.
            $table->decimal('latitude', 10, 7)->nullable()->after('grace_minutes');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('radius_meters')->default(200)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_meters']);
        });
    }
};

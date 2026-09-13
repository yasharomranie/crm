<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Real MAC addresses are not obtainable from a web browser on
            // any platform (a deliberate, universal browser privacy
            // restriction) - "device" here is a best-effort browser/device
            // fingerprint (user agent + screen + timezone, hashed), which
            // is what a web-based check-in can actually see. It's useful
            // for spotting "this doesn't look like their usual phone", not
            // as a forgery-proof identifier.
            $table->string('check_in_ip')->nullable()->after('check_in_at');
            $table->string('check_in_device')->nullable()->after('check_in_ip');
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('check_in_device');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');

            $table->string('check_out_ip')->nullable()->after('check_out_at');
            $table->string('check_out_device')->nullable()->after('check_out_ip');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_out_device');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_ip', 'check_in_device', 'check_in_latitude', 'check_in_longitude',
                'check_out_ip', 'check_out_device', 'check_out_latitude', 'check_out_longitude',
            ]);
        });
    }
};

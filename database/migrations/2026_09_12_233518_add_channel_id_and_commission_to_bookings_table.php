<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('sales_channel_id')->nullable()->after('source')->constrained('sales_channels')->nullOnDelete();
            $table->decimal('commission_amount', 14, 0)->default(0)->after('sales_channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_channel_id');
            $table->dropColumn('commission_amount');
        });
    }
};

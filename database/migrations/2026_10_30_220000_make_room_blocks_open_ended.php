<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->date('ends_on')->nullable()->change();
            $table->string('source', 20)->nullable()->after('reason');
        });

        // Units already flagged «خارج از سرویس» on the room itself become open-ended blocks,
        // so the list of out-of-service rooms and the rack finally agree.
        $now = now();

        DB::table('rooms')->where('status', 'out_of_service')->pluck('id')->each(function (int $roomId) use ($now) {
            $hasOpenBlock = DB::table('room_blocks')->where('room_id', $roomId)->whereNull('ends_on')->exists();

            if (! $hasOpenBlock) {
                DB::table('room_blocks')->insert([
                    'room_id' => $roomId,
                    'starts_on' => $now->toDateString(),
                    'ends_on' => null,
                    'reason' => 'خارج از سرویس (از وضعیت اتاق)',
                    'source' => 'status',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('room_blocks')->whereNull('ends_on')->delete();

        Schema::table('room_blocks', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->date('ends_on')->nullable(false)->change();
        });
    }
};

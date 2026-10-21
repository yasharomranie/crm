<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Every mission gets its own files instead of a shared library. A file that
 * was attached to one mission now belongs to it; a file shared by several
 * missions is copied (row, pieces and the file itself) so each keeps its own,
 * with the member limits, cited pieces and file choices following the copy.
 * Files attached to no mission keep no owner mission and are not shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_mission_sources', function (Blueprint $table) {
            $table->foreignId('ai_mission_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        $this->moveSourcesToTheirMissions();

        Schema::dropIfExists('ai_mission_source_mission');
    }

    public function down(): void
    {
        Schema::create('ai_mission_source_mission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_mission_source_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ai_mission_id', 'ai_mission_source_id'], 'ai_mission_source_unique');
        });

        foreach (DB::table('ai_mission_sources')->whereNotNull('ai_mission_id')->get(['id', 'ai_mission_id']) as $source) {
            DB::table('ai_mission_source_mission')->insert(['ai_mission_id' => $source->ai_mission_id, 'ai_mission_source_id' => $source->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('ai_mission_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_mission_id');
        });
    }

    /**
     * Gives each file of the old shared library to the mission it was attached
     * to (copying it for every further mission). Public so a test can feed it
     * legacy rows.
     */
    public function moveSourcesToTheirMissions(): void
    {
        $attached = DB::table('ai_mission_source_mission')->orderBy('id')->get()->groupBy('ai_mission_source_id');

        foreach ($attached as $sourceId => $rows) {
            $missionIds = $rows->pluck('ai_mission_id')->values();

            DB::table('ai_mission_sources')->where('id', $sourceId)->update(['ai_mission_id' => $missionIds[0]]);

            foreach ($missionIds->slice(1) as $missionId) {
                $this->copyForMission((int) $sourceId, (int) $missionId);
            }
        }
    }

    private function copyForMission(int $sourceId, int $missionId): void
    {
        $original = (array) DB::table('ai_mission_sources')->where('id', $sourceId)->first();
        unset($original['id']);

        if (filled($original['path']) && Storage::disk('local')->exists($original['path'])) {
            $copyPath = 'mission-sources/'.Str::uuid().'.'.$original['extension'];
            Storage::disk('local')->copy($original['path'], $copyPath);
            $original['path'] = $copyPath;
        }

        $copyId = DB::table('ai_mission_sources')->insertGetId([...$original, 'ai_mission_id' => $missionId]);

        $chunkMap = [];

        foreach (DB::table('ai_mission_source_chunks')->where('ai_mission_source_id', $sourceId)->orderBy('position')->get() as $chunk) {
            $chunkMap[$chunk->id] = DB::table('ai_mission_source_chunks')->insertGetId([
                'ai_mission_source_id' => $copyId,
                'position' => $chunk->position,
                'heading' => $chunk->heading,
                'content' => $chunk->content,
                'created_at' => $chunk->created_at,
                'updated_at' => $chunk->updated_at,
            ]);
        }

        foreach ($chunkMap as $oldChunkId => $newChunkId) {
            DB::table('ai_mission_cards')->where('ai_mission_id', $missionId)->where('source_chunk_id', $oldChunkId)->update(['source_chunk_id' => $newChunkId]);
        }

        DB::table('ai_mission_role_source')
            ->where('ai_mission_source_id', $sourceId)
            ->whereIn('ai_mission_role_id', DB::table('ai_mission_roles')->where('ai_mission_id', $missionId)->pluck('id'))
            ->update(['ai_mission_source_id' => $copyId]);

        // The approved brief named the old file ids; it must be approved again.
        DB::table('ai_missions')->where('id', $missionId)->update(['brief_sources_hash' => null]);
    }
};

<?php

namespace Tests\Feature;

use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionCard;
use App\Models\AiMissionRole;
use App\Models\AiMissionSource;
use App\Models\AiMissionSourceChunk;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The step from one shared library to a library per mission: each old file
 * goes to the mission it was attached to and is copied for any other mission.
 */
class AiMissionSourceLibrarySplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_files_are_copied_per_mission_and_everything_that_pointed_at_them_follows(): void
    {
        Storage::fake('local');
        Schema::create('ai_mission_source_mission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_mission_source_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        $owner = User::factory()->create();
        $first = AiMission::factory()->create(['created_by' => $owner->id, 'brief_sources_hash' => 'first-hash']);
        $second = AiMission::factory()->create(['created_by' => $owner->id, 'brief_sources_hash' => 'second-hash']);
        $secondRole = AiMissionRole::factory()->create(['ai_mission_id' => $second->id, 'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $owner->id])->id]);

        Storage::disk('local')->put('mission-sources/shared.txt', 'متن مشترک');
        $shared = AiMissionSource::factory()->create(['user_id' => $owner->id, 'ai_mission_id' => null, 'extension' => 'txt', 'path' => 'mission-sources/shared.txt', 'title' => 'مشترک']);
        $one = AiMissionSourceChunk::factory()->create(['ai_mission_source_id' => $shared->id, 'position' => 1, 'content' => 'بخش اول']);
        AiMissionSourceChunk::factory()->create(['ai_mission_source_id' => $shared->id, 'position' => 2, 'content' => 'بخش دوم']);
        $onlyFirst = AiMissionSource::factory()->create(['user_id' => $owner->id, 'ai_mission_id' => null]);
        $nobody = AiMissionSource::factory()->create(['user_id' => $owner->id, 'ai_mission_id' => null]);

        $card = AiMissionCard::create([
            'ai_mission_id' => $second->id, 'ai_assistant_profile_id' => $secondRole->ai_assistant_profile_id, 'role' => 'specialist', 'round' => 1,
            'kind' => 'finding', 'claim' => 'ادعا', 'source' => 'x', 'source_chunk_id' => $one->id, 'confidence' => 'high', 'unsourced' => false,
        ]);
        DB::table('ai_mission_role_source')->insert(['ai_mission_role_id' => $secondRole->id, 'ai_mission_source_id' => $shared->id]);

        foreach ([[$first->id, $shared->id], [$second->id, $shared->id], [$first->id, $onlyFirst->id]] as [$missionId, $sourceId]) {
            DB::table('ai_mission_source_mission')->insert(['ai_mission_id' => $missionId, 'ai_mission_source_id' => $sourceId, 'created_at' => now(), 'updated_at' => now()]);
        }

        (require database_path('migrations/2026_11_08_100000_make_ai_mission_sources_belong_to_one_mission.php'))->moveSourcesToTheirMissions();

        $this->assertSame($first->id, $shared->fresh()->ai_mission_id);
        $this->assertSame($first->id, $onlyFirst->fresh()->ai_mission_id);
        $this->assertNull($nobody->fresh()->ai_mission_id);

        $copy = AiMissionSource::where('ai_mission_id', $second->id)->sole();
        $this->assertNotSame($shared->id, $copy->id);
        $this->assertSame('مشترک', $copy->title);
        $this->assertNotSame($shared->path, $copy->path);
        Storage::disk('local')->assertExists($copy->path);
        Storage::disk('local')->assertExists($shared->path);
        $this->assertSame(['بخش اول', 'بخش دوم'], $copy->chunks()->pluck('content')->all());

        $this->assertSame($copy->chunks()->where('position', 1)->value('id'), $card->fresh()->source_chunk_id);
        $this->assertSame([$copy->id], $secondRole->sources()->pluck('ai_mission_sources.id')->all());
        $this->assertNull($second->fresh()->brief_sources_hash, 'a copied file needs the brief approved again');
        $this->assertSame('first-hash', $first->fresh()->brief_sources_hash);
    }
}

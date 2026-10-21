<?php

namespace Tests\Feature;

use App\Domain\Ai\DocumentTextExtractor;
use App\Domain\Ai\Missions\MissionBrief;
use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionBriefStatus;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionSourceKind;
use App\Enums\AiMissionSourceStatus;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionRole;
use App\Models\AiMissionSource;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A mission's own files: extracted and digested into its library, then merged
 * into a strategy brief the owner approves before the team may run.
 */
class AiMissionSourcesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->create(['name' => 'مدیر آزمایشی']);
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
        AiProvider::factory()->active()->create();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeJson(array $payload): void
    {
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]]])]);
    }

    private function digest(): array
    {
        return [
            'summary' => 'استراتژی سال آینده',
            'goals' => ['رسیدن به اشغال ۷۵٪'],
            'principles' => ['قیمت‌گذاری پویا'],
            'red_lines' => ['هیچ‌وقت زیر قیمت تمام‌شده نفروش'],
            'kpis' => ['اشغال ماهانه ۷۵٪'],
            'facts' => [],
            'conflicts' => [],
        ];
    }

    private function mission(): AiMission
    {
        return AiMission::factory()->create(['created_by' => $this->admin->id, 'goal' => 'برنامهٔ هفتهٔ آینده']);
    }

    private function missionWithTeam(): AiMission
    {
        $mission = $this->mission();

        foreach ([AiMissionRoleType::Specialist, AiMissionRoleType::Coordinator] as $type) {
            AiMissionRole::factory()->create([
                'ai_mission_id' => $mission->id,
                'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $this->admin->id])->id,
                'role' => $type,
            ]);
        }

        return $mission;
    }

    public function test_the_extractor_reads_csv_markdown_and_xlsx_with_labels_next_to_numbers(): void
    {
        $extractor = app(DocumentTextExtractor::class);

        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "\xEF\xBB\xBFماه;اشغال\nمهر;۷۸\n");
        $this->assertSame("ماه | اشغال\nمهر | ۷۸", $extractor->extract($csv, 'csv')['content']);

        $md = tempnam(sys_get_temp_dir(), 'md');
        file_put_contents($md, "# هدف\nاشغال بیشتر");
        $this->assertStringContainsString('اشغال بیشتر', $extractor->extract($md, 'md')['content']);

        $xlsx = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive;
        $zip->open($xlsx, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="اشغال" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="x" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>ماه</t></si><si><t>نرخ اشغال</t></si><si><r><t>مهر</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="C2"><v>78.5</v></c></row></sheetData></worksheet>');
        $zip->close();

        $this->assertSame("کاربرگ «اشغال»\nماه |  | نرخ اشغال\nمهر |  | 78.5", $extractor->extract($xlsx, 'xlsx')['content']);
    }

    public function test_uploading_files_stores_them_digests_them_and_puts_them_in_the_open_missions_library(): void
    {
        $this->fakeJson($this->digest());
        $mission = $this->mission();

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->set('sourceKind', 'policy')
            ->set('sourceUploads', [UploadedFile::fake()->createWithContent('strategy.txt', 'استراتژی ما اشغال ۷۵ درصد است.')])
            ->call('uploadSources')
            ->assertHasNoErrors();

        $source = AiMissionSource::sole();
        $this->assertSame('strategy', $source->title);
        $this->assertSame(AiMissionSourceStatus::Ready, $source->status);
        $this->assertSame(['رسیدن به اشغال ۷۵٪'], $source->digest['goals']);
        $this->assertSame(AiMissionSourceKind::Policy, $source->kind);
        $this->assertSame($this->admin->id, $source->user_id);
        Storage::disk('local')->assertExists($source->path);
        $this->assertSame($mission->id, $source->ai_mission_id);
    }

    public function test_a_file_type_outside_the_list_is_rejected(): void
    {
        Livewire::test(AiMissionRoom::class)
            ->set('sourceUploads', [UploadedFile::fake()->createWithContent('virus.exe', 'MZ')])
            ->call('uploadSources')
            ->assertHasErrors('sourceUploads.0');

        $this->assertSame(0, AiMissionSource::count());
    }

    public function test_a_provider_failure_marks_the_file_failed_but_keeps_its_text_for_a_retry(): void
    {
        $failing = true;
        Http::fake(['*/chat/completions' => function () use (&$failing) {
            return $failing
                ? Http::response(['error' => ['message' => 'quota exceeded']], 429)
                : Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($this->digest(), JSON_UNESCAPED_UNICODE)]]]]);
        }]);

        $mission = $this->mission();
        $component = Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->set('sourceUploads', [UploadedFile::fake()->createWithContent('plan.csv', "ماه;اشغال\nمهر;۷۸\n")])
            ->call('uploadSources');

        $source = AiMissionSource::sole();
        $this->assertSame(AiMissionSourceStatus::Failed, $source->status);
        $this->assertStringContainsString('quota exceeded', $source->error);
        $this->assertSame("ماه | اشغال\nمهر | ۷۸", $source->content);

        $failing = false;
        $component->call('retrySource', $source->id);

        $this->assertSame(AiMissionSourceStatus::Ready, $source->fresh()->status);
    }

    public function test_one_source_becomes_the_brief_without_another_ai_call_and_waits_for_approval(): void
    {
        $mission = $this->mission();
        AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'digest' => $this->digest()]);
        Http::fake();

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief');

        Http::assertNothingSent();
        $mission->refresh();
        $this->assertSame(AiMissionBriefStatus::Review, $mission->brief_status);
        $this->assertSame(['هیچ‌وقت زیر قیمت تمام‌شده نفروش'], $mission->brief['red_lines']);
        $this->assertNull($mission->brief_approved_at);
    }

    public function test_several_sources_are_merged_by_the_ai_with_binding_files_marked(): void
    {
        $mission = $this->mission();
        AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'title' => 'سیاست قیمت', 'kind' => AiMissionSourceKind::Policy]);
        AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'title' => 'گزارش فروش', 'kind' => AiMissionSourceKind::Evidence]);
        $seen = null;
        Http::fake(['*/chat/completions' => function (Request $request) use (&$seen) {
            $seen = $request->data()['messages'][1]['content'];

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(array_merge($this->digest(), ['conflicts' => ['اشغال هدف با گزارش نمی‌خواند']]), JSON_UNESCAPED_UNICODE)]]]]);
        }]);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief');

        $this->assertStringContainsString('سیاست قیمت', $seen);
        $this->assertStringContainsString('"kind":"evidence"', $seen);
        $this->assertSame(['اشغال هدف با گزارش نمی‌خواند'], $mission->fresh()->brief['conflicts']);
    }

    public function test_a_source_that_is_not_ready_blocks_building_the_brief(): void
    {
        $mission = $this->mission();
        AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'status' => AiMissionSourceStatus::Failed, 'digest' => null]);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief');

        $this->assertSame(AiMissionBriefStatus::None, $mission->fresh()->brief_status);
    }

    public function test_a_mission_with_sources_does_not_run_until_the_brief_is_approved_and_current(): void
    {
        $mission = $this->missionWithTeam();
        $source = AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'digest' => $this->digest()]);

        $result = app(MissionRunner::class)->start($mission, $this->admin);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('خلاصهٔ استراتژی', $result['error']);
        $this->assertSame(AiMissionStatus::Draft, $mission->fresh()->status);

        $page = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief')->call('approveBrief');
        $this->assertSame(AiMissionBriefStatus::Approved, $mission->fresh()->brief_status);
        $this->assertTrue(app(MissionRunner::class)->start($mission->fresh(), $this->admin)['ok']);

        $mission->refresh()->update(['status' => AiMissionStatus::Draft]);
        $source->update(['kind' => AiMissionSourceKind::Evidence]);
        $blocked = app(MissionRunner::class)->start($mission->fresh(), $this->admin);
        $this->assertFalse($blocked['ok']);
        $this->assertStringContainsString('تغییر کرده', $blocked['error']);
        $this->assertNotNull($page);
    }

    public function test_a_mission_without_sources_runs_as_before(): void
    {
        $mission = $this->missionWithTeam();

        $this->assertTrue(app(MissionRunner::class)->start($mission, $this->admin)['ok']);
    }

    public function test_members_read_the_approved_brief_and_nothing_else_from_the_files(): void
    {
        $mission = $this->missionWithTeam();
        AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'digest' => $this->digest(), 'content' => 'متن خام خیلی محرمانه']);
        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief')->call('approveBrief');

        $prompts = [];
        Http::fake(['*/chat/completions' => function (Request $request) use (&$prompts) {
            $prompts[] = $request->data()['messages'][0]['content'];

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['findings' => [], 'summary' => 'x', 'decisions' => []])]]]]);
        }]);

        app(MissionRunner::class)->runRound($mission->fresh(), $this->admin);

        $this->assertNotEmpty($prompts);

        foreach ($prompts as $prompt) {
            $this->assertStringContainsString('هیچ‌وقت زیر قیمت تمام‌شده نفروش', $prompt);
            $this->assertStringContainsString('خط قرمزها', $prompt);
            $this->assertStringNotContainsString('متن خام خیلی محرمانه', $prompt);
        }
    }

    public function test_editing_the_brief_asks_for_a_new_approval(): void
    {
        $mission = $this->mission();
        AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'digest' => $this->digest()]);

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->call('buildBrief')
            ->call('approveBrief')
            ->set('briefLists.red_lines', "خط اول\nخط دوم")
            ->call('saveBrief');

        $mission->refresh();
        $this->assertSame(AiMissionBriefStatus::Review, $mission->brief_status);
        $this->assertSame(['خط اول', 'خط دوم'], $mission->brief['red_lines']);
        $this->assertNotNull(MissionBrief::blocksRun($mission));
    }

    public function test_only_the_owner_can_see_change_or_delete_a_file(): void
    {
        $other = User::factory()->create();
        $other->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $theirs = AiMissionSource::factory()->create([
            'user_id' => $other->id,
            'ai_mission_id' => AiMission::factory()->create(['created_by' => $other->id])->id,
            'title' => 'فایل محرمانهٔ دیگری',
        ]);
        $mission = $this->mission();

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->assertDontSee('فایل محرمانهٔ دیگری')
            ->call('deleteSource', $theirs->id)
            ->call('setSourceKind', $theirs->id, 'background');

        $this->assertSame(0, $mission->sources()->count());
        $this->assertNotNull($theirs->fresh());
        $this->assertSame(AiMissionSourceKind::Policy, $theirs->fresh()->kind);
    }

    public function test_deleting_a_file_removes_it_from_the_disk_and_the_mission(): void
    {
        $mission = $this->mission();
        Storage::disk('local')->put('mission-sources/a.txt', 'x');
        $source = AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'path' => 'mission-sources/a.txt']);

        Livewire::test(AiMissionRoom::class)->call('deleteSource', $source->id);

        Storage::disk('local')->assertMissing('mission-sources/a.txt');
        $this->assertSame(0, AiMissionSource::count());
        $this->assertSame(0, $mission->sources()->count());
    }

    public function test_each_mission_has_its_own_library_and_there_is_no_shared_one(): void
    {
        $first = $this->mission();
        $second = $this->mission();
        AiMissionSource::factory()->create(['ai_mission_id' => $first->id, 'title' => 'برنامهٔ راهبردی ۱۴۰۵']);

        Livewire::test(AiMissionRoom::class)
            ->assertDontSee('کتابخانهٔ منابع')
            ->call('openMission', $first->id)
            ->assertSee('کتابخانهٔ این مأموریت')
            ->assertSee('برنامهٔ راهبردی ۱۴۰۵')
            ->call('openMission', $second->id)
            ->assertSee('کتابخانهٔ این مأموریت')
            ->assertDontSee('برنامهٔ راهبردی ۱۴۰۵');
    }

    public function test_uploading_without_an_open_mission_or_form_stores_nothing(): void
    {
        Livewire::test(AiMissionRoom::class)
            ->set('sourceUploads', [UploadedFile::fake()->createWithContent('plan.txt', 'x')])
            ->call('uploadSources');

        $this->assertSame(0, AiMissionSource::count());
    }

    public function test_files_uploaded_in_the_new_mission_form_belong_to_the_mission_once_it_is_made(): void
    {
        $this->fakeJson($this->digest());
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $strangers = AiMissionSource::factory()->create(['user_id' => User::factory()->create()->id, 'ai_mission_id' => null, 'title' => 'سند ناشناس غریبه']);

        $page = Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->assertSeeHtml('id="source-files"')
            ->assertSee('فایل‌های استراتژی و گزارش این مأموریت')
            ->set('sourceUploads', [UploadedFile::fake()->createWithContent('new-plan.txt', 'برنامهٔ تازه')])
            ->call('uploadSources')
            ->assertSee('new-plan');

        $uploaded = AiMissionSource::where('title', 'new-plan')->sole();
        $this->assertNull($uploaded->ai_mission_id);

        $page
            ->set('newSourceIds', [(string) $uploaded->id, (string) $strangers->id])
            ->set('missionTitle', 'مأموریت با فایل')
            ->set('missionGoal', 'هدف')
            ->set('coordinatorId', $coordinator->id)
            ->set('specialists', [['profile_id' => $specialist->id, 'directive' => '']])
            ->call('createMission');

        $mission = AiMission::sole();
        $this->assertSame([$uploaded->id], $mission->sources()->pluck('id')->all());
        $this->assertNull($strangers->fresh()->ai_mission_id, 'another user\'s file is never taken');
    }

    public function test_cancelling_the_form_throws_away_the_files_uploaded_for_it(): void
    {
        Storage::disk('local')->put('mission-sources/x.txt', 'x');
        $pending = AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'ai_mission_id' => null, 'path' => 'mission-sources/x.txt']);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('newSourceIds', [(string) $pending->id])
            ->call('cancelCreate');

        $this->assertNull($pending->fresh());
        Storage::disk('local')->assertMissing('mission-sources/x.txt');
    }

    public function test_a_pending_file_can_be_taken_back_before_the_mission_is_made(): void
    {
        $pending = AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'ai_mission_id' => null]);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('newSourceIds', [(string) $pending->id])
            ->call('removePendingSource', $pending->id)
            ->assertSet('newSourceIds', []);

        $this->assertNull($pending->fresh());
    }

    public function test_files_without_a_mission_from_the_old_library_are_left_alone_by_a_new_mission(): void
    {
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $old = AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'ai_mission_id' => null]);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('missionTitle', 'بی‌فایل')
            ->set('missionGoal', 'هدف')
            ->set('coordinatorId', $coordinator->id)
            ->set('specialists', [['profile_id' => $specialist->id, 'directive' => '']])
            ->call('createMission');

        $this->assertSame(0, AiMission::sole()->sources()->count());
        $this->assertNotNull($old->fresh());
        $this->assertNull($old->fresh()->ai_mission_id);
    }

    public function test_deleting_a_mission_deletes_its_files_from_the_disk(): void
    {
        $mission = $this->mission();
        Storage::disk('local')->put('mission-sources/m.txt', 'x');
        AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'path' => 'mission-sources/m.txt']);

        Livewire::test(AiMissionRoom::class)->call('deleteMission', $mission->id);

        $this->assertSame(0, AiMissionSource::count());
        Storage::disk('local')->assertMissing('mission-sources/m.txt');
    }
}

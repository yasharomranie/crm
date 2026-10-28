<?php

namespace Tests\Feature;

use App\Enums\GuideEventType;
use App\Enums\GuideObjectCategory;
use App\Enums\GuideObjectStatus;
use App\Enums\GuideReferenceStatus;
use App\Filament\Pages\GuideSettings;
use App\Filament\Resources\GuideObjects\Pages\CreateGuideObject;
use App\Filament\Resources\GuideObjects\Pages\EditGuideObject;
use App\Filament\Resources\GuideObjects\Pages\ListGuideObjects;
use App\Filament\Resources\GuideObjects\RelationManagers\ReferenceImagesRelationManager;
use App\Filament\Resources\GuideObjects\RelationManagers\VideosRelationManager;
use App\Models\GuideEvent;
use App\Models\GuideObject;
use App\Models\GuideReferenceImage;
use App\Models\GuideSetting;
use App\Models\GuideSite;
use App\Models\GuideVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The smart tourism guide (experimental): objects, reference photos, videos,
 * the readiness status that gates what visitors see, the phone-facing JSON and
 * its anonymous event log.
 */
class GuideModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function readyObject(array $attributes = []): GuideObject
    {
        $object = GuideObject::factory()->create($attributes);
        GuideReferenceImage::factory()->count(3)->processed([0.6, 0.8, 0.0])->create(['guide_object_id' => $object->id]);

        return $object->refresh();
    }

    public function test_the_module_starts_with_the_ganjnameh_site_and_is_switched_off(): void
    {
        $this->assertSame(['گنجنامه'], GuideSite::pluck('name')->all());
        $this->assertFalse(GuideSetting::current()->is_enabled);
    }

    public function test_an_object_without_photos_is_a_draft_and_photos_waiting_make_it_processing(): void
    {
        $object = GuideObject::factory()->create();
        $this->assertSame(GuideObjectStatus::Draft, $object->status);

        $object->addReferenceImages(['guide/references/a.jpg', 'guide/references/b.jpg']);

        $this->assertSame(GuideObjectStatus::Processing, $object->refresh()->status);
        $this->assertSame(2, $object->referenceImages()->where('status', GuideReferenceStatus::Pending->value)->count());
    }

    public function test_enough_processed_photos_make_the_object_ready(): void
    {
        $object = GuideObject::factory()->create();
        $images = GuideReferenceImage::factory()->count(3)->create(['guide_object_id' => $object->id]);

        $images->each(fn (GuideReferenceImage $image) => $image->markProcessed([3, 4, 0]));

        $object->refresh();
        $this->assertSame(GuideObjectStatus::Ready, $object->status);
        $this->assertNotNull($object->ready_at);
    }

    public function test_a_finished_batch_that_stays_short_is_failed(): void
    {
        $object = GuideObject::factory()->create();
        $images = GuideReferenceImage::factory()->count(3)->create(['guide_object_id' => $object->id]);

        $images[0]->markProcessed([1, 0]);
        $images[1]->markFailed('تصویر تار است');
        $images[2]->markFailed('تصویر تار است');

        $this->assertSame(GuideObjectStatus::Failed, $object->refresh()->status);
    }

    public function test_the_required_number_of_photos_comes_from_the_settings(): void
    {
        GuideSetting::current()->update(['min_reference_images' => 1]);
        $object = GuideObject::factory()->create();

        GuideReferenceImage::factory()->processed()->create(['guide_object_id' => $object->id]);

        $this->assertSame(GuideObjectStatus::Ready, $object->refresh()->status);
    }

    public function test_deleting_photos_lowers_the_status_again(): void
    {
        $object = $this->readyObject();
        $this->assertSame(GuideObjectStatus::Ready, $object->status);

        $object->referenceImages()->first()->delete();

        $this->assertSame(GuideObjectStatus::Failed, $object->refresh()->status);

        $object->referenceImages()->get()->each->delete();

        $this->assertSame(GuideObjectStatus::Draft, $object->refresh()->status);
    }

    public function test_switching_an_object_off_disables_it_and_switching_on_restores_its_status(): void
    {
        $object = $this->readyObject();

        $object->update(['is_active' => false]);
        $this->assertSame(GuideObjectStatus::Disabled, $object->refresh()->status);

        $object->update(['is_active' => true]);
        $this->assertSame(GuideObjectStatus::Ready, $object->refresh()->status);
    }

    public function test_a_reprocess_sends_a_photo_back_and_drops_its_fingerprint(): void
    {
        $object = $this->readyObject();
        $image = $object->referenceImages()->first();

        $image->reprocess();

        $this->assertSame(GuideReferenceStatus::Pending, $image->refresh()->status);
        $this->assertNull($image->embedding);
        $this->assertSame(GuideObjectStatus::Processing, $object->refresh()->status);
    }

    public function test_a_fingerprint_is_stored_scaled_to_length_one_and_bad_ones_are_refused(): void
    {
        $image = GuideReferenceImage::factory()->create();

        $image->markProcessed([3, 4, 0]);

        $this->assertEqualsWithDelta([0.6, 0.8, 0.0], $image->refresh()->embedding, 0.0001);

        foreach ([[], [0, 0, 0], array_fill(0, GuideReferenceImage::MAX_DIMENSIONS + 1, 1)] as $bad) {
            try {
                $image->markProcessed($bad);
                $this->fail('a bad vector was accepted');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_only_ready_active_objects_of_active_sites_are_recognisable(): void
    {
        $ready = $this->readyObject();
        $draft = GuideObject::factory()->create();
        $off = $this->readyObject(['is_active' => false]);
        $offSite = $this->readyObject(['guide_site_id' => GuideSite::factory()->create(['is_active' => false])->id]);

        $this->assertSame([$ready->id], GuideObject::query()->recognisable()->pluck('id')->all());
        $this->assertNotContains($draft->id, GuideObject::query()->recognisable()->pluck('id')->all());
        $this->assertNotContains($off->id, GuideObject::query()->recognisable()->pluck('id')->all());
        $this->assertNotContains($offSite->id, GuideObject::query()->recognisable()->pluck('id')->all());
    }

    public function test_the_phone_data_is_hidden_while_the_guide_is_off_except_for_staff(): void
    {
        $this->get(route('guide.data'))->assertNotFound();

        $this->actingAs($this->admin())->get(route('guide.data'))->assertOk()->assertJsonPath('enabled', false);
    }

    public function test_the_phone_data_lists_only_ready_objects_with_their_fingerprints_and_published_videos(): void
    {
        Storage::fake('public');
        GuideSetting::current()->update(['is_enabled' => true, 'similarity_threshold' => 0.75, 'min_margin' => 0.05, 'frame_interval_ms' => 2000]);
        $object = $this->readyObject(['title_fa' => 'مجسمهٔ انسان نخستین', 'category' => GuideObjectCategory::PrimitiveManStatue]);
        GuideVideo::factory()->create(['guide_object_id' => $object->id, 'title' => 'داستان منتشرشده', 'url' => 'https://cdn.example.test/a.mp4']);
        GuideVideo::factory()->create(['guide_object_id' => $object->id, 'title' => 'پیش‌نویس', 'is_published' => false]);
        GuideObject::factory()->create(['title_fa' => 'پیش‌نویس بی‌عکس']);

        $response = $this->getJson(route('guide.data'))->assertOk();

        $response->assertJsonPath('enabled', true)
            ->assertJsonPath('settings.similarity_threshold', 0.75)
            ->assertJsonPath('settings.frame_interval_ms', 2000)
            ->assertJsonCount(1, 'objects')
            ->assertJsonPath('objects.0.title', 'مجسمهٔ انسان نخستین')
            ->assertJsonPath('objects.0.category', 'primitive_man_statue')
            ->assertJsonCount(3, 'objects.0.embeddings')
            ->assertJsonCount(1, 'objects.0.videos')
            ->assertJsonPath('objects.0.videos.0.url', 'https://cdn.example.test/a.mp4');
        $this->assertEqualsWithDelta(0.6, $response->json('objects.0.embeddings.0.0'), 0.0001);
    }

    public function test_an_uploaded_video_is_played_from_the_public_disk_when_there_is_no_link(): void
    {
        $video = GuideVideo::factory()->create(['url' => null, 'path' => 'guide/videos/a.mp4', 'poster_path' => 'guide/posters/a.jpg']);

        $this->assertStringEndsWith('/storage/guide/videos/a.mp4', $video->playUrl());
        $this->assertStringEndsWith('/storage/guide/posters/a.jpg', $video->posterUrl());
    }

    public function test_a_phone_reports_an_anonymous_outcome_and_nothing_else_is_kept(): void
    {
        GuideSetting::current()->update(['is_enabled' => true]);
        $object = $this->readyObject();

        $this->postJson(route('guide.events'), ['type' => 'scan_matched', 'object_id' => $object->id, 'confidence' => 0.83, 'latency_ms' => 420])->assertNoContent();
        $this->postJson(route('guide.events'), ['type' => 'scan_unmatched'])->assertNoContent();

        $this->assertSame(2, GuideEvent::count());
        $first = GuideEvent::orderBy('id')->first();
        $this->assertSame(GuideEventType::ScanMatched, $first->type);
        $this->assertSame($object->id, $first->guide_object_id);
        $this->assertSame(420, $first->latency_ms);
        $this->assertSame([], array_diff(array_keys($first->getAttributes()), ['id', 'guide_object_id', 'type', 'confidence', 'latency_ms', 'created_at']));
    }

    public function test_a_bad_event_is_rejected_and_events_are_ignored_while_the_guide_is_off(): void
    {
        $this->postJson(route('guide.events'), ['type' => 'scan_matched'])->assertNotFound();

        GuideSetting::current()->update(['is_enabled' => true]);

        $this->postJson(route('guide.events'), ['type' => 'hack'])->assertUnprocessable();
        $this->postJson(route('guide.events'), ['type' => 'scan_matched', 'confidence' => 4])->assertUnprocessable();
        $this->postJson(route('guide.events'), ['type' => 'scan_matched', 'object_id' => 9999])->assertUnprocessable();
        $this->assertSame(0, GuideEvent::count());
    }

    public function test_the_list_shows_status_and_photo_counts(): void
    {
        $ready = $this->readyObject(['title_fa' => 'کتیبهٔ گنجنامه']);
        GuideObject::factory()->create(['title_fa' => 'حیوان تاکسیدرمی']);

        Livewire::actingAs($this->admin())
            ->test(ListGuideObjects::class)
            ->assertCanSeeTableRecords(GuideObject::all())
            ->assertSee('کتیبهٔ گنجنامه')
            ->assertSee('آماده')
            ->assertSee('پیش‌نویس');
    }

    public function test_an_object_is_created_with_its_creator_and_a_slug(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateGuideObject::class)
            ->fillForm([
                'guide_site_id' => GuideSite::query()->value('id'),
                'title_fa' => 'کتیبهٔ گنجنامه',
                'title_en' => 'Ganjnameh inscription',
                'category' => GuideObjectCategory::Inscription->value,
                'description' => 'کتیبه‌های هخامنشی',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $object = GuideObject::firstOrFail();

        $this->assertSame($admin->id, $object->created_by);
        $this->assertStringStartsWith('ganjnameh-inscription-', $object->slug);
        $this->assertSame(GuideObjectStatus::Draft, $object->status);
    }

    public function test_the_title_and_site_are_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateGuideObject::class)
            ->fillForm(['guide_site_id' => null, 'title_fa' => null])
            ->call('create')
            ->assertHasFormErrors(['guide_site_id' => 'required', 'title_fa' => 'required']);
    }

    public function test_the_object_page_lists_its_photos_and_videos(): void
    {
        $object = GuideObject::factory()->create();
        $image = GuideReferenceImage::factory()->create(['guide_object_id' => $object->id]);
        $video = GuideVideo::factory()->create(['guide_object_id' => $object->id, 'title' => 'داستان غار']);

        Livewire::actingAs($this->admin())
            ->test(ReferenceImagesRelationManager::class, ['ownerRecord' => $object, 'pageClass' => EditGuideObject::class])
            ->assertCanSeeTableRecords([$image])
            ->assertSee('در انتظار پردازش');

        Livewire::actingAs($this->admin())
            ->test(VideosRelationManager::class, ['ownerRecord' => $object, 'pageClass' => EditGuideObject::class])
            ->assertCanSeeTableRecords([$video])
            ->assertSee('داستان غار');
    }

    public function test_a_failed_photo_can_be_sent_for_processing_again(): void
    {
        $object = $this->readyObject();
        $image = $object->referenceImages()->first();
        $image->markFailed('تار');

        Livewire::actingAs($this->admin())
            ->test(ReferenceImagesRelationManager::class, ['ownerRecord' => $object, 'pageClass' => EditGuideObject::class])
            ->callTableAction('reprocess', $image);

        $this->assertSame(GuideReferenceStatus::Pending, $image->refresh()->status);
    }

    public function test_the_settings_page_saves_the_switch_and_the_numbers(): void
    {
        Livewire::actingAs($this->admin())
            ->test(GuideSettings::class)
            ->fillForm(['is_enabled' => true, 'similarity_threshold' => 0.8, 'min_margin' => 0.1, 'frame_interval_ms' => 1200, 'min_reference_images' => 5])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = GuideSetting::current();

        $this->assertTrue($setting->is_enabled);
        $this->assertSame(0.8, $setting->similarity_threshold);
        $this->assertSame(1200, $setting->frame_interval_ms);
        $this->assertSame(5, $setting->min_reference_images);
    }

    public function test_the_settings_reject_out_of_range_numbers(): void
    {
        Livewire::actingAs($this->admin())
            ->test(GuideSettings::class)
            ->fillForm(['similarity_threshold' => 3, 'frame_interval_ms' => 10])
            ->call('save')
            ->assertHasFormErrors(['similarity_threshold', 'frame_interval_ms']);
    }

    public function test_every_module_of_the_guide_has_permissions_to_hand_out(): void
    {
        $this->artisan('access-control:sync');

        foreach (['guide-sites', 'guide-zones', 'guide-objects', 'guide-reference-images', 'guide-videos'] as $module) {
            $this->assertTrue(Permission::where('name', "{$module}.view_any")->exists(), $module);
        }

        $this->assertTrue(Permission::where('name', 'guide-settings.access')->exists());
    }

    public function test_the_scanner_page_is_closed_to_visitors_while_the_guide_is_off(): void
    {
        $this->get(route('guide.scanner'))->assertNotFound();

        $this->actingAs($this->admin())
            ->get(route('guide.scanner'))
            ->assertOk()
            ->assertSee('حالت آزمایش')
            ->assertSee('شروع اسکن');
    }

    public function test_the_scanner_page_is_open_to_visitors_once_the_guide_is_on(): void
    {
        GuideSetting::current()->update(['is_enabled' => true]);

        $this->get(route('guide.scanner'))
            ->assertOk()
            ->assertSee('شروع اسکن')
            ->assertDontSee('حالت آزمایش');
    }

    public function test_the_browser_can_store_the_fingerprint_it_made_for_a_photo(): void
    {
        $object = GuideObject::factory()->create();
        $image = GuideReferenceImage::factory()->create(['guide_object_id' => $object->id]);

        $this->actingAs($this->admin())
            ->postJson(route('guide.enroll.store', $image), ['embedding' => [3, 4, 0]])
            ->assertOk()
            ->assertJson(['status' => 'processed']);

        $this->assertSame([0.6, 0.8, 0.0], $image->refresh()->embedding);
    }

    public function test_an_empty_or_malformed_fingerprint_is_refused(): void
    {
        $image = GuideReferenceImage::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('guide.enroll.store', $image), ['embedding' => []])->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('guide.enroll.store', $image), ['embedding' => ['x']])->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('guide.enroll.store', $image), ['embedding' => [0, 0]])->assertUnprocessable();

        $this->assertSame(GuideReferenceStatus::Failed, $image->refresh()->status);
    }

    public function test_the_browser_can_report_that_a_photo_could_not_be_processed(): void
    {
        $image = GuideReferenceImage::factory()->create();

        $this->actingAs($this->admin())
            ->postJson(route('guide.enroll.failed', $image), ['error' => 'تصویر خراب است'])
            ->assertOk();

        $this->assertSame(GuideReferenceStatus::Failed, $image->refresh()->status);
    }

    public function test_the_pending_list_holds_only_unprocessed_photos_unless_all_is_asked(): void
    {
        $object = GuideObject::factory()->create();
        $pending = GuideReferenceImage::factory()->create(['guide_object_id' => $object->id]);
        $done = GuideReferenceImage::factory()->processed([1.0, 0.0])->create(['guide_object_id' => $object->id]);
        $admin = $this->admin();

        $ids = fn (array $json) => collect($json['images'])->pluck('id')->all();

        $this->assertSame([$pending->id], $ids($this->actingAs($admin)->getJson(route('guide.enroll.pending'))->assertOk()->json()));
        $this->assertEqualsCanonicalizing([$pending->id, $done->id], $ids($this->actingAs($admin)->getJson(route('guide.enroll.pending', ['all' => 1]))->json()));
    }

    public function test_the_staff_tools_need_a_signed_in_user_with_the_right_permission(): void
    {
        $image = GuideReferenceImage::factory()->create();

        $this->postJson(route('guide.enroll.store', $image), ['embedding' => [1]])->assertUnauthorized();

        $role = Role::firstOrCreate(['name' => 'limited']);
        Permission::firstOrCreate(['name' => 'tickets.view_any', 'guard_name' => 'web']);
        $role->givePermissionTo('tickets.view_any');
        $limited = User::factory()->create();
        $limited->assignRole($role);

        $this->actingAs($limited)->get(route('guide.enroll'))->assertForbidden();
        $this->actingAs($limited)->get(route('guide.accuracy'))->assertForbidden();
        $this->actingAs($limited)->postJson(route('guide.enroll.store', $image), ['embedding' => [1]])->assertForbidden();

        $this->actingAs($this->admin())->get(route('guide.enroll'))->assertOk()->assertSee('پردازش تصاویر مرجع');
        $this->actingAs($this->admin())->get(route('guide.accuracy'))->assertOk()->assertSee('آزمون دقت');
    }

    public function test_the_self_hosted_runtime_and_model_are_shipped_with_the_app(): void
    {
        foreach (['ort.wasm.min.mjs', 'ort-wasm-simd-threaded.mjs', 'ort-wasm-simd-threaded.wasm', 'dinov2-small-q8.onnx'] as $file) {
            $this->assertFileExists(public_path("vendor/guide/{$file}"));
        }

        $this->assertStringNotContainsString('https://', file_get_contents(public_path('guide/guide-engine.js')));
    }
}

<?php

namespace Tests\Feature\Crm;

use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Filament\Resources\LeadCaptureForms\Pages\ManageLeadCaptureForms;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\LeadCaptureForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadCaptureFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_an_active_form_is_publicly_visible_without_login(): void
    {
        $form = LeadCaptureForm::factory()->create(['title' => 'مشاوره رایگان تور']);

        $this->get(route('lead-capture.show', $form->slug))
            ->assertOk()
            ->assertSee('مشاوره رایگان تور');
    }

    public function test_an_inactive_form_is_not_found(): void
    {
        $form = LeadCaptureForm::factory()->inactive()->create();

        $this->get(route('lead-capture.show', $form->slug))->assertNotFound();
        $this->post(route('lead-capture.store', $form->slug), ['name' => 'علی', 'mobile' => '09121234567'])->assertNotFound();
    }

    public function test_a_submission_creates_a_guest_a_new_lead_deal_and_a_timeline_entry(): void
    {
        $rep = User::factory()->create();
        $form = LeadCaptureForm::factory()->create([
            'name' => 'لندینگ نوروز',
            'deal_source' => DealSource::SocialMedia,
            'assigned_to' => $rep->id,
        ]);

        $this->post(route('lead-capture.store', $form->slug), [
            'name' => 'نیما کریمی',
            'mobile' => '09121234567',
            'message' => 'برای ۴ نفر قیمت می‌خواهم',
        ])->assertRedirect(route('lead-capture.show', $form->slug))->assertSessionHas('lead_submitted');

        $guest = Guest::where('mobile', '09121234567')->firstOrFail();
        $deal = Deal::firstOrFail();

        $this->assertSame('نیما کریمی', $guest->name);
        $this->assertSame($guest->id, $deal->guest_id);
        $this->assertSame(DealStage::NewLead, $deal->stage);
        $this->assertSame(DealSource::SocialMedia, $deal->source);
        $this->assertSame($rep->id, $deal->assigned_to);
        $this->assertSame($form->id, $deal->lead_capture_form_id);
        $this->assertSame('برای ۴ نفر قیمت می‌خواهم', $deal->notes);
        $this->assertSame(1, $guest->activitiesAsSubject()->where('event', 'lead_captured')->count());
    }

    public function test_an_existing_guest_is_reused_and_not_renamed(): void
    {
        $guest = Guest::factory()->create(['name' => 'نام اصلی', 'mobile' => '09121234567']);
        $form = LeadCaptureForm::factory()->create();

        $this->post(route('lead-capture.store', $form->slug), ['name' => 'نام دیگر', 'mobile' => '09121234567']);

        $this->assertSame(1, Guest::count());
        $this->assertSame('نام اصلی', $guest->fresh()->name);
        $this->assertSame($guest->id, Deal::firstOrFail()->guest_id);
    }

    public function test_persian_digits_in_the_mobile_number_are_normalized(): void
    {
        $form = LeadCaptureForm::factory()->create();

        $this->post(route('lead-capture.store', $form->slug), ['name' => 'علی', 'mobile' => '۰۹۱۲۱۲۳۴۵۶۷']);

        $this->assertSame(1, Guest::where('mobile', '09121234567')->count());
    }

    public function test_an_invalid_mobile_number_is_rejected_and_nothing_is_stored(): void
    {
        $form = LeadCaptureForm::factory()->create();

        $this->post(route('lead-capture.store', $form->slug), ['name' => 'علی', 'mobile' => '12345'])
            ->assertSessionHasErrors('mobile');

        $this->assertSame(0, Guest::count());
        $this->assertSame(0, Deal::count());
    }

    public function test_a_filled_honeypot_looks_like_success_but_stores_nothing(): void
    {
        $form = LeadCaptureForm::factory()->create();

        $this->post(route('lead-capture.store', $form->slug), [
            'name' => 'ربات',
            'mobile' => '09121234567',
            'website' => 'http://spam.example',
        ])->assertSessionHas('lead_submitted');

        $this->assertSame(0, Guest::count());
        $this->assertSame(0, Deal::count());
    }

    public function test_admins_can_list_and_create_forms(): void
    {
        $form = LeadCaptureForm::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageLeadCaptureForms::class)
            ->assertCanSeeTableRecords([$form])
            ->mountAction('create')
            ->setActionData([
                'name' => 'فرم تازه',
                'slug' => 'new-form',
                'title' => 'عنوان تازه',
                'success_message' => 'ممنون',
                'deal_source' => DealSource::Online->value,
                'is_active' => true,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('lead_capture_forms', ['slug' => 'new-form']);
    }
}

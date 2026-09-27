<?php

namespace Tests\Feature;

use App\Enums\EventReminderUnit;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\EventReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_events(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListEvents::class)
            ->assertCanSeeTableRecords([$event]);
    }

    public function test_an_event_can_be_created_with_participants_and_defaults_to_the_creating_user(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateEvent::class)
            ->fillForm([
                'title' => 'جلسه هفتگی',
                'starts_at' => now()->addWeek(),
                'participants' => [$admin->id, $other->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::where('title', 'جلسه هفتگی')->firstOrFail();

        $this->assertSame($admin->id, $event->created_by);
        $this->assertCount(2, $event->participants);
    }

    public function test_reminders_can_be_created_inline_on_the_same_form_as_the_event(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateEvent::class)
            ->fillForm([
                'title' => 'جلسه هفتگی',
                'starts_at' => now()->addWeek(),
                'reminders' => [
                    ['value' => 1, 'unit' => 'week', 'notify_push' => true, 'notify_sms' => false, 'notify_email' => false],
                    ['value' => 24, 'unit' => 'hour', 'notify_push' => true, 'notify_sms' => false, 'notify_email' => false],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::where('title', 'جلسه هفتگی')->firstOrFail();

        $this->assertCount(2, $event->reminders);
        $this->assertEqualsCanonicalizing(
            [60 * 24 * 7, 60 * 24],
            $event->reminders->pluck('minutes_before')->all(),
        );
    }

    public function test_editing_an_event_prefills_reminders_as_value_and_unit(): void
    {
        $event = Event::factory()->create();
        EventReminder::factory()->create(['event_id' => $event->id, 'minutes_before' => 60 * 24 * 7]);

        Livewire::actingAs($this->admin())
            ->test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(function (array $state): array {
                $this->assertCount(1, $state['reminders']);
                $reminder = array_values($state['reminders'])[0];
                $this->assertEquals(1, $reminder['value']);
                $this->assertSame('week', $reminder['unit'] instanceof EventReminderUnit ? $reminder['unit']->value : $reminder['unit']);

                return [];
            });
    }
}

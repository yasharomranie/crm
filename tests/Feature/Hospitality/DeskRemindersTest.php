<?php

namespace Tests\Feature\Hospitality;

use App\Filament\Pages\TodayArrivals;
use App\Models\HandoverNote;
use App\Models\LostItem;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeskRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_only_open_things_are_counted(): void
    {
        HandoverNote::factory()->count(2)->create();
        HandoverNote::factory()->create(['resolved_at' => now()]);
        RoomBlock::factory()->create(['starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addDay()->toDateString()]);
        RoomBlock::factory()->create(['starts_on' => today()->addDays(3)->toDateString(), 'ends_on' => today()->addDays(5)->toDateString()]);
        LostItem::factory()->create();
        LostItem::factory()->create(['status' => LostItem::STATUS_RETURNED]);

        $component = Livewire::actingAs($this->admin())->test(TodayArrivals::class);

        $counts = collect($component->instance()->getDeskReminders())->pluck('count', 'label');

        $this->assertSame(2, $counts['یادداشت باز تحویل شیفت']);
        $this->assertSame(1, $counts['اتاق خارج از سرویس امشب']);
        $this->assertSame(1, $counts['شیء جامانده‌ی بدون تحویل']);
    }

    public function test_nothing_is_listed_when_there_is_nothing_to_remind(): void
    {
        $component = Livewire::actingAs($this->admin())->test(TodayArrivals::class);

        $this->assertSame([], $component->instance()->getDeskReminders());
    }
}

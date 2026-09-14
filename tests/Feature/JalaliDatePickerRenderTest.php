<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * mokhosh/filament-jalali's ->jalali() macro swaps a DatePicker's native
 * input for a custom Alpine-driven Blade view - a broken translation key or
 * missing asset there would blow up every form that uses it. This is a
 * smoke test that one such form (Project's start/end dates) still renders
 * and saves correctly with the Jalali picker in place.
 */
class JalaliDatePickerRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_form_with_jalali_date_pickers_renders_and_saves(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProject::class)
            ->assertOk()
            ->set('data.code', 'PRJ-TEST-1')
            ->set('data.title', 'پروژه آزمایشی')
            ->set('data.budget', 1000)
            ->set('data.start_date', '2026-09-14')
            ->set('data.end_date', '2026-10-14')
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::sole();
        $this->assertSame('2026-09-14', $project->start_date->toDateString());
    }
}

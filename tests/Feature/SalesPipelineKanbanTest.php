<?php

namespace Tests\Feature;

use App\Enums\DealStage;
use App\Filament\Pages\SalesPipeline;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesPipelineKanbanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_dragging_a_deal_to_a_new_column_moves_it_to_that_stage(): void
    {
        $deal = Deal::create(['title' => 'معامله تستی', 'stage' => DealStage::NewLead]);

        Livewire::actingAs($this->admin())
            ->test(SalesPipeline::class)
            ->call('moveDealToStage', $deal->id, DealStage::Negotiation->value);

        $this->assertSame(DealStage::Negotiation, $deal->fresh()->stage);
    }

    public function test_dropping_a_deal_back_on_its_own_column_does_nothing(): void
    {
        $deal = Deal::create(['title' => 'معامله تستی', 'stage' => DealStage::NewLead]);

        Livewire::actingAs($this->admin())
            ->test(SalesPipeline::class)
            ->call('moveDealToStage', $deal->id, DealStage::NewLead->value);

        $this->assertSame(DealStage::NewLead, $deal->fresh()->stage);
    }

    public function test_an_unknown_stage_value_is_ignored(): void
    {
        $deal = Deal::create(['title' => 'معامله تستی', 'stage' => DealStage::NewLead]);

        Livewire::actingAs($this->admin())
            ->test(SalesPipeline::class)
            ->call('moveDealToStage', $deal->id, 'not-a-real-stage');

        $this->assertSame(DealStage::NewLead, $deal->fresh()->stage);
    }

    public function test_the_prev_next_buttons_still_move_the_deal_one_stage_at_a_time(): void
    {
        $deal = Deal::create(['title' => 'معامله تستی', 'stage' => DealStage::NewLead]);

        Livewire::actingAs($this->admin())
            ->test(SalesPipeline::class)
            ->call('moveStage', $deal->id, 'next');

        $this->assertSame(DealStage::NeedsAssessment, $deal->fresh()->stage);
    }

    public function test_moving_before_the_first_stage_does_nothing(): void
    {
        $deal = Deal::create(['title' => 'معامله تستی', 'stage' => DealStage::NewLead]);

        Livewire::actingAs($this->admin())
            ->test(SalesPipeline::class)
            ->call('moveStage', $deal->id, 'prev');

        $this->assertSame(DealStage::NewLead, $deal->fresh()->stage);
    }
}

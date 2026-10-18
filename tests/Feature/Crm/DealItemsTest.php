<?php

namespace Tests\Feature\Crm;

use App\Enums\DealStage;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\RelationManagers\ItemsRelationManager;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DealItemsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function deal(?int $value = null): Deal
    {
        return Deal::create(['title' => 'تور', 'stage' => DealStage::ProposalSent, 'value' => $value]);
    }

    public function test_the_line_total_applies_quantity_and_discount(): void
    {
        $item = new DealItem(['quantity' => 3, 'unit_price' => 1_000_000, 'discount_percent' => 10]);

        $this->assertSame(2_700_000, $item->lineTotal());
    }

    public function test_the_deal_value_follows_its_lines(): void
    {
        $deal = $this->deal(500);

        DealItem::factory()->create(['deal_id' => $deal->id, 'quantity' => 2, 'unit_price' => 1_000_000]);
        DealItem::factory()->create(['deal_id' => $deal->id, 'quantity' => 1, 'unit_price' => 500_000, 'discount_percent' => 20]);

        $this->assertSame(2_400_000, (int) $deal->fresh()->value);
    }

    public function test_deleting_a_line_updates_the_value_and_deleting_the_last_keeps_the_last_total(): void
    {
        $deal = $this->deal();
        $first = DealItem::factory()->create(['deal_id' => $deal->id, 'quantity' => 1, 'unit_price' => 1_000_000]);
        $second = DealItem::factory()->create(['deal_id' => $deal->id, 'quantity' => 1, 'unit_price' => 2_000_000]);

        $second->delete();
        $this->assertSame(1_000_000, (int) $deal->fresh()->value);

        $first->delete();
        $this->assertSame(1_000_000, (int) $deal->fresh()->value);
    }

    public function test_a_deal_without_lines_keeps_the_hand_typed_value(): void
    {
        $deal = $this->deal(7_000_000);

        $deal->syncValueFromItems();

        $this->assertSame(7_000_000, (int) $deal->fresh()->value);
    }

    public function test_lines_are_added_from_the_deal_page(): void
    {
        $deal = $this->deal();

        Livewire::actingAs($this->admin())
            ->test(ItemsRelationManager::class, ['ownerRecord' => $deal, 'pageClass' => EditDeal::class])
            ->mountTableAction('create')
            ->setTableActionData(['description' => '۳ شب ویلا', 'quantity' => 3, 'unit_price' => 2_000_000, 'discount_percent' => 0])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(6_000_000, (int) $deal->fresh()->value);
    }

    public function test_the_value_field_is_locked_once_the_deal_has_lines(): void
    {
        $deal = $this->deal();
        DealItem::factory()->create(['deal_id' => $deal->id]);

        Livewire::actingAs($this->admin())
            ->test(EditDeal::class, ['record' => $deal->getRouteKey()])
            ->assertFormFieldIsDisabled('value');
    }
}

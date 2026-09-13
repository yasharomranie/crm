<?php

namespace Tests\Feature;

use App\Filament\Pages\ContractAssistant;
use App\Models\AiProvider;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_a_draft_without_an_active_provider_does_not_call_out(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create());

        Livewire::test(ContractAssistant::class)
            ->set('counterpartyName', 'آقای برق')
            ->set('brief', 'تعمیر روشنایی محوطه')
            ->call('generateDraft');

        Http::assertNothingSent();
    }

    public function test_generating_an_incomplete_draft_flags_missing_clauses(): void
    {
        AiProvider::factory()->active()->create();
        $category = ContractCategory::factory()->create();
        $company = Company::factory()->create();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        // Deliberately missing every required clause keyword.
                        'content' => "## مقدمه\nاین یک متن ناقص است.",
                    ],
                ]],
            ]),
        ]);

        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ContractAssistant::class)
            ->set('categoryId', $category->id)
            ->set('companyId', $company->id)
            ->set('counterpartyName', 'آقای برق')
            ->set('brief', 'تعمیر روشنایی محوطه')
            ->call('generateDraft');

        $warnings = $component->get('auditWarnings');

        $this->assertNotEmpty($warnings);
        $this->assertSame(count(Contract::requiredClauseRules()), count($warnings));

        Http::assertSentCount(1);
    }

    public function test_a_complete_draft_produces_no_audit_warnings(): void
    {
        AiProvider::factory()->active()->create();

        $completeContract = collect(Contract::requiredClauseRules())
            ->map(fn (array $rule) => $rule['label'].': '.$rule['keywords'][0])
            ->implode("\n\n");

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => $completeContract],
                ]],
            ]),
        ]);

        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ContractAssistant::class)
            ->set('counterpartyName', 'آقای برق')
            ->set('brief', 'یک قرارداد کامل')
            ->call('generateDraft');

        $this->assertSame([], $component->get('auditWarnings'));
    }

    public function test_inserting_suggested_clauses_sends_a_follow_up_request_and_updates_the_draft(): void
    {
        AiProvider::factory()->active()->create();

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => ['role' => 'assistant', 'content' => 'متن ناقص اولیه'],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => ['role' => 'assistant', 'content' => 'متن کامل‌شده با بندهای اضافه'],
                    ]],
                ]),
        ]);

        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ContractAssistant::class)
            ->set('counterpartyName', 'آقای برق')
            ->set('brief', 'توضیح مختصر')
            ->call('generateDraft')
            ->call('insertSuggestedClauses');

        $this->assertSame('متن کامل‌شده با بندهای اضافه', $component->get('body'));
        Http::assertSentCount(2);
    }

    public function test_saving_archives_a_contract_record(): void
    {
        AiProvider::factory()->active()->create();
        $category = ContractCategory::factory()->create();
        $company = Company::factory()->create();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'متن قرارداد نمونه'],
                ]],
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ContractAssistant::class)
            ->set('categoryId', $category->id)
            ->set('companyId', $company->id)
            ->set('counterpartyName', 'آقای برق')
            ->set('brief', 'توضیح مختصر')
            ->call('generateDraft')
            ->call('save');

        $this->assertDatabaseHas('contracts', [
            'contract_category_id' => $category->id,
            'company_id' => $company->id,
            'counterparty_name' => 'آقای برق',
            'body' => 'متن قرارداد نمونه',
            'created_by' => $user->id,
        ]);
    }

    public function test_the_print_route_is_denied_to_a_role_without_contract_permission(): void
    {
        $contract = Contract::factory()->create();

        $role = Role::create(['name' => 'بدون دسترسی قرارداد']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('contracts.print', $contract))
            ->assertForbidden();
    }

    public function test_the_print_route_is_allowed_with_the_contracts_view_permission(): void
    {
        $this->artisan('access-control:sync');

        $contract = Contract::factory()->create();

        $role = Role::create(['name' => 'حقوقی']);
        $role->givePermissionTo(Permission::where('name', 'contracts.view')->sole());

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('contracts.print', $contract))
            ->assertOk()
            ->assertSee($contract->title);
    }
}

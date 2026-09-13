<?php

namespace Tests\Feature;

use App\Enums\DocumentAccessLevel;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test for a real bug found while building the DMS: Select
 * fields configured with ->options(SomeEnum::class) hold their live form
 * state as the enum CASE, not its ->value. Comparing $get('field') to
 * SomeEnum::Case->value in a ->visible()/->required() closure silently
 * always evaluates false - it must compare to the enum case itself. The
 * same bug was found and fixed in StepsRelationManager, FieldsRelationManager
 * and PriceRuleResource.
 */
class DocumentFormReactivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_field_only_appears_when_access_level_is_department_only(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateDocument::class)
            ->assertFormFieldHidden('department_id')
            ->set('data.access_level', DocumentAccessLevel::DepartmentOnly->value)
            ->assertFormFieldVisible('department_id')
            ->set('data.access_level', DocumentAccessLevel::PublicInternal->value)
            ->assertFormFieldHidden('department_id');
    }
}

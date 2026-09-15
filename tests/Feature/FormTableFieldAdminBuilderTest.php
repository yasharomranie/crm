<?php

namespace Tests\Feature;

use App\Enums\FormFieldType;
use App\Filament\Resources\FormTemplates\Pages\EditFormTemplate;
use App\Filament\Resources\FormTemplates\RelationManagers\FieldsRelationManager;
use App\Filament\Support\DynamicFormFieldBuilder;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test for a real production incident: an admin built a table
 * field (FormFieldType::Table) through FieldsRelationManager's own "create
 * field" UI - not via a factory, as FormTableFieldTest does - and every
 * submission of that form crashed with a generic "Page Expired" (Livewire
 * catches an uncaught \TypeError from a failed component update and
 * responds with 419 in production instead of a real error - see
 * HandleRequests::handleUpdate()).
 *
 * The actual bug: the table's column definitions were first stored nested
 * under the same `options` JSON column as the KeyValue field used for
 * select/radio options (`options.columns` / `options.default_rows`).
 * Filament's Repeater silently loses every item's data (key/label/type all
 * come back null) when nested under a dotted sub-path that a sibling
 * component's own state path also touches - this only reproduces through
 * the actual RelationManager form (a raw factory-built FormField never
 * exercises the save path where this corruption happens). The fix moved
 * the definition to its own top-level columns (table_columns,
 * table_default_rows) instead of nesting inside `options`.
 */
class FormTableFieldAdminBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_table_field_through_the_admin_ui_saves_working_column_definitions(): void
    {
        $template = FormTemplate::factory()->create(['is_active' => true]);
        $this->actingAs(User::factory()->create());

        Livewire::test(FieldsRelationManager::class, [
            'ownerRecord' => $template,
            'pageClass' => EditFormTemplate::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'field_key' => 'units',
                'label' => 'واحدهای معوق',
                'field_type' => FormFieldType::Table->value,
                'table_columns' => [
                    ['key' => 'unit_name', 'label' => 'نام واحد', 'type' => 'text'],
                    ['key' => 'amount', 'label' => 'مبلغ', 'type' => 'number'],
                ],
                'table_default_rows' => 2,
                'is_required' => false,
                'sort_order' => 0,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $field = $template->fields()->where('field_key', 'units')->sole();

        $this->assertSame([
            ['key' => 'unit_name', 'label' => 'نام واحد', 'type' => 'text'],
            ['key' => 'amount', 'label' => 'مبلغ', 'type' => 'number'],
        ], $field->table_columns);
        $this->assertSame(2, $field->table_default_rows);

        // The actual crash happened here: building the live data-entry
        // component from the saved field threw a TypeError (Repeater\
        // TableColumn::make() given a null label) once the corruption
        // above occurred.
        $component = DynamicFormFieldBuilder::make($field);

        $this->assertNotNull($component);
    }
}

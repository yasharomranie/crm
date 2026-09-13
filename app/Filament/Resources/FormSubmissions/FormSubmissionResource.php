<?php

namespace App\Filament\Resources\FormSubmissions;

use App\Filament\Resources\FormSubmissions\Pages\CreateFormSubmission;
use App\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Filament\Resources\FormSubmissions\Pages\ViewFormSubmission;
use App\Filament\Resources\FormSubmissions\Schemas\FormSubmissionForm;
use App\Filament\Resources\FormSubmissions\Tables\FormSubmissionsTable;
use App\Models\FormSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "فرم‌ها و کارتابل تأییدات": the runtime side of the form builder -
 * submitting a form, tracking its approval journey, and (for eligible
 * approvers) acting on it. FormTemplateResource is the design-time side
 * and stays untouched.
 */
class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'فرم‌ساز و گردش‌کار';

    protected static ?string $navigationLabel = 'فرم‌ها و کارتابل تأییدات';

    protected static ?string $modelLabel = 'ثبت فرم';

    protected static ?string $pluralModelLabel = 'فرم‌ها و کارتابل تأییدات';

    protected static ?string $recordTitleAttribute = 'reference_code';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    /**
     * Overrides the plain "form-submissions.view" permission check: an
     * eligible approver must be able to open a submission that's routed
     * to them even without that static bit, and FormSubmission::isVisibleTo()
     * already folds the elevated permission in as one of its cases.
     */
    public static function canView(Model $record): bool
    {
        return $record->isVisibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return FormSubmissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FormSubmissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormSubmissions::route('/'),
            'create' => CreateFormSubmission::route('/create'),
            'view' => ViewFormSubmission::route('/{record}'),
        ];
    }
}

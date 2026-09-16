<?php

namespace App\Filament\Resources\DocumentFolders;

use App\Filament\Resources\DocumentFolders\Pages\ManageDocumentFolders;
use App\Filament\Support\FieldHelp;
use App\Models\DocumentFolder;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentFolderResource extends Resource
{
    protected static ?string $model = DocumentFolder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت اسناد';

    protected static ?string $modelLabel = 'پوشه';

    protected static ?string $pluralModelLabel = 'پوشه‌های اسناد';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام پوشه')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('document-folders.name')),
                Select::make('parent_id')
                    ->label('پوشه والد')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('برای ساخت یک زیرپوشه، پوشهٔ والد را انتخاب کنید.')
                    ->hintAction(FieldHelp::make('document-folders.parent_id')),
                Select::make('company_id')
                    ->label('شرکت/هلدینگ')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('اختیاری - برای پوشه‌های سطح بالا که مشخص می‌کنند این پوشه مال کدام شرکت است.')
                    ->hintAction(FieldHelp::make('document-folders.company_id')),
                Select::make('department_id')
                    ->label('مخصوص دپارتمان')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('اختیاری - اگر خالی بماند، پوشه برای همهٔ دپارتمان‌ها قابل مشاهده است.')
                    ->hintAction(FieldHelp::make('document-folders.department_id')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام پوشه')->searchable(),
                TextColumn::make('parent.name')->label('پوشه والد')->placeholder('—'),
                TextColumn::make('company.name')->label('شرکت/هلدینگ')->placeholder('—'),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('همه'),
                TextColumn::make('documents_count')->label('تعداد اسناد')->counts('documents')->numeric(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocumentFolders::route('/'),
        ];
    }
}

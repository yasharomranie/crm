<?php

namespace App\Filament\Resources\SeoTrackedKeywords\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SeoTrackedKeywordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('seo_project_id')
                    ->label('پروژه')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('keyword')
                    ->label('کلمه کلیدی')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->helperText('فقط کلمات فعال هر روز چک می‌شوند - هر پروژه حداکثر ۵۰ کلمه‌ی فعال می‌تواند داشته باشد.')
                    ->default(true),
            ]);
    }
}

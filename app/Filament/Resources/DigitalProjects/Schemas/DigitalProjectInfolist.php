<?php

namespace App\Filament\Resources\DigitalProjects\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DigitalProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('مشخصات پروژه')->columns(3)->schema([
                TextEntry::make('name')->label('نام پروژه'),
                TextEntry::make('channel')->label('نوع')->badge(),
                TextEntry::make('status')->label('وضعیت')->badge(),
                TextEntry::make('handle')->label('آدرس یا آیدی')->placeholder('—')->copyable(),
                TextEntry::make('owner.name')->label('مسئول')->placeholder('—'),
                TextEntry::make('next_review_at')->label('بررسی بعدی')->jalaliDate('Y/m/d')->placeholder('—'),
            ]),
            Section::make('شرح پروژه')->schema([
                TextEntry::make('description')->hiddenLabel()->placeholder('هنوز شرحی نوشته نشده است.')->extraAttributes(['style' => 'white-space: pre-wrap;']),
            ]),
        ]);
    }
}

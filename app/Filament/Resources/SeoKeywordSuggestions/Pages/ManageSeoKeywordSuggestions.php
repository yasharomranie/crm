<?php

namespace App\Filament\Resources\SeoKeywordSuggestions\Pages;

use App\Filament\Resources\SeoKeywordSuggestions\SeoKeywordSuggestionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSeoKeywordSuggestions extends ManageRecords
{
    protected static string $resource = SeoKeywordSuggestionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

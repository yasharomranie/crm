<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();
        $data['mime_type'] = Storage::disk('local')->mimeType($data['file_path']);
        $data['size_kb'] = (int) ceil(Storage::disk('local')->size($data['file_path']) / 1024);

        return $data;
    }
}

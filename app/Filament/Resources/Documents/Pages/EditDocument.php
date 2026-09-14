<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * If the uploaded file actually changed, archive the current one as a
     * DocumentVersion via Document::replaceFile() instead of just
     * overwriting file_path - that's how version history is preserved.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Document $record */
        $newPath = $data['file_path'] ?? null;

        if ($newPath && $newPath !== $record->file_path) {
            $record->replaceFile(
                $newPath,
                Storage::disk()->mimeType($newPath),
                (int) ceil(Storage::disk()->size($newPath) / 1024),
                auth()->user(),
            );

            unset($data['file_path'], $data['mime_type'], $data['size_kb'], $data['uploaded_by']);
        }

        $record->update($data);

        return $record;
    }
}

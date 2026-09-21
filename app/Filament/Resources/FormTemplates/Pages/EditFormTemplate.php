<?php

namespace App\Filament\Resources\FormTemplates\Pages;

use App\Filament\Resources\FormTemplates\FormTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFormTemplate extends EditRecord
{
    protected static string $resource = FormTemplateResource::class;

    /**
     * Just a soft delete (see FormTemplate's SoftDeletes) - that never
     * touches form_submissions.form_template_id, which stays
     * restrictOnDelete() at the database level, so it always succeeds even
     * for a template with real submissions.
     *
     * No Restore/ForceDeleteAction here: this page's own route binding
     * (InteractsWithRecord::resolveRecord()) goes through the resource's
     * default query, which - like every Eloquent query on a SoftDeletes
     * model - excludes trashed records entirely, so a trashed template's
     * edit page can never actually be reached to begin with. Restoring or
     * permanently deleting one belongs on the index table instead (see
     * FormTemplatesTable), whose TrashedFilter can actually list a
     * trashed record for its row actions to act on.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

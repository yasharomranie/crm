<?php

namespace App\Filament\Resources\FormSubmissions\Schemas;

use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class FormSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.infolists.form-submission-detail'),
            ]);
    }
}

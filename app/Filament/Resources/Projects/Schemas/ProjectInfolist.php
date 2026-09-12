<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.infolists.project-dashboard'),
            ]);
    }
}

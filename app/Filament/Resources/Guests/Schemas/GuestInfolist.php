<?php

namespace App\Filament\Resources\Guests\Schemas;

use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class GuestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.infolists.guest-dashboard'),
            ]);
    }
}

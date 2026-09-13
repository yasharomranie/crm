<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * A small "!" icon attached next to a form field (via ->hintAction()) that,
 * when clicked, opens a modal with plain-text guidance about that field.
 * All copy lives in lang/fa/field_help.php, keyed "{module}.{field}" - pass
 * that same key here, e.g. FieldHelp::make('documents.access_level').
 */
class FieldHelp
{
    public static function make(string $key): Action
    {
        return Action::make('help')
            ->label('راهنما')
            ->icon(Heroicon::OutlinedExclamationCircle)
            ->color('gray')
            ->iconButton()
            ->tooltip('راهنما')
            ->modalHeading('راهنما')
            ->modalIcon(Heroicon::OutlinedInformationCircle)
            ->modalDescription(new HtmlString(nl2br(e(__("field_help.{$key}")))))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('متوجه شدم');
    }
}

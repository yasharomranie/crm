<?php

namespace App\Filament\Resources\FormSubmissions\Pages;

use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListFormSubmissions extends ListRecords
{
    protected static string $resource = FormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('ثبت فرم جدید'),
        ];
    }

    /**
     * سه‌گانهٔ کارتابل: چیزی که منتظر تصمیم من است، چیزی که خودم فرستاده‌ام،
     * و همه‌چیزی که (بر اساس getEloquentQuery()) اصلاً حق دیدنش را دارم.
     */
    public function getTabs(): array
    {
        $user = Auth::user();

        return [
            'inbox' => Tab::make('کارتابل ورودی')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingActionFrom($user))
                ->badge(fn () => FormSubmissionResource::getEloquentQuery()->awaitingActionFrom($user)->count()),
            'mine' => Tab::make('ارسالی‌های من')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('submitted_by', $user->id)),
            'all' => Tab::make('همه'),
        ];
    }
}

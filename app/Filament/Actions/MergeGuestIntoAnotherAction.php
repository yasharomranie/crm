<?php

namespace App\Filament\Actions;

use App\Domain\Crm\GuestMergeService;
use App\Filament\Resources\Guests\GuestResource;
use App\Models\Guest;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Livewire\Component;

/**
 * Manual counterpart to the automatic «مشتریان تکراری» list, for a
 * duplicate nobody's detector would flag (different name, different phone):
 * on the guest being looked at, pick the record that should survive and
 * this one is folded into it.
 */
class MergeGuestIntoAnotherAction
{
    public static function make(): Action
    {
        return Action::make('mergeIntoAnother')
            ->label('ادغام در پرونده‌ی دیگر')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('gray')
            ->authorize('delete')
            ->modalHeading('ادغام این پرونده در پرونده‌ی دیگر')
            ->modalDescription('همه‌ی سابقه‌ی این پرونده (رزرو، معامله، تیکت، کیف پول، تاریخچه) به پرونده‌ی انتخاب‌شده منتقل و خودِ این پرونده حذف می‌شود. این کار قابل بازگشت نیست.')
            ->modalSubmitActionLabel('ادغام کن')
            ->schema([
                Select::make('keep_id')
                    ->label('پرونده‌ای که باید نگه داشته شود')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search, Component $livewire) => Guest::query()
                        ->whereKeyNot($livewire->getRecord()->getKey())
                        ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%"))
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Guest $guest) => [$guest->id => trim("{$guest->name} {$guest->mobile}")])
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => Guest::find($value)?->name),
            ])
            ->action(function (array $data, Guest $record, Component $livewire): void {
                $keep = Guest::findOrFail($data['keep_id']);

                app(GuestMergeService::class)->merge($keep, $record);

                Notification::make()->title('پرونده‌ها با موفقیت ادغام شدند.')->success()->send();

                $livewire->redirect(GuestResource::getUrl('view', ['record' => $keep]));
            });
    }
}

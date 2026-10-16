<?php

namespace App\Filament\Pages;

use App\Domain\Crm\GuestDuplicateDetector;
use App\Domain\Crm\GuestMergeService;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Guest;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Lists guest records that are probably the same person (see
 * GuestDuplicateDetector) and lets a person pick which one to keep, then
 * folds the others into it (GuestMergeService). Nothing is merged
 * automatically.
 */
class DuplicateGuests extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.duplicate-guests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $navigationLabel = 'مشتریان تکراری';

    protected static ?string $title = 'مشتریان تکراری';

    public static function accessControlPermission(): string
    {
        return 'duplicate-guests.access';
    }

    /**
     * @return Collection<int, array{key: string, guests: Collection<int, Guest>, reasons: list<string>}>
     */
    public function groups(): Collection
    {
        $groups = app(GuestDuplicateDetector::class)->findGroups();

        $loaded = Guest::query()
            ->withCount(['bookings', 'deals', 'tickets'])
            ->whereIn('id', $groups->flatMap(fn (array $group) => $group['guests']->pluck('id')))
            ->get()
            ->keyBy('id');

        return $groups->map(fn (array $group) => [
            'key' => $group['guests']->pluck('id')->implode('-'),
            'guests' => $group['guests']->map(fn (Guest $guest) => $loaded[$guest->id]),
            'reasons' => $group['reasons'],
        ]);
    }

    /**
     * @param  list<int>  $duplicateIds
     */
    public function mergeGroup(int $keepId, array $duplicateIds): void
    {
        $group = app(GuestDuplicateDetector::class)->findGroups()
            ->first(fn (array $group) => $group['guests']->contains('id', $keepId));

        $groupIds = $group ? $group['guests']->pluck('id')->all() : [];

        // The page may be stale: only merge records that the detector still
        // says belong together right now.
        $duplicateIds = array_values(array_unique(array_map('intval', $duplicateIds)));

        if ($duplicateIds === [] || array_diff($duplicateIds, $groupIds) !== [] || in_array($keepId, $duplicateIds, true)) {
            Notification::make()
                ->title('این گروه دیگر معتبر نیست؛ صفحه را تازه کنید.')
                ->danger()
                ->send();

            return;
        }

        $keep = Guest::findOrFail($keepId);
        $merger = app(GuestMergeService::class);

        foreach (Guest::query()->whereIn('id', $duplicateIds)->get() as $duplicate) {
            $merger->merge($keep, $duplicate);
        }

        Notification::make()
            ->title('پرونده‌ها با موفقیت ادغام شدند.')
            ->success()
            ->send();
    }
}

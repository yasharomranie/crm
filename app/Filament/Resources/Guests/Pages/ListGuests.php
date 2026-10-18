<?php

namespace App\Filament\Resources\Guests\Pages;

use App\Domain\Crm\GuestImporter;
use App\Filament\Resources\Guests\GuestResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ListGuests extends ListRecords
{
    protected static string $resource = GuestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importGuests')
                ->label('درون‌ریزی از اکسل/CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn () => (bool) auth()->user()?->can('guests.create'))
                ->modalHeading('درون‌ریزی مشتریان')
                ->modalDescription('ردیف اول فایل باید عنوان ستون‌ها باشد (نام، موبایل، ایمیل، کد ملی، یادداشت). فقط «نام» اجباری است. مشتریانی که موبایل، ایمیل یا کد ملی‌شان از قبل ثبت شده باشد دوباره ساخته نمی‌شوند و اطلاعات موجود هیچ‌وقت تغییر نمی‌کند.')
                ->modalSubmitActionLabel('درون‌ریزی')
                ->schema([
                    FileUpload::make('file')
                        ->label('فایل xlsx یا csv')
                        ->disk('local')
                        ->directory('guest-imports')
                        ->acceptedFileTypes([
                            'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(5120)
                        ->required(),
                ])
                ->extraModalFooterActions([
                    Action::make('downloadTemplate')
                        ->label('دانلود فایل نمونه')
                        ->color('gray')
                        ->action(fn () => response()->streamDownload(function () {
                            echo "\xEF\xBB\xBF".implode("\n", [
                                'نام,موبایل,ایمیل,کد ملی,یادداشت',
                                'علی رضایی,09121234567,ali@example.com,0012345678,مشتری نمونه',
                            ])."\n";
                        }, 'guests-import-template.csv', ['Content-Type' => 'text/csv; charset=utf-8'])),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $result = app(GuestImporter::class)->import($path, pathinfo($path, PATHINFO_EXTENSION));
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    Notification::make()
                        ->title("{$result['created']} مشتری ساخته شد")
                        ->body(trim(
                            ($result['duplicates'] ? "{$result['duplicates']} ردیف تکراری رد شد. " : '')
                            .($result['invalid'] ? "{$result['invalid']} ردیف نامعتبر بود:\n".implode("\n", $result['errors']) : ''),
                        ) ?: null)
                        ->{$result['invalid'] > 0 ? 'warning' : 'success'}()
                        ->persistent($result['invalid'] > 0)
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}

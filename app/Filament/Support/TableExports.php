<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The one place every reporting table's "خروجی اکسل" / "خروجی PDF" pair is
 * built from, so every report in the system behaves and looks the same
 * instead of each resource growing its own bespoke export code.
 *
 * Both exports read the SAME $columns map (label => how to pull that
 * column's value out of a record) and the table's *currently filtered,
 * searched and sorted* query - so what you export always matches what you
 * see on screen right now, and Excel/PDF are guaranteed to show the same
 * columns in the same order.
 *
 * No queue, no new database table: openspout/openspout and league/csv
 * already ship as transitive dependencies of filament/actions, so a real
 * .xlsx file is generated synchronously in this one request - same
 * dependency-free spirit as the contract/form Word exports.
 */
class TableExports
{
    /**
     * @param  array<string, Closure(Model): mixed>  $columns  column label => value resolver
     */
    public static function excel(string $filename, array $columns): Action
    {
        return Action::make('exportExcel')
            ->label('خروجی اکسل')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->action(function (HasTable $livewire) use ($filename, $columns) {
                $records = $livewire->getFilteredSortedTableQuery()?->get() ?? collect();

                return response()->streamDownload(
                    fn () => print (static::renderXlsx($columns, $records)),
                    "{$filename}.xlsx",
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                );
            });
    }

    /**
     * @param  array<string, Closure(Model): mixed>  $columns
     */
    public static function pdf(string $title, array $columns): Action
    {
        return Action::make('exportPdf')
            ->label('خروجی PDF')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->action(function (HasTable $livewire) use ($title, $columns) {
                $records = $livewire->getFilteredSortedTableQuery()?->get() ?? collect();

                $html = view('reports.print', [
                    'title' => $title,
                    'labels' => array_keys($columns),
                    'rows' => $records->map(
                        fn (Model $record) => collect($columns)->map(fn (Closure $resolve) => $resolve($record))->all()
                    ),
                    'generatedBy' => auth()->user()?->name,
                ])->render();

                $token = (string) Str::uuid();
                Cache::put("report-print:{$token}", [
                    'html' => $html,
                    'user_id' => auth()->id(),
                ], now()->addMinutes(30));

                $livewire->dispatch('open-in-new-tab', url: route('reports.print', $token));
            });
    }

    /**
     * @param  array<string, Closure(Model): mixed>  $columns
     * @param  iterable<Model>  $records
     */
    private static function renderXlsx(array $columns, iterable $records): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'report_export_');

        $writer = new Writer;
        $writer->openToFile($tempPath);

        $writer->addRow(Row::fromValues(
            array_keys($columns),
            (new Style)->setFontBold()->setBackgroundColor('F3F4F6'),
        ));

        foreach ($records as $record) {
            $writer->addRow(Row::fromValues(
                collect($columns)->map(fn (Closure $resolve) => (string) ($resolve($record) ?? '—'))->all(),
            ));
        }

        $writer->close();

        $bytes = file_get_contents($tempPath);
        unlink($tempPath);

        return $bytes;
    }
}

<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\TicketingReport;
use App\Domain\Ticketing\TicketingReportExport;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\TicketSourceType;
use App\Filament\Pages\TicketingReportsPage;
use App\Models\Product;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketingReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function sellSomething(): void
    {
        $product = Product::factory()->create(['name' => 'تله‌کابین']);
        $variant = TicketVariant::factory()->create(['product_id' => $product->id]);
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100_000]);
        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 2]],
        ]);
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function readWorkbook(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();

        return $sheets;
    }

    public function test_the_workbook_has_a_sheet_per_section_with_the_numbers_on_screen(): void
    {
        $this->sellSomething();
        $report = app(TicketingReport::class)->build(now()->startOfDay(), now()->addMinute());

        $path = app(TicketingReportExport::class)->render($report);
        $sheets = $this->readWorkbook($path);
        unlink($path);

        $this->assertSame(['خلاصه', 'محصول', 'صندوقدار', 'روش پرداخت', 'منبع فروش', 'کد تخفیف', 'استفاده‌نشده', 'رایگان', 'ورود ساعتی'], array_keys($sheets));
        $this->assertSame(['تله‌کابین', 2, 200000], $sheets['محصول'][1]);
        $this->assertCount(25, $sheets['ورود ساعتی']); // header + 24 hours
    }

    public function test_the_page_offers_the_export_and_downloads_a_file(): void
    {
        $this->sellSomething();
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        Livewire::actingAs($admin)->test(TicketingReportsPage::class)
            ->assertActionExists('exportExcel')
            ->callAction('exportExcel')
            ->assertFileDownloaded();
    }
}

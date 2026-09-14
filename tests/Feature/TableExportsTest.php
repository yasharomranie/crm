<?php

namespace Tests\Feature;

use App\Enums\DealStage;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TableExports (app/Filament/Support/TableExports.php) is the one place
 * every reporting table's "خروجی اکسل" / "خروجی PDF" pair comes from.
 * These tests exercise it once, through the Deals table, since the same
 * code backs every other resource that uses it.
 */
class TableExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_excel_export_downloads_a_valid_xlsx_file(): void
    {
        $guest = Guest::factory()->create(['name' => 'دکتر دانا تهرانی']);
        Deal::create(['title' => 'قرارداد آزمایشی', 'stage' => DealStage::Negotiation, 'value' => 5_000_000, 'guest_id' => $guest->id]);

        $this->actingAs(User::factory()->create());

        $response = Livewire::test(ListDeals::class)->callTableAction('exportExcel');

        $response->assertFileDownloaded(
            'معاملات-فروش.xlsx',
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $content = base64_decode(data_get($response->effects, 'download.content'));
        // A .xlsx file is a zip archive - "PK" is the zip local-file-header
        // signature, the cheapest way to confirm this is a real spreadsheet
        // and not, say, an error page.
        $this->assertStringStartsWith('PK', $content);
    }

    public function test_the_pdf_export_opens_a_print_page_showing_the_currently_filtered_deals(): void
    {
        $guest = Guest::factory()->create(['name' => 'دکتر دانا تهرانی']);
        $visible = Deal::create(['title' => 'معاملهٔ در جریان', 'stage' => DealStage::Negotiation, 'value' => 5_000_000, 'guest_id' => $guest->id]);
        Deal::create(['title' => 'معاملهٔ بردهٔ قدیمی', 'stage' => DealStage::Won, 'value' => 9_000_000]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = Livewire::test(ListDeals::class)
            ->filterTable('stage', DealStage::Negotiation->value)
            ->callTableAction('exportPdf');

        $printUrl = null;
        $response->assertDispatched('open-in-new-tab', function (string $name, array $params) use (&$printUrl) {
            $printUrl = $params['url'];

            return str_contains($printUrl, '/reports/print/');
        });

        $this->assertNotNull($printUrl);

        $this->actingAs($user)
            ->get($printUrl)
            ->assertOk()
            ->assertSee($visible->title)
            ->assertDontSee('معاملهٔ بردهٔ قدیمی');
    }

    public function test_the_report_print_route_is_forbidden_for_a_different_user(): void
    {
        Deal::create(['title' => 'معاملهٔ محرمانه', 'stage' => DealStage::NewLead]);

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $printResponse = Livewire::actingAs($owner)->test(ListDeals::class)->callTableAction('exportPdf');

        $printUrl = null;
        $printResponse->assertDispatched('open-in-new-tab', function (string $name, array $params) use (&$printUrl) {
            $printUrl = $params['url'];

            return true;
        });

        $this->actingAs($stranger)
            ->get($printUrl)
            ->assertForbidden();
    }

    public function test_the_report_print_route_returns_gone_for_an_unknown_token(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.print', 'not-a-real-token'))
            ->assertStatus(410);
    }
}

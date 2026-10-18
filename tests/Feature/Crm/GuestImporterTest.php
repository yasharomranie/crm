<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\GuestImporter;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestImporterTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->tempFiles, 'file_exists'));

        parent::tearDown();
    }

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'guests_').'.csv';
        file_put_contents($path, $content);

        return $this->tempFiles[] = $path;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'guests_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $this->tempFiles[] = $path;
    }

    private function import(string $path, string $extension = 'csv'): array
    {
        return app(GuestImporter::class)->import($path, $extension);
    }

    public function test_it_creates_guests_from_a_csv_with_persian_headers(): void
    {
        $result = $this->import($this->csv("\xEF\xBB\xBFنام,موبایل,ایمیل,کد ملی,یادداشت\nعلی رضایی,09121234567,Ali@Example.com,0012345678,مشتری ویژه\nمریم کریمی,09351112233,,,\n"));

        $this->assertSame(2, $result['created']);
        $ali = Guest::where('name', 'علی رضایی')->sole();
        $this->assertSame('09121234567', $ali->mobile);
        $this->assertSame('ali@example.com', $ali->email);
        $this->assertSame('0012345678', $ali->national_code);
        $this->assertSame('مشتری ویژه', $ali->notes);
    }

    public function test_it_reads_xlsx_files_too(): void
    {
        $result = $this->import($this->xlsx([['نام', 'موبایل'], ['سارا احمدی', '9121112222']]), 'xlsx');

        $this->assertSame(1, $result['created']);
        $this->assertSame('09121112222', Guest::sole()->mobile);
    }

    public function test_mobiles_in_other_formats_are_matched_to_existing_guests_and_skipped(): void
    {
        $existing = Guest::factory()->create(['name' => 'موجود', 'mobile' => '+989121234567']);

        $result = $this->import($this->csv("نام,موبایل\nتکراری,09121234567\nتازه,09127654321\n"));

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame('موجود', $existing->fresh()->name);
        $this->assertSame(2, Guest::count());
    }

    public function test_duplicates_inside_the_file_are_created_only_once(): void
    {
        $result = $this->import($this->csv("نام,ایمیل\nاول,same@example.com\nدوم,SAME@example.com\n"));

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame('اول', Guest::sole()->name);
    }

    public function test_bad_rows_are_reported_with_their_line_and_good_rows_still_import(): void
    {
        $result = $this->import($this->csv("نام,موبایل,ایمیل\n,09121111111,\nبی‌موبایل,12345,\nبی‌ایمیل,,not-an-email\nخوب,09122222222,good@example.com\n"));

        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['invalid']);
        $this->assertStringContainsString('ردیف 2', $result['errors'][0]);
        $this->assertStringContainsString('ردیف 3', $result['errors'][1]);
        $this->assertSame('خوب', Guest::sole()->name);
    }

    public function test_a_file_without_a_name_column_is_rejected_and_nothing_is_created(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->import($this->csv("موبایل,ایمیل\n09121234567,a@example.com\n"));
        } finally {
            $this->assertSame(0, Guest::count());
        }
    }

    public function test_unsupported_extensions_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->import($this->csv('x'), 'pdf');
    }

    public function test_blank_rows_are_ignored(): void
    {
        $result = $this->import($this->csv("نام,موبایل\n,\nفقط نام,\n"));

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['invalid']);
    }

    public function test_the_import_action_is_offered_on_the_guest_list(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        Livewire::actingAs($admin)->test(ListGuests::class)->assertActionVisible('importGuests');
    }
}

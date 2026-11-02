<?php

namespace Tests\Feature;

use App\Domain\System\ServerLogReader;
use App\Filament\Pages\ServerErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * «گزارش خطاهای سرور»: the newest errors of the application log, readable in
 * the panel (no terminal on the host), with secrets masked.
 */
class ServerErrorLogTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/server-log-'.uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);

        parent::tearDown();
    }

    private function writeLog(string $name, string $content): string
    {
        $path = $this->directory.'/'.$name;
        file_put_contents($path, $content);

        return $path;
    }

    private function sampleLog(): string
    {
        return <<<'LOG'
[2026-11-02 08:00:00] production.INFO: Deployment finished
[2026-11-02 08:20:11] production.ERROR: Call to a member function name() on null {"url":"https://example.test/admin/room-rack"}
#0 /app/Filament/Pages/RoomRack.php(95): foo()
#1 {main}
[2026-11-02 08:21:00] production.WARNING: Slow query
[2026-11-02 08:25:30] production.CRITICAL: Provider said Bearer abcdefghijklmnop and api_key=SECRETVALUE123 sk-abcdefghijklmnopqrstuv
LOG;
    }

    public function test_it_lists_the_newest_errors_first_with_their_details(): void
    {
        $path = $this->writeLog('laravel.log', $this->sampleLog());

        $entries = app(ServerLogReader::class)->recent($path);

        $this->assertCount(2, $entries);
        $this->assertSame('CRITICAL', $entries[0]['level']);
        $this->assertSame('ERROR', $entries[1]['level']);
        $this->assertSame('2026-11-02 08:20:11', $entries[1]['time']);
        $this->assertStringContainsString('Call to a member function name() on null', $entries[1]['headline']);
        $this->assertStringContainsString('RoomRack.php(95)', $entries[1]['details']);
    }

    public function test_it_can_include_the_lower_levels(): void
    {
        $path = $this->writeLog('laravel.log', $this->sampleLog());

        $levels = array_column(app(ServerLogReader::class)->recent($path, 20, false), 'level');

        $this->assertSame(['CRITICAL', 'WARNING', 'ERROR', 'INFO'], $levels);
    }

    public function test_keys_and_tokens_are_masked(): void
    {
        $path = $this->writeLog('laravel.log', $this->sampleLog());

        $details = app(ServerLogReader::class)->recent($path)[0]['details'];

        $this->assertStringNotContainsString('abcdefghijklmnop', $details);
        $this->assertStringNotContainsString('SECRETVALUE123', $details);
        $this->assertStringNotContainsString('sk-abcdefghijklmnopqrstuv', $details);
        $this->assertStringContainsString('••••', $details);
    }

    public function test_the_newest_file_is_chosen_and_a_missing_one_gives_nothing(): void
    {
        $old = $this->writeLog('laravel-2026-10-01.log', '');
        $new = $this->writeLog('laravel-2026-11-02.log', '');
        touch($old, time() - 3600);
        touch($new, time());

        $reader = app(ServerLogReader::class);

        $this->assertSame($new, $reader->newestFile($this->directory));
        $this->assertNull($reader->newestFile($this->directory.'/nothing'));
        $this->assertSame([], $reader->recent($this->directory.'/missing.log'));
    }

    public function test_an_oversized_log_is_read_from_its_end(): void
    {
        $filler = str_repeat("[2026-11-01 01:00:00] production.INFO: filler line\n", 20000);
        $path = $this->writeLog('laravel.log', $filler."[2026-11-02 09:00:00] production.ERROR: the latest failure\n");

        $this->assertSame('the latest failure', app(ServerLogReader::class)->recent($path)[0]['headline']);
    }

    public function test_only_users_with_the_permission_can_open_the_page(): void
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'بدون گزارش خطا']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->first());
        $limited = User::factory()->create();
        $limited->assignRole($role);

        $this->actingAs($limited);
        $this->assertFalse(ServerErrorLog::canAccess());

        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $this->assertTrue(ServerErrorLog::canAccess());
        Livewire::test(ServerErrorLog::class)->assertOk()->assertSee('گزارش خطاهای سرور');
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureIdempotentLivewireUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureIdempotentLivewireUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stands in for a Filament create action's side effect (creating a
        // record) without needing a real Livewire component/payload - the
        // middleware itself doesn't care what the wrapped route does.
        Route::post('/__test/idempotent-side-effect', function () {
            $count = Cache::increment('idempotent-side-effect-count');

            return response()->json(['count' => $count]);
        })->middleware(['web', EnsureIdempotentLivewireUpdate::class]);
    }

    public function test_repeating_the_same_retry_id_runs_the_side_effect_only_once(): void
    {
        $headers = ['X-Offline-Retry-Id' => 'retry-abc-123'];

        $first = $this->postJson('/__test/idempotent-side-effect', [], $headers);
        $second = $this->postJson('/__test/idempotent-side-effect', [], $headers);
        $third = $this->postJson('/__test/idempotent-side-effect', [], $headers);

        $first->assertOk()->assertJson(['count' => 1]);
        // The exact same (cached) response is returned on every repeat -
        // not a fresh execution that happens to also read count = 1.
        $second->assertOk()->assertJson(['count' => 1]);
        $third->assertOk()->assertJson(['count' => 1]);

        $this->assertSame(1, Cache::get('idempotent-side-effect-count'));
    }

    public function test_different_retry_ids_each_run_the_side_effect(): void
    {
        $this->postJson('/__test/idempotent-side-effect', [], ['X-Offline-Retry-Id' => 'retry-one'])
            ->assertOk()->assertJson(['count' => 1]);

        $this->postJson('/__test/idempotent-side-effect', [], ['X-Offline-Retry-Id' => 'retry-two'])
            ->assertOk()->assertJson(['count' => 2]);
    }

    public function test_a_request_without_a_retry_id_is_never_deduplicated(): void
    {
        $this->postJson('/__test/idempotent-side-effect')->assertOk()->assertJson(['count' => 1]);
        $this->postJson('/__test/idempotent-side-effect')->assertOk()->assertJson(['count' => 2]);
    }

    public function test_deduplication_still_works_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create());

        $headers = ['X-Offline-Retry-Id' => 'retry-authenticated'];

        $this->postJson('/__test/idempotent-side-effect', [], $headers)->assertOk()->assertJson(['count' => 1]);
        $this->postJson('/__test/idempotent-side-effect', [], $headers)->assertOk()->assertJson(['count' => 1]);

        $this->assertSame(1, Cache::get('idempotent-side-effect-count'));
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Deployment\DeploymentTriggerService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeploymentTriggerServiceTest extends TestCase
{
    public function test_it_is_not_pending_until_a_deploy_is_requested(): void
    {
        Storage::fake('local');

        $this->assertFalse(app(DeploymentTriggerService::class)->isPending());

        app(DeploymentTriggerService::class)->requestDeploy();

        $this->assertTrue(app(DeploymentTriggerService::class)->isPending());
    }

    public function test_it_returns_null_for_the_log_until_one_exists(): void
    {
        Storage::fake('local');

        $this->assertNull(app(DeploymentTriggerService::class)->lastLog());

        Storage::disk('local')->put('deploy_log.txt', 'done');

        $this->assertSame('done', app(DeploymentTriggerService::class)->lastLog());
    }
}

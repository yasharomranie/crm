<?php

namespace App\Console\Commands;

use App\Domain\Seo\SiteHealth\SiteHealthMonitor;
use Illuminate\Console\Command;

class CheckSeoSiteHealth extends Command
{
    protected $signature = 'seo:check-site-health';

    protected $description = 'Check that every SEO project website answers and its SSL certificate is not about to expire, and alert on changes';

    public function handle(SiteHealthMonitor $monitor): int
    {
        $this->info("{$monitor->run()} site(s) checked.");

        return self::SUCCESS;
    }
}

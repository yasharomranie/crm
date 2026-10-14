<?php

namespace App\Domain\Deployment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Lets an admin request a production deploy from the Filament panel without
 * shell/SSH access (disabled by the host) and without PHP's own proc_open
 * (also disabled there) - this service only ever writes a flag file on the
 * "local" disk. A permanent, every-minute Cron Job outside PHP watches for
 * that file and, when it appears, runs `git pull` + `migrate --force` +
 * `optimize:clear` + `optimize` directly via the shell, logging to
 * LOG_FILE and removing TRIGGER_FILE itself once done.
 */
class DeploymentTriggerService
{
    private const TRIGGER_FILE = 'deploy.trigger';

    private const LOG_FILE = 'deploy_log.txt';

    public function requestDeploy(): void
    {
        Storage::disk('local')->put(self::TRIGGER_FILE, now()->toDateTimeString());
    }

    public function isPending(): bool
    {
        return Storage::disk('local')->exists(self::TRIGGER_FILE);
    }

    public function lastLog(): ?string
    {
        return Storage::disk('local')->exists(self::LOG_FILE)
            ? Storage::disk('local')->get(self::LOG_FILE)
            : null;
    }

    /**
     * The log file's last-modified time, i.e. when the Cron Job last
     * finished writing to it - the closest available signal for when the
     * last deploy actually completed (nothing in the log content itself
     * carries a timestamp, since it's each artisan command's raw output).
     */
    public function lastDeployAt(): ?Carbon
    {
        return Storage::disk('local')->exists(self::LOG_FILE)
            ? Carbon::createFromTimestamp(Storage::disk('local')->lastModified(self::LOG_FILE))
            : null;
    }
}

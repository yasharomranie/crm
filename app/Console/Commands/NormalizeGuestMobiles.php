<?php

namespace App\Console\Commands;

use App\Domain\Crm\GuestMobileNormalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('guests:normalize-mobiles {--apply : Write the changes (without it, only report them)}')]
#[Description('Rewrite customer mobile numbers to one canonical spelling (09XXXXXXXXX) and list pairs that are the same person')]
class NormalizeGuestMobiles extends Command
{
    public function handle(GuestMobileNormalizer $normalizer): int
    {
        $apply = (bool) $this->option('apply');
        $result = $normalizer->run($apply);

        foreach ($result['changed'] as $row) {
            $this->line("#{$row['id']}: {$row['from']} -> {$row['to']}");
        }

        foreach ($result['conflicts'] as $row) {
            $this->warn("#{$row['id']} ({$row['mobile']}) is the same number as #{$row['other_id']} ({$row['canonical']}) - merge them by hand.");
        }

        $verb = $apply ? 'updated' : 'would be updated';
        $this->info(count($result['changed'])." customer(s) {$verb}, ".count($result['conflicts']).' conflict(s).');

        if (! $apply && $result['changed'] !== []) {
            $this->comment('Run again with --apply to write the changes.');
        }

        return self::SUCCESS;
    }
}

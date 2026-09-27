<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\WebsiteInspector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('websites:check')]
#[Description('Check every website through its authenticated WordPress status endpoint')]
class CheckWebsites extends Command
{
    public function handle(WebsiteInspector $inspector): int
    {
        $checked = 0;
        $failed = 0;

        foreach (Website::query()->lazyById(100) as $website) {
            $result = $inspector->inspect($website);
            $website->recordCheck($result);
            $checked++;

            if ($result['status_code'] !== 200 || $result['check_error'] !== null) {
                $failed++;
                $this->warn($website->domain.': '.($result['check_error'] ?? 'Status endpoint did not return HTTP 200.'));
            }
        }

        $this->info("Checked {$checked} websites: ".($checked - $failed)." succeeded, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

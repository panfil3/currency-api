<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Jobs\SyncExchangeRatesJob;
use Illuminate\Console\Command;

final class SyncExchangeRatesCommand extends Command
{
    protected $signature = 'currency:sync {--base=USD}';
    protected $description = 'Synchronize exchange rates from external providers';

    public function handle(): int
    {
        $base = $this->option('base');

        $this->info("Dispatching exchange rate sync job for base currency: {$base}");

        SyncExchangeRatesJob::dispatch($base);

        $this->info('Job dispatched successfully');

        return 0;
    }
}
<?php

namespace App\Console\Commands;

use App\services\StaleOrderService;
use Illuminate\Console\Command;

class CancelStaleOrders extends Command
{
    protected $signature = 'orders:cancel-stale';

    protected $description = 'Cancel unpaid orders that have been pending for longer than 48 hours';

    public function handle(StaleOrderService $orders): int
    {
        $ids = $orders->cancelStale();
        $this->info('Cancelled '.count($ids).' pending order(s).');

        return self::SUCCESS;
    }
}

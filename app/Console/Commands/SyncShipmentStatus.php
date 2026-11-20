<?php

namespace App\Console\Commands;

use App\services\ShipmentTrackingService;
use Illuminate\Console\Command;

class SyncShipmentStatus extends Command
{
    protected $signature = 'shipments:sync-status';

    protected $description = 'Pull open AWB statuses and email customers when a shipment milestone changes';

    public function handle(ShipmentTrackingService $tracking): int
    {
        $count = $tracking->syncOpenShipments();
        $this->info('Synced '.$count.' shipment update(s).');

        return self::SUCCESS;
    }
}

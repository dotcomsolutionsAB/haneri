<?php

namespace App\Console\Commands;

use App\services\AbandonedCartService;
use Illuminate\Console\Command;

class RemindAbandonedCarts extends Command
{
    protected $signature = 'carts:remind-abandoned';

    protected $description = 'Email logged-in customers whose carts have been idle for 1 hour and 24 hours';

    public function handle(AbandonedCartService $carts): int
    {
        $sent = $carts->sendDueReminders();
        $this->info("Sent {$sent} abandoned cart reminder(s).");

        return self::SUCCESS;
    }
}

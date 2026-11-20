<?php

namespace Tests\Unit;

use App\Services\DelhiveryService;
use PHPUnit\Framework\TestCase;

class DelhiveryPackageAmountsTest extends TestCase
{
    public function test_equal_split_when_every_box_carries_the_full_order_total(): void
    {
        $money = DelhiveryService::allocatePackageAmounts([
            ['total_amount' => 16990],
            ['total_amount' => 16990],
        ], 16990.0, 0.0);

        $this->assertSame(8495.0, $money[0]['total_amount']);
        $this->assertSame(8495.0, $money[1]['total_amount']);
    }

    public function test_explicit_unit_amounts_are_kept(): void
    {
        $money = DelhiveryService::allocatePackageAmounts([
            ['total_amount' => 8495, 'cod_amount' => 0],
            ['total_amount' => 8495, 'cod_amount' => 0],
        ], 16990.0, 0.0);

        $this->assertSame(8495.0, $money[0]['total_amount']);
        $this->assertSame(8495.0, $money[1]['total_amount']);
    }

    public function test_tiny_child_amounts_are_rejected_and_split_evenly(): void
    {
        $money = DelhiveryService::allocatePackageAmounts([
            ['total_amount' => 8495],
            ['total_amount' => 0.1],
        ], 8495.0, 0.0);

        $this->assertSame(4247.5, $money[0]['total_amount']);
        $this->assertSame(4247.5, $money[1]['total_amount']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\DelhiveryService;
use PHPUnit\Framework\TestCase;

class DelhiveryWarehouseMatchTest extends TestCase
{
    public function test_case_insensitive_name_wins(): void
    {
        $resolved = DelhiveryService::matchClientWarehouse([
            ['name' => 'Kolkata Hub', 'pin' => '700001'],
            ['name' => 'Burhanuddin', 'pin' => '713146'],
        ], 'burhanuddin', '700001');

        $this->assertSame('Burhanuddin', $resolved['match']['name']);
    }

    public function test_single_registered_warehouse_replaces_an_unknown_pickup(): void
    {
        $resolved = DelhiveryService::matchClientWarehouse([
            'data' => [
                ['name' => 'Burhanuddin', 'pin' => '713146', 'city' => 'Memari'],
            ],
        ], 'Kolkata', '700001');

        $this->assertSame('Burhanuddin', $resolved['match']['name']);
        $this->assertSame('713146', $resolved['match']['pin']);
    }

    public function test_nested_pickup_location_name_is_found(): void
    {
        $resolved = DelhiveryService::matchClientWarehouse([
            'result' => [
                'pickup_locations' => [
                    [
                        'pickup_location_name' => 'HANERI HO',
                        'city' => 'Noida',
                        'state' => 'Uttar Pradesh',
                        'pincode' => '201301',
                    ],
                ],
            ],
        ], 'Kolkata', '700001');

        $this->assertSame('HANERI HO', $resolved['match']['name']);
        $this->assertSame('201301', $resolved['match']['pin']);
    }

    public function test_unknown_pickup_among_several_warehouses_is_not_guessed(): void
    {
        $resolved = DelhiveryService::matchClientWarehouse([
            ['name' => 'Noida', 'pin' => '201301'],
            ['name' => 'Burhanuddin', 'pin' => '713146'],
        ], 'Kolkata', '700001');

        $this->assertNull($resolved['match']);
        $this->assertSame(['Noida', 'Burhanuddin'], $resolved['names']);
    }
}

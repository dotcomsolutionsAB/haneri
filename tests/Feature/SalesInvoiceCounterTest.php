<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingController;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CheckoutSchema;
use Tests\TestCase;

class SalesInvoiceCounterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CheckoutSchema::build();
    }

    public function test_admin_can_set_the_next_sales_invoice_number(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/settings/sales-invoice')
            ->assertOk()
            ->assertJsonPath('data.prefix', 'HAN-INV-')
            ->assertJsonPath('data.next_number', 1)
            ->assertJsonPath('data.preview', 'HAN-INV-0001');

        $this->putJson('/api/settings/sales-invoice', [
            'prefix' => 'HAN-INV-',
            'next_number' => 29,
            'pad' => 4,
        ])->assertOk()
            ->assertJsonPath('data.preview', 'HAN-INV-0029')
            ->assertJsonPath('data.next_number', 29);

        $this->assertSame('HAN-INV-0029', SettingController::allocateSalesInvoiceNumber());
        $this->assertSame('HAN-INV-0030', SettingController::allocateSalesInvoiceNumber());

        $this->getJson('/api/settings/sales-invoice')
            ->assertOk()
            ->assertJsonPath('data.next_number', 31);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@haneri.in',
            'password' => 'secret-pass-123',
            'role' => 'admin',
        ]);
    }
}

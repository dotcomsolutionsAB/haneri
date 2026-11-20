<?php

namespace Tests\Feature;

use App\services\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppUtilityTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1196510793549419',
            'services.whatsapp.business_account_id' => '1271818951690161',
            'services.whatsapp.api_version' => 'v22.0',
            'services.whatsapp.otp_language' => 'en',
            'services.whatsapp.order_confirmed_template' => 'haneri_order_confirmed',
            'services.whatsapp.order_dispatched_template' => 'haneri_order_dispatched',
            'services.whatsapp.checkout_incomplete_template' => 'haneri_checkout_incomplete',
            'services.whatsapp.cart_coupon_template' => 'haneri_cart_offer',
        ]);
        Cache::flush();
    }

    public function test_an_approved_order_template_sends_the_labeled_fields(): void
    {
        $this->fakeTemplate('haneri_order_confirmed', 'APPROVED');

        $this->assertTrue(WhatsAppService::sendOrderConfirmed(
            '9876543210',
            'Santosh',
            '39',
            [['name' => 'FengShui Ceiling Fan', 'variant' => 'Espresso Walnut', 'qty' => 2]],
            16990
        ));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/messages')) {
                return false;
            }
            $template = $request['template'];
            $body = $template['components'][0]['parameters'];

            return $request['to'] === '919876543210'
                && $template['name'] === 'haneri_order_confirmed'
                && $body[0]['text'] === 'Santosh'
                && $body[1]['text'] === '39'
                && $body[2]['text'] === 'FengShui Ceiling Fan (Espresso Walnut) x 2'
                && $body[3]['text'] === '₹16,990.00'
                && $template['components'][1]['parameters'][0]['text'] === '39';
        });
    }

    public function test_dispatched_template_sends_the_tracking_number(): void
    {
        $this->fakeTemplate('haneri_order_dispatched', 'APPROVED');

        $this->assertTrue(WhatsAppService::sendOrderDispatched(
            '9876543210',
            'Santosh',
            '39',
            'Delhivery',
            '36348510000475'
        ));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/messages')
                && $request['template']['name'] === 'haneri_order_dispatched'
                && $request['template']['components'][0]['parameters'][3]['text'] === '36348510000475';
        });
    }

    public function test_the_first_cart_reminder_sends_the_restore_token(): void
    {
        $this->fakeTemplate('haneri_checkout_incomplete', 'APPROVED');

        $this->assertTrue(WhatsAppService::sendCheckoutIncomplete(
            '9876543210',
            'Santosh',
            [['name' => 'FengShui Ceiling Fan', 'qty' => 2]],
            'restoretoken'
        ));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/messages')
                && $request['template']['name'] === 'haneri_checkout_incomplete'
                && $request['template']['components'][1]['parameters'][0]['text'] === 'restoretoken';
        });
    }

    public function test_the_coupon_reminder_sends_the_code_and_discount(): void
    {
        $this->fakeTemplate('haneri_cart_offer', 'APPROVED');

        $this->assertTrue(WhatsAppService::sendCartCoupon(
            '9876543210',
            'Santosh',
            [['name' => 'FengShui Ceiling Fan', 'qty' => 2]],
            'CART-AB12CD34',
            '10% off',
            '20 Nov 2026, 06:00 PM',
            'restoretoken'
        ));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/messages')) {
                return false;
            }
            $body = $request['template']['components'][0]['parameters'];

            return $request['template']['name'] === 'haneri_cart_offer'
                && $body[2]['text'] === 'CART-AB12CD34'
                && $body[3]['text'] === '10% off'
                && $body[4]['text'] === '20 Nov 2026, 06:00 PM'
                && $request['template']['components'][1]['parameters'][0]['text'] === 'restoretoken';
        });
    }

    public function test_a_pending_template_is_not_sent(): void
    {
        $this->fakeTemplate('haneri_order_confirmed', 'PENDING');

        $this->assertFalse(WhatsAppService::sendOrderConfirmed(
            '9876543210',
            'Santosh',
            '39',
            [['name' => 'FengShui Ceiling Fan', 'qty' => 1]],
            100
        ));

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/messages');
        });
    }

    private function fakeTemplate(string $name, string $status): void
    {
        Http::fake([
            'graph.facebook.com/*message_templates*' => Http::response([
                'data' => [['name' => $name, 'status' => $status]],
            ]),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
        ]);
    }
}

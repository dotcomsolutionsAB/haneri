<?php

namespace Tests\Feature;

use App\services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppOtpTest extends TestCase
{
    public function test_otp_is_sent_on_the_approved_authentication_template(): void
    {
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1196510793549419',
            'services.whatsapp.api_version' => 'v22.0',
            'services.whatsapp.otp_template' => 'haneri_otp',
            'services.whatsapp.otp_language' => 'en',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
        ]);

        $this->assertTrue(WhatsAppService::sendOtp('98765 43210', '123456'));

        Http::assertSent(function ($request) {
            $template = $request['template'];

            return $request->url() === 'https://graph.facebook.com/v22.0/1196510793549419/messages'
                && $request['to'] === '919876543210'
                && $template['name'] === 'haneri_otp'
                && $template['language']['code'] === 'en'
                && $template['components'][0]['parameters'][0]['text'] === '123456'
                && $template['components'][1]['parameters'][0]['text'] === '123456';
        });
    }

    public function test_a_missing_token_does_not_call_meta(): void
    {
        config([
            'services.whatsapp.token' => '',
            'services.whatsapp.phone_number_id' => '',
        ]);

        Http::fake();

        $this->assertFalse(WhatsAppService::sendOtp('9876543210', '123456'));
        Http::assertNothingSent();
    }
}

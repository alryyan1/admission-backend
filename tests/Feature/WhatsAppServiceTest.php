<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    public function test_it_is_not_configured_without_credentials(): void
    {
        config(['services.whatsapp.phone_number_id' => null, 'services.whatsapp.access_token' => null]);

        $this->assertFalse(app(WhatsAppService::class)->isConfigured());
    }

    public function test_it_sends_a_template_message_with_normalized_phone_and_body_parameters(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.default_country_code' => '20',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $service = app(WhatsAppService::class);

        $this->assertTrue($service->isConfigured());

        $service->sendTemplate(
            toPhone: '01012345678',
            templateName: 'admission_created',
            languageCode: 'ar',
            bodyParameters: [['type' => 'text', 'text' => 'Ahmed']],
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
                && $request['to'] === '201012345678'
                && $request['template']['name'] === 'admission_created'
                && $request['template']['language']['code'] === 'ar'
                && $request['template']['components'][0]['parameters'][0]['text'] === 'Ahmed';
        });
    }
}

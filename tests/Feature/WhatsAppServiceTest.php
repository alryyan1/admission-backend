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

    public function test_it_sends_a_template_message_with_a_document_header(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.default_country_code' => '20',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        app(WhatsAppService::class)->sendTemplate(
            toPhone: '01012345678',
            templateName: 'operation_team_pdf',
            languageCode: 'ar',
            bodyParameters: [['type' => 'text', 'text' => 'استئصال الزائدة']],
            headerParameters: [['type' => 'document', 'document' => ['id' => 'media-123', 'filename' => 'team.pdf']]],
        );

        Http::assertSent(function ($request) {
            return $request['template']['components'][0]['type'] === 'header'
                && $request['template']['components'][0]['parameters'][0]['document']['id'] === 'media-123'
                && $request['template']['components'][1]['type'] === 'body'
                && $request['template']['components'][1]['parameters'][0]['text'] === 'استئصال الزائدة';
        });
    }

    public function test_it_uploads_media_and_returns_the_media_id(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
        ]);

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'media-123'], 200),
        ]);

        $mediaId = app(WhatsAppService::class)->uploadMedia('%PDF-1.4 fake content', 'team.pdf');

        $this->assertSame('media-123', $mediaId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/media';
        });
    }
}

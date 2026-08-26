<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client over the Meta WhatsApp Cloud API for sending template messages.
 * Template messages are the only way to start a conversation with a customer
 * outside the 24-hour customer-service window, and each template name/language
 * must already exist and be approved in Meta Business Manager.
 */
class WhatsAppService
{
    private readonly string $apiVersion;

    private readonly ?string $phoneNumberId;

    private readonly ?string $accessToken;

    private readonly string $defaultCountryCode;

    public function __construct()
    {
        $this->apiVersion = (string) config('services.whatsapp.api_version', 'v21.0');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->defaultCountryCode = (string) config('services.whatsapp.default_country_code', '20');
    }

    public function isConfigured(): bool
    {
        return filled($this->phoneNumberId) && filled($this->accessToken);
    }

    /**
     * @param  array<int, array<string, mixed>>  $bodyParameters  e.g. [['type' => 'text', 'text' => 'Ahmed']]
     */
    public function sendTemplate(
        string $toPhone,
        string $templateName,
        string $languageCode,
        array $bodyParameters = [],
    ): Response {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('WhatsApp Cloud API is not configured (missing phone_number_id or access_token).');
        }

        $components = [];

        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($toPhone),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $languageCode],
                    'components' => $components,
                ],
            ])
            ->throw();
    }

    /**
     * Converts a locally-formatted number (e.g. "01012345678") into the
     * country-coded, symbol-free format the Cloud API expects ("201012345678").
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return $this->defaultCountryCode.substr($digits, 1);
        }

        if (! str_starts_with($digits, $this->defaultCountryCode) && strlen($digits) <= 10) {
            return $this->defaultCountryCode.$digits;
        }

        return $digits;
    }
}

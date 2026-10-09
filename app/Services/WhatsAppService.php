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
     * Fetches the display number and verification status of the configured
     * sender from the Graph API, so the settings page can show which number
     * messages are actually sent from.
     *
     * @return array{display_phone_number?: string, verified_name?: string, quality_rating?: string}
     */
    public function getPhoneNumberInfo(): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('WhatsApp Cloud API is not configured (missing phone_number_id or access_token).');
        }

        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}", [
                'fields' => 'display_phone_number,verified_name,quality_rating',
            ])
            ->throw()
            ->json();
    }

    /**
     * @param  array<int, array<string, mixed>>  $bodyParameters  e.g. [['type' => 'text', 'text' => 'Ahmed']]
     * @param  array<int, array<string, mixed>>  $headerParameters  e.g. [['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => 'file.pdf']]]
     */
    public function sendTemplate(
        string $toPhone,
        string $templateName,
        string $languageCode,
        array $bodyParameters = [],
        array $headerParameters = [],
    ): Response {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('WhatsApp Cloud API is not configured (missing phone_number_id or access_token).');
        }

        $components = [];

        if ($headerParameters !== []) {
            $components[] = [
                'type' => 'header',
                'parameters' => $headerParameters,
            ];
        }

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
     * Uploads a binary file to the Cloud API's Media endpoint so it can be referenced
     * by id in a document-header template message. Returns the resulting media id.
     */
    public function uploadMedia(string $binary, string $filename, string $mimeType = 'application/pdf'): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('WhatsApp Cloud API is not configured (missing phone_number_id or access_token).');
        }

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->attach('file', $binary, $filename, ['Content-Type' => $mimeType])
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ])
            ->throw()
            ->json();

        return (string) $response['id'];
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

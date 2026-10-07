<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendWhatsAppTestMessageRequest;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;

class WhatsAppSettingController extends Controller
{
    public function show(WhatsAppService $whatsApp): JsonResponse
    {
        $configured = $whatsApp->isConfigured();
        $phoneInfo = [];
        $error = null;

        if ($configured) {
            try {
                $phoneInfo = $whatsApp->getPhoneNumberInfo();
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        return response()->json([
            'configured' => $configured,
            'phone_number_id' => config('services.whatsapp.phone_number_id'),
            'display_phone_number' => $phoneInfo['display_phone_number'] ?? null,
            'verified_name' => $phoneInfo['verified_name'] ?? null,
            'quality_rating' => $phoneInfo['quality_rating'] ?? null,
            'error' => $error,
        ]);
    }

    public function sendTest(SendWhatsAppTestMessageRequest $request, WhatsAppService $whatsApp): JsonResponse
    {
        $whatsApp->sendTemplate(
            toPhone: $request->validated('phone'),
            templateName: 'hello_world',
            languageCode: 'en_US',
        );

        return response()->json(['message' => 'تم إرسال الرسالة الاختبارية بنجاح']);
    }
}

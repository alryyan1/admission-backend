<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWhatsAppRecipientRequest;
use App\Models\WhatsAppRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WhatsAppRecipientController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(WhatsAppRecipient::orderBy('label')->get());
    }

    public function store(StoreWhatsAppRecipientRequest $request): JsonResponse
    {
        $recipient = WhatsAppRecipient::create($request->validated());

        return response()->json($recipient, Response::HTTP_CREATED);
    }

    public function destroy(WhatsAppRecipient $recipient): JsonResponse
    {
        $recipient->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

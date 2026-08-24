<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateShortStayServiceSettingRequest;
use App\Models\ShortStayServiceSetting;
use Illuminate\Http\JsonResponse;

class ShortStayServiceSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(ShortStayServiceSetting::current()->load(['service12h', 'service24h']));
    }

    public function update(UpdateShortStayServiceSettingRequest $request): JsonResponse
    {
        $setting = ShortStayServiceSetting::current();
        $setting->update($request->validated());

        return response()->json($setting->load(['service12h', 'service24h']));
    }
}

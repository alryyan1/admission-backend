<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateChartOpeningServiceSettingRequest;
use App\Models\ChartOpeningServiceSetting;
use Illuminate\Http\JsonResponse;

class ChartOpeningServiceSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(ChartOpeningServiceSetting::current()->load('service'));
    }

    public function update(UpdateChartOpeningServiceSettingRequest $request): JsonResponse
    {
        $setting = ChartOpeningServiceSetting::current();
        $setting->update($request->validated());

        return response()->json($setting->load('service'));
    }
}

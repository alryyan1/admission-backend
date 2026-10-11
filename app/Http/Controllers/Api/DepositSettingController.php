<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDepositSettingRequest;
use App\Models\DepositSetting;
use Illuminate\Http\JsonResponse;

class DepositSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(DepositSetting::current());
    }

    public function update(UpdateDepositSettingRequest $request): JsonResponse
    {
        $setting = DepositSetting::current();
        $setting->update($request->validated());

        return response()->json($setting);
    }
}

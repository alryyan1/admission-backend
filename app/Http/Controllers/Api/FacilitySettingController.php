<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFacilitySettingRequest;
use App\Models\FacilitySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacilitySettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(FacilitySetting::current());
    }

    public function logo(): StreamedResponse|Response
    {
        return $this->streamFile(FacilitySetting::current()->logo_path);
    }

    public function stamp(): StreamedResponse|Response
    {
        return $this->streamFile(FacilitySetting::current()->stamp_path);
    }

    public function watermark(): StreamedResponse|Response
    {
        return $this->streamFile(FacilitySetting::current()->watermark_path);
    }

    private function streamFile(?string $path): StreamedResponse|Response
    {
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function update(UpdateFacilitySettingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $setting = FacilitySetting::current();

        foreach (['name', 'phone', 'email', 'address'] as $field) {
            if (array_key_exists($field, $validated)) {
                $setting->{$field} = $validated[$field];
            }
        }

        if ($request->boolean('remove_logo') && $setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
            $setting->logo_path = null;
        }

        if ($request->boolean('remove_stamp') && $setting->stamp_path) {
            Storage::disk('public')->delete($setting->stamp_path);
            $setting->stamp_path = null;
        }

        if ($request->boolean('remove_watermark') && $setting->watermark_path) {
            Storage::disk('public')->delete($setting->watermark_path);
            $setting->watermark_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $setting->logo_path = $request->file('logo')->store('facility', 'public');
        }

        if ($request->hasFile('stamp')) {
            if ($setting->stamp_path) {
                Storage::disk('public')->delete($setting->stamp_path);
            }
            $setting->stamp_path = $request->file('stamp')->store('facility', 'public');
        }

        if ($request->hasFile('watermark')) {
            if ($setting->watermark_path) {
                Storage::disk('public')->delete($setting->watermark_path);
            }
            $setting->watermark_path = $request->file('watermark')->store('facility', 'public');
        }

        if ($request->has('use_logo')) {
            $setting->use_logo = $request->boolean('use_logo');
        }

        if ($request->has('use_stamp')) {
            $setting->use_stamp = $request->boolean('use_stamp');
        }

        if ($request->has('use_watermark')) {
            $setting->use_watermark = $request->boolean('use_watermark');
        }

        $setting->save();

        return response()->json($setting->fresh());
    }
}

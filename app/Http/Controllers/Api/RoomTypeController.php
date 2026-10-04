<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomTypeRequest;
use App\Http\Requests\UpdateRoomTypeRequest;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class RoomTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(RoomType::orderBy('id')->get());
    }

    public function store(StoreRoomTypeRequest $request): JsonResponse
    {
        $roomType = RoomType::create([
            'code' => RoomType::generateUniqueCode(),
            'name' => $request->validated('name'),
        ]);

        return response()->json($roomType, Response::HTTP_CREATED);
    }

    public function update(UpdateRoomTypeRequest $request, RoomType $roomType): JsonResponse
    {
        $roomType->update(['name' => $request->validated('name')]);

        return response()->json($roomType);
    }

    public function destroy(RoomType $roomType): Response
    {
        if ($roomType->rooms()->exists()) {
            throw ValidationException::withMessages([
                'room_type' => ['لا يمكن حذف نوع غرفة مستخدم في غرف موجودة.'],
            ]);
        }

        $roomType->delete();

        return response()->noContent();
    }
}

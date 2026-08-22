<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Room::with(['beds', 'ward'])->withCount('beds');

        if ($request->filled('ward_id')) {
            $query->where('ward_id', $request->integer('ward_id'));
        }

        if ($request->filled('room_type')) {
            $query->where('room_type', $request->string('room_type'));
        }

        return response()->json($query->orderBy('room_number')->get());
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return response()->json($room, Response::HTTP_CREATED);
    }

    public function show(Room $room): JsonResponse
    {
        return response()->json($room->load('ward', 'beds'));
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $room->update($request->validated());

        return response()->json($room);
    }

    public function destroy(Room $room): Response
    {
        $room->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetRoomBedsStatusRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Room::with(['beds', 'ward.floor'])->withCount('beds');

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
        $roomData = $request->safe()->except('auto_create_beds');

        $room = DB::transaction(function () use ($request, $roomData): Room {
            $room = Room::query()->create($roomData);

            if ($request->boolean('auto_create_beds')) {
                $room->beds()->createMany($this->buildDefaultBeds($room->capacity));
            }

            return $room;
        });

        return response()->json($room, Response::HTTP_CREATED);
    }

    /**
     * @return list<array{bed_number: string, unit_type: string, status: string}>
     */
    private function buildDefaultBeds(int $bedCount): array
    {
        return Collection::times($bedCount, fn (int $bedNumber): array => [
            'bed_number' => (string) $bedNumber,
            'unit_type' => 'bed',
            'status' => 'available',
        ])->values()->all();
    }

    public function show(Room $room): JsonResponse
    {
        return response()->json($room->load('ward.floor', 'beds'));
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

    /**
     * Bulk-sets every bed in the room to "available" or "maintenance" in one request
     * (e.g. closing a whole room for cleaning). Beds with a currently admitted patient
     * are left untouched, same as the single-bed update guard.
     */
    public function setBedsStatus(SetRoomBedsStatusRequest $request, Room $room): JsonResponse
    {
        $updated = $room->beds()
            ->whereDoesntHave('currentAdmission')
            ->update(['status' => $request->string('status')]);

        $skipped = $room->beds()->whereHas('currentAdmission')->count();

        return response()->json([
            'room' => $room->load('beds'),
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\RequestedService;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdmissionRevenueReportController extends Controller
{
    /**
     * Room/ward/incubator accommodation revenue grouped by room type, so each department's
     * contribution can be reviewed separately over a chosen period.
     */
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $admissions = Admission::query()
            ->whereBetween('admission_date', [$from, $to])
            ->whereHas('requestedServices', fn ($query) => $query->where('name', RequestedService::AccommodationFeeName))
            ->with(['patient', 'bed.room.ward', 'requestedServices'])
            ->orderByDesc('admission_date')
            ->get();

        $roomTypesByCode = RoomType::query()->get()->keyBy('code');

        $rows = $admissions->map(fn (Admission $admission) => $this->buildRow($admission, $roomTypesByCode));

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'admissions_count' => $rows->count(),
                'room_revenue' => (float) $rows->sum('room_revenue'),
            ],
            'by_room_type' => $this->groupByRoomType($rows),
            'admissions' => $rows->values(),
        ]);
    }

    /**
     * @param  Collection<int|string, RoomType>  $roomTypesByCode
     * @return array{admission_id: int, admission_number: string|null, patient_name: string|null, admission_date: string|null, discharge_date: string|null, status: string, room_type_code: string|null, room_type_name: string, ward_name: string|null, room_number: string|null, room_revenue: float}
     */
    private function buildRow(Admission $admission, Collection $roomTypesByCode): array
    {
        $roomRevenue = (float) $admission->requestedServices
            ->where('name', RequestedService::AccommodationFeeName)
            ->sum('total_price');

        $roomTypeCode = $admission->bed?->room?->room_type;

        return [
            'admission_id' => $admission->id,
            'admission_number' => $admission->admission_number,
            'patient_name' => $admission->patient?->name,
            'admission_date' => $admission->admission_date?->toIso8601String(),
            'discharge_date' => $admission->discharge_date?->toIso8601String(),
            'status' => $admission->status,
            'room_type_code' => $roomTypeCode,
            'room_type_name' => $roomTypeCode !== null ? ($roomTypesByCode->get($roomTypeCode)?->name ?? $roomTypeCode) : 'غير محدد',
            'ward_name' => $admission->bed?->room?->ward?->name,
            'room_number' => $admission->bed?->room?->room_number,
            'room_revenue' => $roomRevenue,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array{room_type_code: string|null, room_type_name: string, admissions_count: int, total: float}>
     */
    private function groupByRoomType(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => $row['room_type_code'] ?? 'unknown')
            ->map(fn (Collection $group, string $code) => [
                'room_type_code' => $code === 'unknown' ? null : $code,
                'room_type_name' => $group->first()['room_type_name'],
                'admissions_count' => $group->count(),
                'total' => (float) $group->sum('room_revenue'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfMonth();

        return [$from, $to];
    }
}

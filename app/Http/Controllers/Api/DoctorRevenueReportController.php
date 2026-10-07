<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorRevenueReportRequest;
use App\Models\Admission;
use App\Models\Doctor;
use App\Models\RequestedService;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DoctorRevenueReportController extends Controller
{
    public function index(DoctorRevenueReportRequest $request): JsonResponse
    {
        $doctor = Doctor::query()->with('specialist')->findOrFail($request->integer('doctor_id'));
        $role = $request->string('doctor_role')->toString();
        $column = $role === 'referring' ? 'referred_by_doctor_id' : 'admitting_doctor_id';

        [$from, $to] = $this->resolveRange($request);

        $admissions = Admission::query()
            ->whereHas('patient', fn ($query) => $query->where($column, $doctor->id))
            ->whereBetween('admission_date', [$from, $to])
            ->with(['patient', 'bed.room', 'requestedServices', 'operations'])
            ->orderByDesc('admission_date')
            ->get();

        $roomTypesByCode = RoomType::query()->get()->keyBy('code');

        $admissionRows = $admissions->map(fn (Admission $admission) => $this->buildAdmissionRow($admission, $roomTypesByCode));

        return response()->json([
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'specialist' => $doctor->specialist?->name,
            ],
            'doctor_role' => $role,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'patients_count' => $admissionRows->pluck('patient_id')->unique()->count(),
                'admissions_count' => $admissionRows->count(),
                'room_revenue' => (float) $admissionRows->sum('room_revenue'),
                'services_revenue' => (float) $admissionRows->sum('services_revenue'),
                'operations_revenue' => (float) $admissionRows->sum('operations_revenue'),
                'total_revenue' => (float) $admissionRows->sum('total_revenue'),
            ],
            'room_revenue_by_type' => $this->groupRoomRevenueByType($admissionRows),
            'admissions' => $admissionRows->values(),
        ]);
    }

    /**
     * @param  Collection<int|string, RoomType>  $roomTypesByCode
     * @return array{admission_id: int, admission_number: string|null, patient_id: int, patient_name: string|null, admission_date: string|null, discharge_date: string|null, status: string, room_type_code: string|null, room_type_name: string|null, room_revenue: float, services_revenue: float, operations_revenue: float, total_revenue: float}
     */
    private function buildAdmissionRow(Admission $admission, Collection $roomTypesByCode): array
    {
        $roomRevenue = (float) $admission->requestedServices
            ->where('name', RequestedService::AccommodationFeeName)
            ->sum('total_price');

        $servicesRevenue = (float) $admission->requestedServices
            ->where('name', '!=', RequestedService::AccommodationFeeName)
            ->sum('total_price');

        $operationsRevenue = (float) $admission->operations->sum('price');

        $roomTypeCode = $admission->bed?->room?->room_type;

        return [
            'admission_id' => $admission->id,
            'admission_number' => $admission->admission_number,
            'patient_id' => $admission->patient_id,
            'patient_name' => $admission->patient?->name,
            'admission_date' => $admission->admission_date?->toIso8601String(),
            'discharge_date' => $admission->discharge_date?->toIso8601String(),
            'status' => $admission->status,
            'room_type_code' => $roomTypeCode,
            'room_type_name' => $roomTypeCode !== null ? ($roomTypesByCode->get($roomTypeCode)?->name ?? $roomTypeCode) : null,
            'room_revenue' => $roomRevenue,
            'services_revenue' => $servicesRevenue,
            'operations_revenue' => $operationsRevenue,
            'total_revenue' => $roomRevenue + $servicesRevenue + $operationsRevenue,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $admissionRows
     * @return Collection<int, array{room_type_code: string|null, room_type_name: string, total: float}>
     */
    private function groupRoomRevenueByType(Collection $admissionRows): Collection
    {
        return $admissionRows
            ->filter(fn (array $row) => $row['room_revenue'] > 0)
            ->groupBy(fn (array $row) => $row['room_type_code'] ?? 'unknown')
            ->map(fn (Collection $rows, string $code) => [
                'room_type_code' => $code === 'unknown' ? null : $code,
                'room_type_name' => $rows->first()['room_type_name'] ?? 'غير محدد',
                'total' => (float) $rows->sum('room_revenue'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(DoctorRevenueReportRequest $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfMonth()->endOfDay();

        return [$from, $to];
    }
}

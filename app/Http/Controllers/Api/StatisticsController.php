<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\Bed;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Operation;
use App\Models\RequestedService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function occupancy(): JsonResponse
    {
        $totalBeds = Bed::query()->count();
        $occupiedBeds = Bed::query()->where('status', 'occupied')->count();
        $maintenanceBeds = Bed::query()->where('status', 'maintenance')->count();
        $availableBeds = $totalBeds - $occupiedBeds - $maintenanceBeds;

        $byWard = Bed::query()
            ->join('rooms', 'rooms.id', '=', 'beds.room_id')
            ->join('wards', 'wards.id', '=', 'rooms.ward_id')
            ->join('floors', 'floors.id', '=', 'wards.floor_id')
            ->selectRaw('floors.id as floor_id, floors.name as floor_name, wards.id as ward_id, wards.name as ward_name')
            ->selectRaw('COUNT(*) as total_beds')
            ->selectRaw("SUM(CASE WHEN beds.status = 'occupied' THEN 1 ELSE 0 END) as occupied_beds")
            ->groupBy('floors.id', 'floors.name', 'wards.id', 'wards.name')
            ->orderBy('floors.id')
            ->orderBy('wards.id')
            ->get();

        return response()->json([
            'summary' => [
                'total_beds' => $totalBeds,
                'occupied_beds' => $occupiedBeds,
                'available_beds' => $availableBeds,
                'maintenance_beds' => $maintenanceBeds,
                'occupancy_rate' => $totalBeds > 0 ? round($occupiedBeds / $totalBeds * 100, 1) : 0,
            ],
            'by_ward' => $byWard,
        ]);
    }

    public function admissions(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $statusCounts = Admission::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereBetween('admission_date', [$from, $to])
            ->groupBy('status')
            ->pluck('total', 'status');

        $typeCounts = Admission::query()
            ->selectRaw('admission_type, COUNT(*) as total')
            ->whereBetween('admission_date', [$from, $to])
            ->groupBy('admission_type')
            ->pluck('total', 'admission_type');

        $dailyTrend = Admission::query()
            ->selectRaw('DATE(admission_date) as date, COUNT(*) as total')
            ->whereBetween('admission_date', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $averageLengthOfStayHours = Admission::query()
            ->whereNotNull('discharge_date')
            ->whereBetween('admission_date', [$from, $to])
            ->get(['admission_date', 'discharge_date'])
            ->avg(fn (Admission $admission) => $admission->admission_date->diffInHours($admission->discharge_date));

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'active_admissions' => Admission::query()->where('status', 'admitted')->count(),
            'status_counts' => $statusCounts,
            'type_counts' => $typeCounts,
            'daily_trend' => $dailyTrend,
            'average_length_of_stay_hours' => $averageLengthOfStayHours ? round($averageLengthOfStayHours, 1) : null,
        ]);
    }

    public function financials(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $dailyRevenue = Invoice::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as date, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $depositsByMethod = AdmissionDeposit::query()
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'paid_total' => (float) Invoice::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum('total'),
            'outstanding_total' => (float) Invoice::query()->whereIn('status', ['issued', 'draft'])->sum('total'),
            'deposits_total' => (float) AdmissionDeposit::query()->whereBetween('paid_at', [$from, $to])->sum('amount'),
            'services_total' => (float) (RequestedService::query()
                ->whereHas('admission', fn ($query) => $query->whereBetween('admission_date', [$from, $to]))
                ->selectRaw('SUM(quantity * unit_price) as total')
                ->value('total') ?? 0),
            'daily_revenue' => $dailyRevenue,
            'deposits_by_method' => $depositsByMethod,
        ]);
    }

    public function doctorsAndServices(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $topDoctorRows = Admission::query()
            ->whereNotNull('admitting_doctor_id')
            ->whereBetween('admission_date', [$from, $to])
            ->selectRaw('admitting_doctor_id, COUNT(*) as admissions_count')
            ->groupBy('admitting_doctor_id')
            ->orderByDesc('admissions_count')
            ->limit(10)
            ->get();

        $doctorsById = Doctor::query()->whereIn('id', $topDoctorRows->pluck('admitting_doctor_id'))->get()->keyBy('id');

        $topDoctors = $topDoctorRows->map(function ($row) use ($doctorsById) {
            $doctor = $doctorsById->get((int) $row->admitting_doctor_id);

            return [
                'id' => (int) $row->admitting_doctor_id,
                'name' => $doctor?->name,
                'specialist' => $doctor?->specialist,
                'admissions_count' => (int) $row->admissions_count,
            ];
        });

        $topServices = RequestedService::query()
            ->whereHas('admission', fn ($query) => $query->whereBetween('admission_date', [$from, $to]))
            ->selectRaw('name, SUM(quantity) as total_quantity, SUM(quantity * unit_price) as total_revenue')
            ->groupBy('name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'top_doctors' => $topDoctors,
            'top_services' => $topServices,
        ]);
    }

    public function operations(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $scheduledToday = Operation::query()
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
            ->count();

        $completedToday = Operation::query()
            ->where('status', 'completed')
            ->whereBetween('ended_at', [$todayStart, $todayEnd])
            ->count();

        $cancelledToday = Operation::query()
            ->where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$todayStart, $todayEnd])
            ->count();

        $statusCounts = Operation::query()
            ->whereBetween('scheduled_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $bySurgeonRows = Operation::query()
            ->whereNotNull('surgeon_id')
            ->whereBetween('scheduled_at', [$from, $to])
            ->selectRaw('surgeon_id, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->groupBy('surgeon_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $surgeonsById = Doctor::query()->whereIn('id', $bySurgeonRows->pluck('surgeon_id'))->get()->keyBy('id');

        $bySurgeon = $bySurgeonRows->map(function ($row) use ($surgeonsById) {
            $doctor = $surgeonsById->get((int) $row->surgeon_id);

            return [
                'id' => (int) $row->surgeon_id,
                'name' => $doctor?->name,
                'specialist' => $doctor?->specialist,
                'total' => (int) $row->total,
                'completed_count' => (int) $row->completed_count,
                'cancelled_count' => (int) $row->cancelled_count,
            ];
        });

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'today' => [
                'scheduled' => $scheduledToday,
                'completed' => $completedToday,
                'cancelled' => $cancelledToday,
            ],
            'status_counts' => $statusCounts,
            'by_surgeon' => $bySurgeon,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyRevenueReportRequest;
use App\Services\DailyRevenueReportService;
use Illuminate\Http\JsonResponse;

class DailyRevenueReportController extends Controller
{
    public function __construct(private readonly DailyRevenueReportService $report) {}

    public function index(DailyRevenueReportRequest $request): JsonResponse
    {
        return response()->json(
            $this->report->build($request->filled('month') ? $request->string('month')->toString() : null)
        );
    }
}

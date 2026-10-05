<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RevenueCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RevenueCalculatorController extends Controller
{
    public function __construct(private readonly RevenueCalculatorService $calculator) {}

    public function show(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->query('date')) : now();
        $userId = $request->filled('user_id') ? $request->integer('user_id') : null;

        return response()->json([
            ...$this->calculator->calculate($date, $userId),
            'generated_by' => $request->user()?->name,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PaymentsReportController extends Controller
{
    /**
     * Users who can be picked as a deposit's recorder (e.g. the revenue calculator's filter).
     */
    public function recorders(): JsonResponse
    {
        return response()->json(
            User::query()->orderBy('name')->get(['id', 'name'])
        );
    }
}

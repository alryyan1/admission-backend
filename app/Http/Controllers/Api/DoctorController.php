<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DoctorDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request, DoctorDirectory $directory): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        return response()->json($directory->search($search));
    }
}

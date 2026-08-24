<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query()
            ->with('causer:id,name,username')
            ->latest('id');

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->integer('causer_id'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        $activities = $query->paginate($request->integer('per_page', 25));

        return response()->json($activities);
    }

    public function subjectTypes(): JsonResponse
    {
        $types = Activity::query()
            ->select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn (string $type) => [
                'value' => $type,
                'label' => class_basename($type),
            ])
            ->sortBy('label')
            ->values();

        return response()->json($types);
    }

    public function causers(): JsonResponse
    {
        $users = User::query()->orderBy('name')->get(['id', 'name', 'username']);

        return response()->json($users);
    }
}

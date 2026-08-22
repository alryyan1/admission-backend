<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with(['tokens' => function ($query) {
                $query->orderByDesc('last_used_at')->orderByDesc('created_at');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'role', 'is_active']);

        return response()->json($users);
    }

    public function destroy(Request $request, PersonalAccessToken $token): Response
    {
        if ($request->user()->currentAccessToken()->id === $token->id) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'لا يمكن إنهاء جلستك الحالية من هذه الصفحة.');
        }

        $token->delete();

        return response()->noContent();
    }

    public function destroyForUser(Request $request, User $user): Response
    {
        $user->tokens()
            ->when(
                $request->user()->is($user),
                fn ($query) => $query->whereNot('id', $request->user()->currentAccessToken()->id),
            )
            ->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webuser;
use App\Support\ApiErrorFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsersApiController extends Controller
{
    /**
     * GET /api/users
     * Список пользователей для внешней авторизации.
     *
     * Query params:
     * - only_active=1|0 (по умолчанию 1)
     */
    public function index(Request $request): JsonResponse
    {
        $onlyActiveRaw = $request->query('only_active', '1');
        $onlyActive = filter_var($onlyActiveRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $query = Webuser::with('role')->orderBy('Id');

        // По умолчанию отдаём только активные учётные записи.
        // only_active=0 / false / off / no — отключает фильтр.
        if ($onlyActive !== false) {
            $query->where('IsActive', 1);
        }

        $users = $query->get();

        $payload = $users->map(fn (Webuser $user): array => $this->mapUser($user))->values();

        return response()->json([
            'count' => $payload->count(),
            'users' => $payload,
        ]);
    }

    /**
     * POST /api/users/auth
     * Проверка логина/пароля для внешних приложений.
     */
    public function auth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $login = trim((string) $data['login']);
        $password = (string) $data['password'];

        $user = Webuser::with('role')
            ->where('Login', $login)
            ->first();

        if (!$user || !Hash::check($password, (string) ($user->Password ?? ''))) {
            return ApiErrorFormatter::response(
                $request,
                401,
                'invalid_credentials',
                'Invalid credentials'
            );
        }

        if (!(bool) ($user->IsActive ?? 0)) {
            return ApiErrorFormatter::response(
                $request,
                403,
                'user_inactive',
                'User is inactive'
            );
        }

        return response()->json([
            'authorized' => true,
            'user' => $this->mapUser($user),
        ]);
    }

    private function mapUser(Webuser $user): array
    {
        return [
            'id' => (int) ($user->Id ?? 0),
            'login' => (string) ($user->Login ?? ''),
            'is_active' => (bool) ($user->IsActive ?? 0),
            'role' => [
                'id' => isset($user->IdRole) ? (int) $user->IdRole : null,
                'name' => (string) data_get($user, 'role.NameRole', ''),
            ],
            'last_update' => isset($user->LastUpdate) ? (string) $user->LastUpdate : null,
        ];
    }
}


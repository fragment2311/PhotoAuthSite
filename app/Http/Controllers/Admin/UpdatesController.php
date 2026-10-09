<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UpdateAgentClient;
use Illuminate\Http\Request;
use Throwable;

class UpdatesController extends Controller
{
    public function check(UpdateAgentClient $agent)
    {
        return $this->forward($agent, 'POST', '/check');
    }

    public function status(UpdateAgentClient $agent)
    {
        return $this->forward($agent, 'GET', '/status');
    }

    public function start(Request $request, UpdateAgentClient $agent)
    {
        $validated = $request->validate([
            'target_sha' => ['required', 'regex:/^[a-f0-9]{40}$/'],
            'run_migrations' => ['required', 'boolean'],
        ]);

        return $this->forward($agent, 'POST', '/start', [
            'target_sha' => $validated['target_sha'],
            'run_migrations' => (bool) $validated['run_migrations'],
        ]);
    }

    public function acknowledgeRecovery(UpdateAgentClient $agent)
    {
        return $this->forward($agent, 'POST', '/acknowledge-recovery');
    }

    private function forward(UpdateAgentClient $agent, string $method, string $path, array $payload = [])
    {
        try {
            $result = $agent->request($method, $path, $payload);
            return response()->json($result['data'], $result['status']);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json([
                'error' => $exception->getMessage() ?: 'Сервис обновления временно недоступен.',
            ], 503);
        }
    }
}

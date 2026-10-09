<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ErrorNotificationService;
use App\Support\ApiErrorFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemDiagnosticsApiController extends Controller
{
    public function channelsStatus(Request $request): JsonResponse
    {
        $requestId = ApiErrorFormatter::resolveRequestId($request);

        $mailer = (string) config('mail.default', 'log');
        $mailHost = (string) config('mail.mailers.smtp.host', '');
        $mailConfigured = $mailer !== 'log' && $mailer !== 'array' && $mailHost !== '';

        $queueConnection = (string) config('services.error_notifications.queue_connection', (string) config('queue.default', 'sync'));
        $queueName = (string) config('services.error_notifications.queue_name', 'error-notifications');

        return response()->json([
            'success' => true,
            'data' => [
                'request_id' => $requestId,
                'email' => [
                    'enabled' => true,
                    'configured' => $mailConfigured,
                    'recipient' => (string) config('services.error_notifications.email_to', 'access_line@bk.ru'),
                ],
                'queue' => [
                    'connection' => $queueConnection,
                    'queue' => $queueName,
                ],
            ],
        ]);
    }

    public function testEmail(Request $request, ErrorNotificationService $service): JsonResponse
    {
        $requestId = ApiErrorFormatter::resolveRequestId($request);
        $clientId = trim((string) $request->header('X-Client-Id', (string) $request->query('client_id', '')));

        $entry = $service->log('critical', '[API TEST] Проверка email канала уведомлений', [
            'test_mode' => true,
            'request_id' => $requestId,
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'request_url' => $request->fullUrl(),
            'request_ip' => $request->ip(),
            'request_user_agent' => (string) $request->userAgent(),
            'client_id' => $clientId !== '' ? $clientId : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'request_id' => $requestId,
                'queued' => true,
                'log_id' => $entry?->Id,
                'message' => 'Тестовая email-нотификация поставлена в обработку',
            ],
        ]);
    }
}


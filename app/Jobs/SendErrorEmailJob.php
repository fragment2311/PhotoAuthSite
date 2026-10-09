<?php

namespace App\Jobs;

use App\Services\ErrorNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendErrorEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /**
     * @var array<int, int>
     */
    public array $backoff;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
        public string $stackTrace,
        public ?int $logId = null
    ) {
        $this->tries = (int) config('services.error_notifications.email_tries', 5);

        $backoffRaw = config('services.error_notifications.email_backoff_seconds', [30, 120, 300]);
        $backoff = is_array($backoffRaw) ? $backoffRaw : [30, 120, 300];
        $backoff = array_values(array_filter(array_map(static fn ($v): int => max(1, (int) $v), $backoff)));

        $this->backoff = $backoff !== [] ? $backoff : [30, 120, 300];
    }

    public function handle(ErrorNotificationService $service): void
    {
        $service->sendEmailNow(
            $this->level,
            $this->message,
            $this->context,
            $this->stackTrace,
            $this->logId
        );
    }
}


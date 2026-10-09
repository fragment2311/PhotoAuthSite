<?php

return [
    'client_id' => env('LICENSE_CLIENT_ID', null),
    'license_path' => storage_path('app/license/license.lic'),
    'public_key_path' => base_path('keys/public_key.pem'),
    'log_path' => storage_path('logs/license.log'),
    'log_max_size_bytes' => (int) env('LICENSE_LOG_MAX_SIZE_BYTES', 5 * 1024 * 1024),
    'log_keep_bytes' => (int) env('LICENSE_LOG_KEEP_BYTES', 1024 * 1024),
    'log_prune_chance_percent' => (int) env('LICENSE_LOG_PRUNE_CHANCE_PERCENT', 5),
    'cache_ttl_seconds' => (int) env('LICENSE_CACHE_TTL_SECONDS', 120),
    'timezone' => env('LICENSE_TIMEZONE', 'UTC'),

    'allowed_features' => ['report', 'export', 'calc'],

    // NEW:
    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 7),
    'warn_before_days' => (int) env('LICENSE_WARN_BEFORE_DAYS', 7),
];

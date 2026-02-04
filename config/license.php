<?php

return [
    'client_id' => env('LICENSE_CLIENT_ID', null),
    'license_path' => storage_path('app/license/license.lic'),
    'public_key_path' => base_path('keys/public_key.pem'),
    'cache_ttl_seconds' => (int) env('LICENSE_CACHE_TTL_SECONDS', 120),
    'timezone' => env('LICENSE_TIMEZONE', 'UTC'),

    'allowed_features' => ['report', 'export', 'calc'],

    // NEW:
    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 7),
    'warn_before_days' => (int) env('LICENSE_WARN_BEFORE_DAYS', 7),
];

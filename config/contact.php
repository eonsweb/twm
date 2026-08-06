<?php

return [
    'rate_limit' => [
        'ip_per_hour' => (int) env('CONTACT_RATE_LIMIT_PER_IP', 8),
        'email_per_hour' => (int) env('CONTACT_RATE_LIMIT_PER_EMAIL', 4),
    ],
];

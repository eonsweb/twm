<?php

return [
    'disk' => env('MEDIA_DISK', 'public'),
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
    'default_visibility' => 'public',
    'retention_days' => 30,
    'types' => [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'max_kilobytes' => 10 * 1024,
        ],
        'video' => [
            'extensions' => ['mp4', 'webm', 'mov'],
            'mime_types' => ['video/mp4', 'video/webm', 'video/quicktime'],
            'max_kilobytes' => 250 * 1024,
        ],
        'audio' => [
            'extensions' => ['mp3', 'wav', 'm4a', 'ogg'],
            'mime_types' => ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/ogg'],
            'max_kilobytes' => 50 * 1024,
        ],
        'document' => [
            'extensions' => ['pdf', 'doc', 'docx', 'txt', 'rtf'],
            'mime_types' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/rtf', 'text/rtf'],
            'max_kilobytes' => 25 * 1024,
        ],
        'spreadsheet' => [
            'extensions' => ['xls', 'xlsx', 'csv'],
            'mime_types' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv', 'text/plain'],
            'max_kilobytes' => 25 * 1024,
        ],
        'presentation' => [
            'extensions' => ['ppt', 'pptx'],
            'mime_types' => ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'max_kilobytes' => 25 * 1024,
        ],
        'archive' => [
            'extensions' => ['zip'],
            'mime_types' => ['application/zip', 'application/x-zip-compressed'],
            'max_kilobytes' => 100 * 1024,
        ],
    ],
];

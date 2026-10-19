<?php

return [
    'frontend_url' => env('FRONTEND_URL'),
    'admin_name' => env('ADMIN_NAME', 'Platform Administrator'),
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'backup_disk' => env('BACKUP_DISK', env('FILESYSTEM_DISK', 'local')),
    'require_https' => env('DEPLOY_REQUIRE_HTTPS', true),
];

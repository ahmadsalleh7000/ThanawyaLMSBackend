<?php

return [
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),
    'content_security_policy' => implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "connect-src 'self'",
        "font-src 'self' https://fonts.gstatic.com data:",
        "form-action 'self' https://web.whatsapp.com https://wa.me",
        "frame-ancestors 'self'",
        "frame-src 'self'",
        "img-src 'self' data: blob:",
        "media-src 'self' blob:",
        "object-src 'none'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    ]),
];

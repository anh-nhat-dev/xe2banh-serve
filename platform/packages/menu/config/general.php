<?php

return [
    'locations' => [
        'header-menu'           => 'Header Navigation',
        'header-bottom-menu'    => 'Header Bottom Navigation',
        'main-menu'             => 'Main Navigation',
        'footer-menu'           => 'Footer Navigation',
        'sitemap'               => "Sitemap"
    ],
    'cache'     => [
        'enabled' => env('CACHE_FRONTEND_MENU', false),
    ],
];

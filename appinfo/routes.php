<?php

declare(strict_types=1);

return [
    'routes' => [
        // Admin settings
        ['name' => 'AdminSettings#save', 'url' => '/admin/settings', 'verb' => 'POST'],
        ['name' => 'AdminSettings#test', 'url' => '/admin/test', 'verb' => 'POST'],

        // Personal settings
        ['name' => 'PersonalSettings#save', 'url' => '/personal/settings', 'verb' => 'POST'],
        ['name' => 'PersonalSettings#getAddress', 'url' => '/personal/address', 'verb' => 'GET'],
    ],
];

<?php
declare(strict_types=1);

return [
    'driver'  => 'mysql',
    'host'    => \App\Core\Env::get('DB_HOST', '127.0.0.1'),
    'port'    => (int)\App\Core\Env::get('DB_PORT', '3306'),
    'name'    => \App\Core\Env::get('DB_NAME', ''),
    'user'    => \App\Core\Env::get('DB_USER', ''),
    'pass'    => \App\Core\Env::get('DB_PASS', ''),
    'charset' => 'utf8mb4',
];

<?php

$config = [
    'database' => [
        'host'     => 'localhost',
        'port'     => 5432,
        'username' => 'postgres',
        'password' => 'postgres',
        'database' => 'your_database',
    ],
    'app' => [
        'name'            => 'Open Registry Database',
        'version'         => '1.0.0',
        'timezone'        => 'UTC',
        'debug'           => false,
        'session_timeout' => 3600,
    ],
];

function getDatabaseConnection(): PgConnection
{
    global $config;
    $db = $config['database'];
    return new PgConnection(
        $db['host'],
        $db['username'],
        $db['password'],
        $db['database'],
        (int)$db['port']
    );
}

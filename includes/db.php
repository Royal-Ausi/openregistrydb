<?php
require_once __DIR__ . '/pgsql_compat.php';

$dbHost = getenv('OR_DB_HOST') ?: 'localhost';
$dbPort = (int)(getenv('OR_DB_PORT') ?: 5432);
$dbUser = getenv('OR_DB_USER') ?: 'postgres';
$dbPass = getenv('OR_DB_PASS') ?: '';
$dbName = getenv('OR_DB_NAME') ?: '';

$configFile = dirname(__DIR__) . '/config.php';
if (is_readable($configFile)) {
    /** @var array<string, mixed> $config */
    include $configFile;
    if (!empty($config['database'])) {
        $db = $config['database'];
        $dbHost = $db['host'] ?? $dbHost;
        $dbPort = (int)($db['port'] ?? $dbPort);
        $dbUser = $db['username'] ?? $dbUser;
        $dbPass = $db['password'] ?? $dbPass;
        $dbName = $db['database'] ?? $dbName;
    }
}

$conn = new PgConnection($dbHost, $dbUser, $dbPass, $dbName, $dbPort);


if ($conn->connect_error !== '') {
    die('Connection failed: ' . $conn->connect_error);
}

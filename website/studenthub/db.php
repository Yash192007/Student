<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function portal_database_settings(): array
{
    $production = getenv('STUDENTHUB_ENV') === 'production';
    if ($production) {
        foreach (['STUDENTHUB_DB_HOST', 'STUDENTHUB_DB_NAME', 'STUDENTHUB_DB_USER', 'STUDENTHUB_DB_PASSWORD'] as $requiredSetting) {
            $value = getenv($requiredSetting);
            if ($value === false || $value === '') {
                throw new RuntimeException('Production requires STUDENTHUB_DB_HOST, STUDENTHUB_DB_NAME, STUDENTHUB_DB_USER, and a non-empty STUDENTHUB_DB_PASSWORD.');
            }
        }
    }

    $settings = [
        'host' => portal_config('STUDENTHUB_DB_HOST', '127.0.0.1'),
        'name' => portal_config('STUDENTHUB_DB_NAME', 'studenthub'),
        'user' => portal_config('STUDENTHUB_DB_USER', 'root'),
        'password' => portal_config('STUDENTHUB_DB_PASSWORD'),
        'port' => (int) portal_config('STUDENTHUB_DB_PORT', '3306'),
    ];

    return $settings;
}

function portal_db(): mysqli
{
    static $connection;
    if ($connection instanceof mysqli) {
        return $connection;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $settings = portal_database_settings();
    $connection = new mysqli(
        $settings['host'],
        $settings['user'],
        $settings['password'],
        $settings['name'],
        $settings['port']
    );
    $connection->set_charset('utf8mb4');
    return $connection;
}

function portal_pdo(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $settings = portal_database_settings();
    $connection = new PDO(
        "mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['name']};charset=utf8mb4",
        $settings['user'],
        $settings['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $connection;
}

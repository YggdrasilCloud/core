<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Ensure APP_ENV is set to 'test' for functional tests
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'test';
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'];

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}

// Create test database schema
if ($_ENV['APP_ENV'] === 'test') {
    $testDbPath = dirname(__DIR__).'/var/test.db';
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }

    passthru(sprintf(
        'APP_ENV=test php "%s/bin/console" doctrine:schema:create --quiet 2>&1',
        dirname(__DIR__)
    ));
}

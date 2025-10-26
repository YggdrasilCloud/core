<?php

declare(strict_types=1);

// Composer autoloader
require_once dirname(__DIR__).'/vendor/autoload.php';

// Force test environment (also defined in phpunit.xml.dist)
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

// End of bootstrap

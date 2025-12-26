<?php

declare(strict_types=1);

use DG\BypassFinals;

// Composer autoloader
require_once dirname(__DIR__).'/vendor/autoload.php';

// Enable BypassFinals to allow mocking final classes in tests
BypassFinals::enable();

// Force test environment (also defined in phpunit.xml.dist)
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

// End of bootstrap

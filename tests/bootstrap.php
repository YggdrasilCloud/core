<?php

declare(strict_types=1);

// Chargement auto de Composer
require_once dirname(__DIR__).'/vendor/autoload.php';

// Force l'environnement de test (phpunit.xml.dist le définit aussi)
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

// Fin du bootstrap

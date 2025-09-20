<?php

// PHPStan bootstrap file for Laravel application

// Load the Laravel application bootstrap
require_once __DIR__ . '/bootstrap/app.php';

// Boot the Laravel application for static analysis
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();
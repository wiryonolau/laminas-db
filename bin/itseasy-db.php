<?php

declare(strict_types=1);


chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_string($path) && __FILE__ !== $path && is_file($path)) {
        return false;
    }
    unset($path);
}

// Composer autoloading
include __DIR__ . '/../vendor/autoload.php';

$application = new \Symfony\Component\Console\Application();

$commands = [
    \Itseasy\Database\Console\Command\SchemaCommand::class,
    \Itseasy\Database\Console\Command\DataCommand::class
];

foreach ($commands as $command) {
    $application->add(new $command());
}

$application->run();

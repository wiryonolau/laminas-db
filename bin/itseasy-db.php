<?php

declare(strict_types=1);

use Laminas\Log\LoggerInterface;

chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_string($path) && __FILE__ !== $path && is_file($path)) {
        return false;
    }
    unset($path);
}

// Composer autoloading, require composer ^2.2
include $_composer_autoload_path ?? __DIR__ . '/../vendor/autoload.php';

$reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
define("APP_DIR", dirname(dirname(dirname($reflection->getFileName()))));

$application = new \Symfony\Component\Console\Application();

$commands = [
    \Itseasy\Database\Console\Command\SchemaCommand::class,
    \Itseasy\Database\Console\Command\DataCommand::class
];

$logger = new Laminas\Log\Logger();
$logger->addWriter('stream', null, ['stream' => 'php://stderr']);

foreach ($commands as $command) {
    $commandObject = new $command();
    $commandObject->setLogger($logger);
    $application->add(new $command());
}

$application->run();

<?php

declare(strict_types=1);

namespace Itseasy\Repository\Factory;

use Psr\Container\ContainerInterface;
use Itseasy\Repository\GenericRepository;
use Itseasy\Database;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;

/**
 * For Laminas ServiceManager only
 */
class RepositoryAbstractServiceFactory implements AbstractFactoryInterface
{
    public const SERVICE_SUFFIX = 'Repository';

    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $namespace = explode("\\", $requestedName);
        $repository = preg_split('/(?=[A-Z])/', end($namespace), -1, PREG_SPLIT_NO_EMPTY);
        array_pop($repository);
        $table_name = strtolower(implode("_", $repository));

        return new GenericRepository($container->get(Database::class), $table_name);
    }

    public function canCreate(ContainerInterface $container, $requestedName)
    {
        $namespace = explode("\\", $requestedName);
        if (strpos(end($namespace), self::SERVICE_SUFFIX) > -1) {
            return true;
        }
        return false;
    }
}

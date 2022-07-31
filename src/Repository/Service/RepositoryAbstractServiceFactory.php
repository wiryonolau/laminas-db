<?php

declare(strict_types=1);

namespace Itseasy\Repository\Service;

use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use Itseasy\Repository\AbstractRepository;

class RepositoryAbstractServiceFactory implements AbstractFactoryInterface
{
    public const SERVICE_SUFFIX = 'Repository';

    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $namespace = explode("\\", $requestedName);
        $repository = preg_split('/(?=[A-Z])/', end($namespace));
        array_pop($repository);
        $table_name = strtolower(implode("_", $repository));

        if (!$container->has(Database::class)) {
            throw new InvalidServiceException();
        }

        return new AbstractRepository($container->get(Database::class), $table_name);
    }

    public function canCreate(ContainerInterface $container, $requestedName)
    {
        $namespace = explode("\\", $requestedName);
        if (strpos(end($namespace), self::SERVICE_SUFFIX) !== 0) {
            return true;
        }
        return false;
    }
}

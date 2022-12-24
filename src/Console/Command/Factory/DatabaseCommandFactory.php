<?php

namespace Itseasy\Database\Console\Command\Factory;

use Psr\Container\ContainerInterface;

class DatabaseCommandFactory
{
    public function __invoke(ContainerInterface $container): DatabaseCommand
    {
        return new DatabaseCommand();
    }
}

<?php

namespace Itseasy\Database\Metadata\Hydrator;

use Laminas\Hydrator\ClassMethodsHydrator;
use Laminas\Hydrator\NamingStrategy\CompositeNamingStrategy;
use Laminas\Hydrator\NamingStrategy\MapNamingStrategy;

class ColumnObjectHydrator extends ClassMethodsHydrator
{
    public function __construct()
    {
        parent::__construct();
        $this->setNamingStrategy(
            new CompositeNamingStrategy([
                "errata" => MapNamingStrategy::createFromHydrationMap([
                    "errata" => "erratas"
                ])
            ])
        );
    }
}

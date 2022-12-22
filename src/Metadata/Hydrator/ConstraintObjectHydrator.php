<?php

namespace Itseasy\Database\Metadata\Hydrator;

use Itseasy\Database\Metadata\Hydrator\Strategy\NullToArrayStrategy;
use Laminas\Hydrator\ClassMethodsHydrator;

class ConstraintObjectHydrator extends ClassMethodsHydrator
{
    public function __construct()
    {
        parent::__construct();
        $this->addStrategy("columns", new NullToArrayStrategy());
        $this->addStrategy("referencedColumns", new NullToArrayStrategy());
    }
}

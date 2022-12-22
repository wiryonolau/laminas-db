<?php

namespace Itseasy\Database\Metadata\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;

class NullToArrayStrategy implements StrategyInterface
{
    public function hydrate($value, ?array $data)
    {
        return empty($value) ? [] : $value;
    }

    public function extract($value, ?object $object = null)
    {
        return $value;
    }
}

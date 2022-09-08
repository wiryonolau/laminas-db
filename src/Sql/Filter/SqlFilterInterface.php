<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Filter;

use Laminas\Db\Sql\SqlInterface;
use Laminas\Db\Sql\Predicate\PredicateSet;

// Only for type hinting
interface SqlFilterInterface
{
    public function applyFilter(
        SqlInterface $sql,
        string $value,
        string $combination = PredicateSet::OP_AND
    ): SqlInterface;
}

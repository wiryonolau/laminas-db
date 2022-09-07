<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Filter;

use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\PreparableSqlInterface;

// Only for type hinting
interface SqlFilterInterface
{
    public function applyFilter(
        PreparableSqlInterface $sql,
        string $value,
        string $combination = PredicateSet::OP_AND
    ): PreparableSqlInterface;
}

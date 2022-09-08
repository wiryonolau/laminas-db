<?php

declare(strict_types=1);

namespace Itseasy\Repository;

use Itseasy\Database\Sql\Filter\RegexSqlFilter;

/* For AbstractFactory */

class GenericRepository extends AbstractRepository
{
    protected function defineSqlFilter(): void
    {
        $this->setSqlFilter(new RegexSqlFilter([
            [
                "id:(.*)", function ($id) {
                    $p = new Predicate();
                    return $p->equalTo("id", $id);
                }
            ]
        ]));
    }
}

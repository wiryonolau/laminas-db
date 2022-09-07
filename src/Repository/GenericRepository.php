<?php

declare(strict_types=1);

namespace Itseasy\Repository;

use Itseasy\Database\Database;
use Itseasy\Database\Sql\Filter\RegexSqlFilter;

/* For AbstractFactory */

class GenericRepository extends AbstractRepository
{
    public function __construct(
        Database $db,
        string $table
    ) {
        parent::__construct($db, $table);

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

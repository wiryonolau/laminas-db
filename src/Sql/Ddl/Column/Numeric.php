<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\AbstractPrecisionColumn;

class Numeric extends AbstractPrecisionColumn implements PostgresColumnInterface
{
    protected $type = 'NUMERIC';
}

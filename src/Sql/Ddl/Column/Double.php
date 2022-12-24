<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\AbstractPrecisionColumn;

class Double extends AbstractPrecisionColumn implements MysqlColumnInterface
{
    protected $type = 'DOUBLE';
}

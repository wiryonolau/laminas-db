<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\AbstractPrecisionColumn;

class DoublePrecision extends AbstractPrecisionColumn implements PostgreColumnInterface
{
    protected $type = 'DOUBLE PRECISION';
}

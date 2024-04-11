<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\Column;

class Real extends Column implements PostgreColumnInterface
{
    protected $type = 'REAL';
}

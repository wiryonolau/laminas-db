<?php

declare(strict_types=1);

namespace Itseasy\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\Integer;

class Identity extends Integer
{
    protected $type = 'SERIAL';
}

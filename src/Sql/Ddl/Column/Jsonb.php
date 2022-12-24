<?php

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\Column;

class Jsonb extends Column implements PostgresColumnInterface
{
    protected $type = 'JSONB';
}

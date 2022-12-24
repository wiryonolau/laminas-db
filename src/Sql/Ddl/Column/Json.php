<?php

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\Column;

class Json extends Column implements MysqlColumnInterface, PostgresColumnInterface
{
    protected $type = 'JSON';
}

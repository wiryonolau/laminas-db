<?php

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\Integer;

class SmallInteger extends Integer implements MysqlColumnInterface, PostgresColumnInterface
{
    protected $type = 'SMALLINT';
}

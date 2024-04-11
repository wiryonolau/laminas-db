<?php

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\Integer;

class SmallInteger extends Integer implements MysqlColumnInterface, PostgreColumnInterface
{
    protected $type = 'SMALLINT';
}

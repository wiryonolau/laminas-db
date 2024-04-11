<?php

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\Text;

class Longtext extends Text implements MysqlColumnInterface, PostgreColumnInterface
{
    protected $type = 'LONGTEXT';
}

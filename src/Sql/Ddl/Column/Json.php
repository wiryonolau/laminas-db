<?php

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\AbstractLengthColumn;

class Json extends AbstractLengthColumn
{
    protected $type = 'JSON';
}

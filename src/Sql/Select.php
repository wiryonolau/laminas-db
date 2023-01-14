<?php

namespace Itseasy\Database\Sql;

use Laminas\Db\Adapter\Driver\DriverInterface;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\Select as SqlSelect;

class Select extends SqlSelect
{
    public const JOIN_INNER          = Join::JOIN_INNER;
    public const JOIN_OUTER          = Join::JOIN_OUTER;
    public const JOIN_FULL_OUTER     = Join::JOIN_FULL_OUTER;
    public const JOIN_LEFT           = Join::JOIN_LEFT;
    public const JOIN_RIGHT          = Join::JOIN_RIGHT;
    public const JOIN_RIGHT_OUTER    = Join::JOIN_RIGHT_OUTER;
    public const JOIN_LEFT_OUTER     = Join::JOIN_LEFT_OUTER;
    public const JOIN_CROSS          = Join::JOIN_CROSS;

    public function  __construct($table)
    {
        parent::__construct($table);
        $this->joins  = new Join();
    }
}

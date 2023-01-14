<?php

namespace Itseasy\Database\Sql;

use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join as SqlJoin;

class Join extends SqlJoin
{
    public const JOIN_CROSS  = 'cross';

    public function join($name, $on, $columns = [Select::SQL_STAR], $type = self::JOIN_INNER)
    {
        if (is_array($name) && (!is_string(key($name)) || count($name) !== 1)) {
            throw new Exception\InvalidArgumentException(
                sprintf("join() expects '%s' as a single element associative array", array_shift($name))
            );
        }

        if (!is_array($columns)) {
            $columns = [$columns];
        }

        if ($type == self::JOIN_CROSS) {
            $this->joins[] = [
                'name'    => $name,
                'on'      => new Expression("1=1"),
                'columns' => $columns,
                'type'    => self::JOIN_INNER
            ];
        } else {
            $this->joins[] = [
                'name'    => $name,
                'on'      => $on,
                'columns' => $columns,
                'type'    => $type ? $type : self::JOIN_INNER,
            ];
        }

        return $this;
    }
}

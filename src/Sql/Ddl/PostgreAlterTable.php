<?php

namespace Itseasy\Database\Sql\Ddl;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\TableIdentifier;
use Laminas\Db\Sql\Ddl\Column;

class PostgreAlterTable extends AlterTable
{
    public const ALTER_COLUMNS    = 'alterColumns';

    /** @var array */
    protected $alterColumns = [];

    /**
     * @param string|TableIdentifier $table
     */
    public function __construct($table = '')
    {
        $this->specifications[self::ALTER_COLUMNS]  = [
            "%1\$s" => [
                [2 => "ALTER COLUMN %1\$s %2\$s,\n", 'combinedby' => ""],
            ],
        ];

        $table ? $this->setTable($table) : null;
    }

    /**
     * @param  string $name
     * @return $this Provides a fluent interface
     */
    public function alterColumn($name, Column\ColumnInterface $column)
    {
        $this->alterColumns[$name] = $column;

        return $this;
    }

    /** @return string[] */
    protected function processAlterColumns(?PlatformInterface $adapterPlatform = null)
    {
        $sqls = [];
        foreach ($this->alterColumns as $name => $column) {
            $sqls[] = [
                $adapterPlatform->quoteIdentifier($name),
                $this->processExpression($column, $adapterPlatform),
            ];
        }

        return [$sqls];
    }
}

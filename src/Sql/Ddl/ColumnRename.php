<?php

namespace Itseasy\Database\Sql\Ddl;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;

class ColumnRename extends AbstractSql implements SqlInterface
{
    public const COLUMN_RENAME = 'columnRename';

    /** @var array */
    protected $specifications = [
        self::COLUMN_RENAME => 'ALTER TABLE %1$s RENAME COLUMN %2$s TO %3$s',
    ];

    protected $table;
    protected $old_name;
    protected $new_name;

    public function __construct(string $table, string $old_name, string $new_name)
    {
        $this->table = $table;
        $this->old_name = $old_name;
        $this->new_name = $new_name;
    }

    protected function processColumnRename(?PlatformInterface $adapterPlatform = null)
    {
        return [$this->table, $this->old_name, $this->new_name];
    }
}

<?php

namespace Itseasy\Database\Sql\Ddl;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;

/**
 * Will not drop primary key with auto increment
 */
class DropPrimaryKey extends AbstractSql implements SqlInterface
{
    public const DROP_PRIMARY_KEY = 'dropPrimaryKey';

    /** @var array */
    protected $specifications = [
        self::DROP_PRIMARY_KEY => 'ALTER TABLE %1$s DROP PRIMARY KEY'
    ];

    protected $table = "";

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    protected function processDropPrimaryKey(?PlatformInterface $adapterPlatform = null)
    {
        return [
            $adapterPlatform->quoteIdentifier($this->table),
        ];
    }
}

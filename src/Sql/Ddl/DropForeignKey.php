<?php

namespace Itseasy\Database\Sql\Ddl;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;

class DropForeignKey extends AbstractSql implements SqlInterface
{
    public const DROP_PRIMARY_KEY = 'dropForeignKey';

    /** @var array */
    protected $specifications = [
        self::DROP_PRIMARY_KEY => 'ALTER TABLE %1$s DROP FOREIGN KEY %2$s'
    ];

    protected $table = "";
    protected $foreignKeyName  = "";

    public function __construct(string $table, string $foreignKeyName)
    {
        $this->table = $table;
        $this->foreignKeyName = $foreignKeyName;
    }

    protected function processDropForeignKey(?PlatformInterface $adapterPlatform = null)
    {
        return [$this->table, $this->foreignKeyName];
    }
}

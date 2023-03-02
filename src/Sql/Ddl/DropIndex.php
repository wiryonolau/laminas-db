<?php

namespace Itseasy\Database\Sql\Ddl;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;

class DropIndex extends AbstractSql implements SqlInterface
{
    public const DROP_PRIMARY_KEY = 'dropIndex';

    /** @var array */
    protected $specifications = [
        self::DROP_PRIMARY_KEY => 'ALTER TABLE %1$s DROP INDEX %2$s'
    ];

    protected $table = "";
    protected $indexName  = "";

    public function __construct(string $table, string $indexName)
    {
        $this->table = $table;
        $this->indexName = $indexName;
    }

    protected function processDropIndex(?PlatformInterface $adapterPlatform = null)
    {
        return [$this->table, $this->indexName];
    }
}

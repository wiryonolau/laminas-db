<?php

namespace Itseasy\Database\Sql\Ddl\Schema;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\SqlInterface;
use Laminas\Db\Sql\TableIdentifier;

class MysqlCreateSchema extends AbstractSql implements SqlInterface
{
    const SCHEMA = "schema";
    const CHARSET = "charset";
    const COLLATE = "collate";

    protected $name;
    protected $charset;
    protected $collate;

    protected $specifications = [
        self::SCHEMA => "CREATE SCHEMA IF NOT EXISTS %1\$s",
        self::CHARSET => "DEFAULT CHARACTER SET %1\$s",
        self::COLLATE => "DEFAULT COLLATE %1\$s"
    ];

    public function __construct(string $name, string $charset = null, string $collate = null)
    {
        $this->name = $name;
        $this->charset = (is_null($charset) ? "utf8mb4" : $charset);
        $this->collate = (is_null($collate) ? "utf8mb4_unicode_ci" : $collate);
    }

    protected function processSchema(PlatformInterface $adapterPlatform = null): array
    {
        return [$this->name];
    }

    protected function processCharset(PlatformInterface $adapterPlatform = null): array
    {
        return [$this->charset];
    }

    protected function processCollate(PlatformInterface $adapterPlatform = null): array
    {
        return [$this->collate];
    }
}

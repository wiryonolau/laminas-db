<?php

namespace Itseasy\Database\Metadata\Source;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Exception\RuntimeException;
use Laminas\Db\Metadata\Source\Factory as LaminasSourceFactory;
use Laminas\Db\Metadata\MetadataInterface;

class Factory extends LaminasSourceFactory
{
    const PLATFORM_MYSQL = "MySQL";
    // Currently no specific case for MariaDB, use MySQL as value
    const PLATFORM_MARIADB = "MySQL";
    const PLATFORM_POSTGRESQL = "PostgreSQL";
    const PLATFORM_SQLITE = "SQLite";
    const PLATFORM_ORACLE = "Oracle";

    public static function createSourceFromAdapter(Adapter $adapter): MetadataInterface
    {
        $platformName = $adapter->getPlatform()->getName();

        try {
            switch ($platformName) {
                case self::PLATFORM_MYSQL:
                    return new MysqlMetadata($adapter);
                case self::PLATFORM_POSTGRESQL:
                    return new PostgresqlMetadata($adapter);
                default:
                    return parent::createSourceFromAdapter($adapter);
            }
        } catch (Throwable $th) {
            throw new RuntimeException($th->getMessage());
        }
    }
}

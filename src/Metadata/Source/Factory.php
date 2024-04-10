<?php

namespace Itseasy\Database\Metadata\Source;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Exception\RuntimeException;
use Laminas\Db\Metadata\Source\Factory as LaminasSourceFactory;
use Laminas\Db\Metadata\MetadataInterface;

class Factory extends LaminasSourceFactory
{
    const PLATFORM_MYSQL = "MySQL";
    const PLATFORM_MARIADB = "MariaDB";
    const PLATFORM_POSTGRESQL = "PostgreSQL";
    const PLATFORM_SQLITE = "SQLite";
    const PLATFORM_ORACLE = "Oracle";

    const PLATFORM_LIST = [
        self::PLATFORM_MARIADB,
        self::PLATFORM_MYSQL,
        self::PLATFORM_POSTGRESQL,
        self::PLATFORM_SQLITE,
        self::PLATFORM_ORACLE
    ];

    public static function createSourceFromAdapter(Adapter $adapter): MetadataInterface
    {
        $platformName = $adapter->getPlatform()->getName();

        try {
            switch ($platformName) {
                case self::PLATFORM_MARIADB:
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

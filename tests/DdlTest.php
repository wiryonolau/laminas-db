<?php

namespace Itseasy\DatabaseTest;

use Itseasy\Database\Database;
use Itseasy\Database\Metadata\Source\Factory;
use Laminas\Db\Adapter\Adapter;
use Laminas\Log\Logger;
use PHPUnit\Framework\TestCase;

final class DdlTest extends TestCase
{
    protected $logger;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->logger = new Logger();
        $this->logger->addWriter('stream', null, ['stream' => 'php://stderr']);
    }

    public function testMysql57()
    {
        $adapter = new Adapter([
            'driver'   => 'Pdo_Mysql',
            'hostname' => 'laminas-db_mysql57',
            'port'     => 3306,
            'username' => 'root',
            'password' => '888888',
            'database' => 'test',
            'driver_options' => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => true,
                \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            ],
        ]);

        $db = new Database($adapter, $this->logger);

        $meta = Factory::createSourceFromAdapter($db->getAdapter());

        $this->checkTable($meta);
    }

    public function testMysql80()
    {
        $adapter = new Adapter([
            'driver'   => 'Pdo_Mysql',
            'hostname' => 'laminas-db_mysql80',
            'port'     => 3306,
            'username' => 'root',
            'password' => '888888',
            'database' => 'test',
            'driver_options' => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => true,
                \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            ],
        ]);

        $db = new Database($adapter, $this->logger);

        $meta = Factory::createSourceFromAdapter($db->getAdapter());

        $this->checkTable($meta);
    }

    public function testMariadb10()
    {
        $adapter = new Adapter([
            'driver'   => 'Pdo_Mysql',
            'hostname' => 'laminas-db_mariadb10',
            'port'     => 3306,
            'username' => 'root',
            'password' => '888888',
            'database' => 'test',
            'driver_options' => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => true,
                \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            ],
        ]);

        $db = new Database($adapter, $this->logger);

        $meta = Factory::createSourceFromAdapter($db->getAdapter());

        $this->checkTable($meta);
    }

    public function testPostgres10()
    {
        $adapter = new Adapter([
            'driver'   => 'Pdo_Pgsql',
            'hostname' => 'laminas-db_postgres10',
            'port'     => 5432,
            'username' => 'postgres',
            'password' => '888888',
            'database' => 'postgres'
        ]);

        $db = new Database($adapter, $this->logger);

        $meta = Factory::createSourceFromAdapter($db->getAdapter());

        $this->checkTable($meta);
    }

    private function checkTable($meta)
    {
        $this->assertEquals(count($meta->getTables()), 2);

        foreach ($meta->getTables() as $table) {
            $this->assertEquals(count($table->getColumns()) > 0, true);
        }
    }

    // public function testSqlite()
    // {
    // }
}
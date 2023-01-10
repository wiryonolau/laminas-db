<?php

namespace Itseasy\DatabaseTest;

use Itseasy\Database\Database;
use Itseasy\Database\Metadata\Source\Factory;
use Itseasy\Database\Sql\Ddl\DdlUtilities;
use Itseasy\Database\Sql\Ddl\TableDiff;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Laminas\Db\Sql\Sql;
use Laminas\Log\Logger;
use PHPUnit\Framework\TestCase;
use Throwable;

final class DdlTest extends TestCase
{
    protected $logger;

    public function setUp(): void
    {
        $this->logger = new Logger();
        $this->logger->addWriter('stream', null, ['stream' => 'php://stderr']);
    }

    public function testMysql57()
    {
        if (getenv("DBTYPE") != "mysql") {
            $this->markTestSkipped(
                'Not require'
            );
        }

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
        debug($meta->getMetadata());

        $tables = $meta->getTables();
        foreach ($tables as $table) {
            $this->testDiff($db, $table);
        }

        $this->checkTable($meta);
    }

    public function testMysql80()
    {
        if (getenv("DBTYPE") != "mysql") {
            $this->markTestSkipped(
                'Not require'
            );
        }

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
        debug($meta->getMetadata());

        $tables = $meta->getTables();
        foreach ($tables as $table) {
            $this->testDiff($db, $table);
        }

        $this->checkTable($meta);
    }

    public function testMariadb10()
    {
        if (getenv("DBTYPE") != "mariadb") {
            $this->markTestSkipped(
                'Not require'
            );
        }

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

        $tables = $meta->getTables();
        foreach ($tables as $table) {
            $this->testDiff($db, $table);
        }

        $this->checkTable($meta);
    }

    public function testPostgres10()
    {
        if (getenv("DBTYPE") != "postgres") {
            $this->markTestSkipped(
                'Not require'
            );
        }


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

        $tables = $meta->getTables();
        foreach ($tables as $table) {
            $this->testDiff($db, $table);
            break;
        }

        $this->checkTable($meta);
    }

    private function testDiff(Database $db, AbstractTableObject $table)
    {
        $sql = new Sql($db->getAdapter());
        $diff = new TableDiff($db->getAdapter());
        $tableDdl = DdlUtilities::tableObjectToDdl($table, $db->getAdapter()->getPlatform()->getName());
        $ddls = $diff->diff($table);

        foreach ($ddls as $ddl) {
            debug($sql->buildSqlString($ddl));
        }
    }

    private function checkTable($meta)
    {
        foreach ($meta->getTables() as $table) {
            $this->assertEquals(count($table->getColumns()) > 0, true);
        }
    }
}

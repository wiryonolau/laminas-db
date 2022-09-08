<?php

namespace Itseasy\DatabaseTest;

use ArrayIterator;
use Itseasy\Database\Database;
use Itseasy\Database\Result;
use Itseasy\Database\Sql\Ddl;
use Laminas\Db\Adapter\Adapter;
use Laminas\Log\Logger;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testDatabase()
    {
        $logger = new Logger();
        $logger->addWriter('stream', null, ['stream' => 'php://stderr']);

        $adapter = new Adapter([
            'driver'   => 'Pdo_Sqlite',
            'database' => __DIR__ . '/db/sqlite.db',
        ]);

        $db = new Database($adapter, $logger);
        $repository = new Repository\Repository($db, "test");

        $result = $repository->dropTable();
        $result = $repository->dropTrigger();
        $result = $repository->createTable();

        $dummy_data = [
            "test1",
            "test2"
        ];

        foreach ($dummy_data as $index => $value) {
            $result = $repository->populateTable($value);
            $this->assertEquals($result->getGeneratedValue(), $index + 1);
        }

        $result = [
            $repository->getData(),
            $repository->getData("index_param"),
            $repository->getData("named_param")
        ];

        foreach ($result as $r) {
            $r->setObjectPrototype(Model\TestModel::class);
            $obj = $r->getFirstRow();
            $this->assertEquals($obj->name, $dummy_data[($obj->id - 1)]);
        }

        $this->assertEquals($result[0]->getFirstRow() instanceof Model\TestModel, true);
        $this->assertEquals($result[0]->getRows() instanceof ArrayIterator, true);
        $this->assertEquals($result[0]->getSingleValue(), 2);

        $result = new Result(new Model\TestModel());
        $r = $repository->getData(null, $result);
        $this->assertEquals($r->getFirstRow() instanceof Model\TestModel, true);

        $result = new Result(null, new Model\TestCollectionModel());
        $r = $repository->getData(null, $result);
        $this->assertEquals($r->getFirstRow() instanceof Model\TestModel, false);
        $this->assertEquals($r->getRows() instanceof Model\TestCollectionModel, true);

        $result = new Result(new Model\TestModel(), new Model\TestCollectionModel());
        $r = $repository->getData(null, $result);
        $this->assertEquals($r->getFirstRow() instanceof Model\TestModel, true);
        $this->assertEquals($r->getRows() instanceof Model\TestCollectionModel, true);

        $r = $repository->getData();
        $this->assertEquals($r->getFirstRow(Model\TestModel::class) instanceof Model\TestModel, true);
        $this->assertEquals($r->getRows(Model\TestCollectionModel::class) instanceof Model\TestCollectionModel, true);

        $rows = $r->getRows(ArrayIterator::class, Model\TestModel::class);
        foreach ($rows as $r) {
            $this->assertEquals($r instanceof Model\TestModel, true);
        }

        $rows = $repository->getFilterAwareRows("id:2", null, null, null, null, Model\TestModel::class);
        $this->assertEquals($rows->count(), 1);
        $this->assertEquals($rows->current()->id, 2);
    }

    public function testDdlDatabase()
    {
        $alter = new Ddl\Schema\MysqlAlterSchema("test", "utf8");
        $alter_sql = "ALTER SCHEMA test DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8mb4_unicode_ci";
        $this->assertEquals($alter->getSqlString(), $alter_sql);

        $create = new Ddl\Schema\MysqlCreateSchema("test", "utf8");
        $create_sql = "CREATE SCHEMA IF NOT EXISTS test DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8mb4_unicode_ci";
        $this->assertEquals($create->getSqlString(), $create_sql);

        $grant = new Ddl\Privilege\MysqlGrantPrivilege("test", "*", "hoho", "localhost");
        $grant_sql = "GRANT ALL PRIVILEGES ON `test`.* TO 'hoho'@'localhost'";
        $this->assertEquals($grant->getSqlString(), $grant_sql);
    }
}

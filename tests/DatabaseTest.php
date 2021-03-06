<?php
namespace Itseasy\DatabaseTest;

use Itseasy\Database\Database;
use Itseasy\Database\Result;
use Itseasy\Database\Sql\Ddl;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase {
    public function testDatabase() {
        $adapter = new Adapter([
            'driver'   => 'Pdo_Sqlite',
            'database' => __DIR__.'/db/sqlite.db',
        ]);
        $db = new Database($adapter);
        $repository = new Repository\Repository($db);

        $result = $repository->dropTable();
        $result = $repository->dropTrigger();
        $result = $repository->createTable();

        $dummy_data = [
            "test1",
            "test2"
        ];

        foreach ($dummy_data as $index => $value)  {
            $result = $repository->populateTable($value);
            $this->assertEquals($result->getGeneratedValue(), $index + 1);
        }

        $result = [
            $repository->getData(),
            $repository->getData("index_param"),
            $repository->getData("named_param")
        ];

        foreach ($result as $r) {
            $r->setObject(Model\TestModel::class);
            $obj = $r->getFirstRow();
            $this->assertEquals($obj->name, $dummy_data[($obj->id - 1)]);
        }

    }

    public function testDdlDatabase() {
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


?>

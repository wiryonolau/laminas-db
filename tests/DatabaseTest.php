<?php
namespace Itseasy\DatabaseTest;

use PHPUnit\Framework\TestCase;
use Itseasy\Database\Database;
use Itseasy\Database\Result;
use Laminas\Db\Adapter\Adapter;

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
}


?>

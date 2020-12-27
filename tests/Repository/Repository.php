<?php

namespace Itseasy\DatabaseTest\Repository;

use Itseasy\Database\Database;
use Laminas\Db\Sql;
use PDO;

class Repository {
    protected $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function dropTable() {
        $drop = "DROP TABLE IF EXISTS test";
        return $this->db->execute($drop);
    }

    public function createTable() {
        $create = new Sql\Ddl\CreateTable("test");

        $id = new Sql\Ddl\Column\Integer("id", true);
        $id->setOption('AUTO_INCREMENT', true);
        $create->addColumn($id);

        $create->addColumn(new Sql\Ddl\Column\Char("name", 255));

        $create->addConstraint(new Sql\Ddl\Constraint\PrimaryKey('id'));

        return $this->db->execute($create);
    }

    public function populateTable($name) {
        $insert = new Sql\Insert("test");
        $insert->columns(["name"]);
        $insert->values([$name]);

        return $this->db->execute($insert);
    }

    public function getData(?string $format = null) {
        $parameters = [];

        switch ($format) {
            case "index_param":
                $select = "SELECT * FROM test WHERE id = ?";
                $parameters = [2];
                break;
            case "named_param":
                $select = "SELECT * FROM test WHERE id = :id";
                $parameters = [
                    ":id" => 2
                ];
                break;
            default:
                $select = new Sql\Select("test");
                $select->where(["id" => 2]);
        }
        return $this->db->execute($select, $parameters);
    }
}

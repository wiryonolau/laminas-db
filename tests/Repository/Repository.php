<?php

namespace Itseasy\DatabaseTest\Repository;

use Itseasy\Database\Database;
use Itseasy\Database\Sql\Filter\RegexSqlFilter;
use Itseasy\Repository\AbstractRepository;
use Laminas\Db\Sql;
use Laminas\Db\Sql\Predicate\Predicate;

class Repository extends AbstractRepository
{
    public function __construct(Database $db, string $table)
    {
        parent::__construct($db, $table);

        $this->setSqlFilter(new RegexSqlFilter([
            [
                "id:(.*)", function ($id) {
                    $p = new Predicate();
                    return $p->equalTo("id", $id);
                }
            ]
        ]));
    }

    public function dropTable()
    {
        $drop = "DROP TABLE IF EXISTS test";
        return $this->db->execute($drop);
    }

    public function dropTrigger()
    {
        $drop = "DROP TRIGGER IF EXISTS test_AINS";
        $this->db->execute($drop);

        $drop = "DROP TRIGGER IF EXISTS test_AUPD";
        $this->db->execute($drop);
    }

    public function createTable()
    {
        $create = new Sql\Ddl\CreateTable("test");
        $id = new Sql\Ddl\Column\Integer("id", true);
        $id->setOption('AUTO_INCREMENT', true);
        $create->addColumn($id);
        $create->addColumn(new Sql\Ddl\Column\Char("name", 255));
        $create->addColumn(new Sql\Ddl\Column\Datetime("tech_creation_date", true));
        $create->addColumn(new Sql\Ddl\Column\Datetime("tech_modification_date", true));
        $create->addConstraint(new Sql\Ddl\Constraint\PrimaryKey('id'));
        $this->db->execute($create);

        $trigger_ains = "
        CREATE TRIGGER IF NOT EXISTS test_AINS AFTER INSERT
        ON test FOR EACH ROW
        BEGIN
			UPDATE test SET
                tech_creation_date = DATETIME('NOW'),
                tech_modification_date = DATETIME('NOW')
            WHERE id = NEW.id;
        END;
        ";
        $this->db->execute($trigger_ains);

        $trigger_aupd = "
        CREATE TRIGGER IF NOT EXISTS test_AUPD AFTER UPDATE
        ON test FOR EACH ROW
        BEGIN
			UPDATE test SET
                tech_modification_date = DATETIME('NOW')
            WHERE id = OLD.id;
        END;
        ";
        $this->db->execute($trigger_aupd);
    }

    public function populateTable($name)
    {
        $insert = new Sql\Insert("test");
        $insert->columns(["name"]);
        $insert->values([$name]);

        return $this->db->execute($insert);
    }

    public function getData(?string $format = null, $result = null)
    {
        $parameters = [];

        $select = new Sql\Select("test");

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

        return $this->db->execute($select, $parameters, $result);
    }
}

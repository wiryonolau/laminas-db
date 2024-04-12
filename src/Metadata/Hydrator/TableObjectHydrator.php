<?php

namespace Itseasy\Database\Metadata\Hydrator;

use Exception;
use Itseasy\Database\Metadata\Object\MysqlTableObject;
use Itseasy\Database\Metadata\Object\TableObject as ItseasyTableObject;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Laminas\Db\Metadata\Object\ColumnObject;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Metadata\Object\TableObject;
use Laminas\Db\Metadata\Object\ViewObject;
use Laminas\Hydrator\AbstractHydrator;
use Laminas\Hydrator\Strategy\CollectionStrategy;

class TableObjectHydrator extends AbstractHydrator
{
    public function __construct(
        string $column_object = ColumnObject::class,
        string $constraint_object = ConstraintObject::class
    ) {
        $this->addStrategy(
            "columns",
            new CollectionStrategy(
                new ColumnObjectHydrator(),
                $column_object
            )
        );

        $this->addStrategy(
            "constraints",
            new CollectionStrategy(
                new ConstraintObjectHydrator(),
                $constraint_object
            )
        );
    }

    public function hydrate(array $data, object $object)
    {
        $objectClass = get_class($object);
        $objectClass = new $objectClass("");

        if (!$objectClass instanceof AbstractTableObject) {
            throw new Exception("Invalid object, object must extend AbstractTableObject");
        }

        $objectClass->setName($data["name"]);

        if ($objectClass instanceof MysqlTableObject) {
            $objectClass->setCharset($data["charset"]);
            $objectClass->setCollation($data["collation"]);
            $objectClass->setEngine($data["engine"]);
        }

        $objectClass->setColumns($this->hydrateValue("columns", $data["columns"]));
        $objectClass->setConstraints($this->hydrateValue("constraints", $data["constraints"]));

        return $objectClass;
    }

    public function extract(object $object): array
    {
        if (!$object instanceof AbstractTableObject) {
            throw new Exception("Invalid object, object must extend AbstractTableObject");
        }

        $array = [
            "name" => $object->getName(),
        ];

        if ($object instanceof TableObject or $object instanceof ItseasyTableObject) {
            $array["table_type"] = "BASE TABLE";
        } else if ($object instanceof ViewObject) {
            $array["table_type"] = "VIEW";
        } else {
            throw new Exception("Table not supported");
        }

        if ($object instanceof MysqlTableObject) {
            $array["charset"] = $object->getCharset();
            $array["collation"] = $object->getCollation();
            $array["engine"] = $object->getEngine();
        }

        $array["columns"] = $this->extractValue("columns", $object->getColumns());
        $array["constraints"] = $this->extractValue("constraints", $object->getConstraints());

        return $array;
    }
}

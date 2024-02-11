<?php

namespace Itseasy\Database\Sql\Hydrator;

use Itseasy\Database\Sql\Select;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Insert;
use Laminas\Db\Sql\Update;
use Laminas\Hydrator\AbstractHydrator;

class SqlHydrator extends AbstractHydrator
{
    public function hydrate(array $data, object $object)
    {
        $objectClass = null;

        switch (get_class($object)) {
            case Select::class:
                $objectClass = new Select($data["table"]);
                break;
            case Update::class:
                $objectClass = new Update($data["table"]);
                break;
            case Insert::class:
                $objectClass = new Insert($data["table"]);
                break;
            case Delete::class:
                $objectClass = new Delete($data["table"]);
                break;
            default:
                throw new Exception("Invalid object, object must extend AbstractPreparableSql");
        }

        return $objectClass;
    }

    public function extract(object $object): array
    {
        $data = [];

        switch (get_class($object)) {
            case Select::class:
            case Update::class:
            case Insert::class:
            case Delete::class:
                $data = $object->getRawState();
                break;
            default:
                throw new Exception("Invalid object, object must extend AbstractPreparableSql");
        }

        return $data;
    }
}

<?php

namespace Itseasy\Database\Metadata\Hydrator;

use Laminas\Hydrator\ClassMethodsHydrator;

class ColumnObjectHydrator extends ClassMethodsHydrator
{
    public function hydrate(array $data, object $object)
    {
        // When column default is empty string is nullable must be set to true
        // Integer primary key doesn't have is_nullable key skip if not set 
        if (
            isset($data["is_nullable"])
            and !$data["is_nullable"]
            and empty($data["column_default"])
        ) {
            $data["is_nullable"] = true;
            $data["column_default"] = null;
        }

        return parent::hydrate($data, $object);
    }
}

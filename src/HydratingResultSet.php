<?php

namespace Itseasy\Database;

use Laminas\Db\ResultSet\HydratingResultSet as LaminasHydratingResultSet;

class HydratingResultSet extends LaminasHydratingResultSet
{
    /**
     * Iterator: get current item
     * Disable buffer
     *
     * @return object|null
     */
    public function current()
    {
        $data    = $this->dataSource->current();
        $current = is_array($data) ? $this->hydrator->hydrate($data, clone $this->objectPrototype) : null;

        return $current;
    }
}

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
        if ($this->buffer === null) {
            $this->buffer = -2; // implicitly disable buffering from here on
        } elseif (is_array($this->buffer) && isset($this->buffer[$this->position])) {
            return $this->buffer[$this->position];
        }
        $data    = $this->dataSource->current();
        $current = is_array($data) ? $this->hydrator->hydrate($data, new $this->objectPrototype) : null;

        if (is_array($this->buffer)) {
            $this->buffer[$this->position] = $current;
        }

        return $current;
    }
}

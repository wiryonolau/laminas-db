<?php

namespace Itseasy\Database\Metadata\Object;

class PostgresqlIndexObject extends AbstractIndexObject
{
    protected $indexDefinition;

    public function getIndexDefinition()
    {
        return $this->indexDefinition;
    }

    public function setIndexDefinition($indexDefinition)
    {
        $this->indexDefinition = $indexDefinition;
    }
}

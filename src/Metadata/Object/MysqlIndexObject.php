<?php

namespace Itseasy\Database\Metadata\Object;

class MysqlIndexObject extends AbstractIndexObject
{
    protected $columnName;
    protected $indexType;

    public function getColumnName()
    {
        return $this->columnName;
    }

    public function setColumnName($columnName)
    {
        $this->columnName = $columnName;
    }

    public function getIndexType()
    {
        return $this->indexType;
    }

    public function setIndexType($indexType)
    {
        $this->indexType = $indexType;
    }
}

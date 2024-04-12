<?php

namespace Itseasy\Database\Metadata\Object;

use Laminas\Db\Metadata\Object\AbstractTableObject;

class TableObject extends AbstractTableObject
{
    protected $renameColumns = [];

    public function renameColumn(string $column, string $rename): void
    {
        $this->renameColumns[$column] = $rename;
    }

    public function getRenameColumns(): array
    {
        return $this->renameColumns;
    }

    public function getColumnNewName(string $column): ?string
    {
        return $this->renameColumns[$column] ?? null;
    }
}

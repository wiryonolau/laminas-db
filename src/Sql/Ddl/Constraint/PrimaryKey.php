<?php

namespace Itseasy\Database\Sql\Ddl\Constraint;

use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey as ConstraintPrimaryKey;
use Laminas\Db\Sql\Ddl\SqlInterface;

use function array_fill;
use function array_merge;
use function count;
use function implode;
use function sprintf;

class PrimaryKey extends ConstraintPrimaryKey
{
    protected $tableExist = false;

    public function __construct($columns = null, $name = null, bool $table_exist = false)
    {
        parent::__construct($columns, $name);

        $this->tableExist = $table_exist;

        if ($this->tableExist) {
            $this->namedSpecification = '';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getExpressionData()
    {
        $colCount     = count($this->columns);
        $newSpecTypes = [];
        $values       = [];
        $newSpec      = '';

        if ($this->name) {
            $newSpec       .= $this->namedSpecification;

            if (!$this->tableExist) {
                $values[] = $this->name;
            }
            $newSpecTypes[] = self::TYPE_IDENTIFIER;
        }

        $newSpec .= $this->specification;

        if ($colCount) {
            $values       = array_merge($values, $this->columns);
            $newSpecParts = array_fill(0, $colCount, '%s');
            $newSpecTypes = array_merge($newSpecTypes, array_fill(0, $colCount, self::TYPE_IDENTIFIER));
            $newSpec     .= sprintf($this->columnSpecification, implode(', ', $newSpecParts));
        }

        return [
            [
                $newSpec,
                $values,
                $newSpecTypes,
            ],
        ];
    }
}

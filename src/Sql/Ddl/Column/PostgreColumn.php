<?php

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\ColumnInterface;

/**
 * Column Object wrapper for postgre
 * We cannot extend Laminas Column since it was use by a lot of column
 * 
 * Postgre doesn't support CHANGE COLUMN method, we need to use multipler ALTER COLUMN instead
 */
class PostgreColumn implements ColumnInterface
{
    protected $column;
    protected $constraints;

    /**
     * @param array $constraints Array of ConstraintInterface
     */
    public function  __construct(ColumnInterface $column, array $constraints = [])
    {
        $this->column = $column;
        $this->constraints = $constraints;
    }

    public function setName($name)
    {
        return $this->column->setName($name);
    }

    public function getName()
    {
        return $this->column->getName();
    }

    public function setNullable($nullable)
    {
        return $this->column->setNullable($nullable);
    }


    public function isNullable()
    {
        return $this->column->isNullable();
    }

    public function setDefault($default)
    {
        return $this->column->setDefault($default);
    }

    public function getDefault()
    {
        return $this->column->getDefault();
    }

    public function setOptions(array $options)
    {
        return $this->column->setOptions($options);
    }

    public function setOption($name, $value)
    {
        return $this->column->setOption($name, $value);
    }

    public function getOptions()
    {
        return $this->column->getOptions();
    }

    public function getExpressionData()
    {
        $data = $this->column->getExpressionData();
        $type = $data[0][1][1];

        // Retrieve constraint index from Laminas Column, we don't have access to constraint property
        $constraint_index = 0;
        foreach ($data as $index => $param) {
            if (is_string($param)) {
                $constraint_index = $index;
                break;
            }
        }
        $constraints = array_splice($data, 0, $constraint_index);

        $spec = "TYPE %s";

        $params   = [];
        $params[] = $type;

        $types = [self::TYPE_LITERAL];

        if ($this->column->isNullable()) {
            $spec .= ', ALTER COLUMN %s SET NOT NULL';
        } else {
            if (!count($this->constraints)) {
                $spec .= ', ALTER COLUMN %s DROP NOT NULL';
            }
        }
        $params[] = $this->column->getName();
        $types[] = self::TYPE_IDENTIFIER;


        if ($this->column->getDefault() !== null) {
            $spec    .= ', ALTER COLUMN %s SET DEFAULT %s';

            $params[] = $this->column->getName();
            $params[] = $this->column->getDefault();

            $types[] = self::TYPE_IDENTIFIER;
            $types[] = self::TYPE_VALUE;
        }

        $data = [
            [
                $spec,
                $params,
                $types,
            ],
        ];


        if (count($constraints)) {
            $data[] = " ";
            $data = array_merge($data, $constraints);
        }

        return $data;
    }
}

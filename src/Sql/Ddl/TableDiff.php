<?php

namespace Itseasy\Database\Sql\Ddl;

use Exception;
use Laminas\Db\Sql\Ddl;
use Itseasy\Database\Metadata\Source\Factory;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Laminas\Db\Metadata\Object\ColumnObject;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Sql\Ddl\Column\Column;
use Laminas\Db\Sql\Ddl\Column\ColumnInterface;
use Laminas\Db\Sql\Ddl\Constraint\Check;
use Laminas\Db\Sql\Ddl\Constraint\ConstraintInterface;
use Laminas\Db\Sql\Ddl\Constraint\ForeignKey;
use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Db\Sql\Ddl\Constraint\UniqueKey;

class TableDiff
{
    protected $metadata;

    // array of table name
    protected $existingTableNames;

    public function __construct(
        AdapterInterface $adapter,
        ?array $existingTableNames = []
    ) {
        $this->metadata = Factory::createSourceFromAdapter($adapter);

        if (empty($existingTableNames)) {
            $this->existingTableNames = $this->metadata->getTableNames();
        } else {
            $this->existingTableNames = $existingTableNames;
        }
    }

    /**
     * Check if difference exist in given table against existing table
     * 
     * @return Ddl\SqlInterface[] | Ddl\SqlInterface
     */
    public function diff(
        AbstractTableObject $table,
        ?AbstractTableObject $existingTable = null
    ): array {
        $ddls = [];

        if (
            is_null($existingTable)
            and in_array($table->getName(), $this->existingTableNames)
        ) {
            $existingTable = $this->metadata->getTable($table->getName());
        }

        if (!$existingTable) {
            $ddl = new Ddl\CreateTable($table->getName());
            foreach ($table->getColumns() as $column) {
                $ddl->addColumn($column);
            }

            foreach ($table->getConstraints() as $constraint) {
                $ddl->addConstraint($constraint);
            }

            $ddls[] = $ddl;
        }

        if ($existingTable) {
            $existingColumns = [];
            $existingConstraints = [];

            $ddl  = new Ddl\AlterTable($table->getName());
            $hasChange = false;

            # Index existing column by name
            foreach ($existingTable->getColumns() as $column) {
                $existingColumns[$column->getName()] = $column;
            }

            // Index existing constraint
            foreach ($existingTable->getConstraints() as $constraint) {
                $existingConstraints[$constraint->getName()] = $constraint;
            }

            foreach ($table->getColumns() as $column) {
                if (
                    isset($existingColumns[$column->getName()])
                ) {
                    if (!$this->columnHasUpdate(
                        $existingColumns[$column->getName()],
                        $column
                    )) {
                        continue;
                    }

                    $hasChange = true;
                    $ddl->changeColumn(
                        $column->getName(),
                        $this->columnObjectToDdl($column)
                    );
                } else {
                    $hasChange = true;
                    $ddl->addColumn($this->columnObjectToDdl($column));
                }
            }

            foreach ($table->getConstraints() as $constraint) {
                if (isset($existingConstraints[$constraint->getName()])) {
                    if (!$this->constraintHasDiff(
                        $existingConstraints[$constraint->getName()],
                        $constraint
                    )) {
                        continue;
                    }

                    $hasChange = true;
                    $dropDdl  = new Ddl\AlterTable($table->getName());
                    $dropDdl->dropConstraint($constraint->getName());
                    $ddls[] = $dropDdl;
                }
                $hasChange = true;
                $ddl->addConstraint($this->constraintObjectToDdl($constraint));
            }

            if ($hasChange) {
                $ddls[] = $ddl;
            }
        }

        return $ddls;
    }

    /**
     * Compare column differences
     * Each adapter might have unique requirement
     * Each attribute might have unique requirement
     */
    protected function columnHasUpdate(
        ColumnObject $existing,
        ColumnObject $update
    ): bool {
        if ($existing->getColumnDefault() != $update->getColumnDefault()) {
            return true;
        }

        if ($existing->isNullable() !== $update->isNullable()) {
            return true;
        }

        if ($existing->getDataType() !== $update->getDataType()) {
            return true;
        }

        if ($existing->getCharacterMaximumLength() !== $update->getCharacterMaximumLength()) {
            return true;
        }

        if ($existing->getCharacterOctetLength() !== $update->getCharacterOctetLength()) {
            return true;
        }

        if ($existing->getNumericPrecision() !== $update->getNumericPrecision()) {
            return true;
        }

        if ($existing->getNumericScale() !== $update->getNumericScale()) {
            return true;
        }

        if ($existing->getNumericUnsigned() !== $update->getNumericUnsigned()) {
            return true;
        }

        foreach ($existing->getErratas() as $key => $value) {
            if ($update->getErrata($key) !== $value) {
                debug(["errata", $key, $value, $update->getErrata($key)]);
                return true;
            }
        }

        return false;
    }

    protected function constraintHasDiff(
        ConstraintObject $existing,
        ConstraintObject $update
    ): bool {
        if ($existing->getTableName() !== $update->getTableName()) {
            return true;
        }

        if ($existing->getSchemaName() !== $update->getSchemaName()) {
            return true;
        }

        if ($existing->getType() !== $update->getType()) {
            return true;
        }

        $existingColumns = is_null($existing->getColumns()) ? [] : $existing->getColumns();
        $updateColumns = is_null($update->getColumns()) ? [] : $update->getColumns();
        if (count(array_diff($existingColumns, $updateColumns))) {
            return true;
        }

        if ($existing->getReferencedTableSchema() !== $update->getReferencedTableSchema()) {
            return true;
        }

        if ($existing->getReferencedTableName() !== $update->getReferencedTableName()) {
            return true;
        }

        $existingReferenceColumns = is_null($existing->getReferencedColumns()) ? [] : $existing->getReferencedColumns();
        $updateReferencesColumns = is_null($update->getReferencedColumns()) ? [] : $update->getReferencedColumns();
        if (count(array_diff(
            $existingReferenceColumns,
            $updateReferencesColumns
        ))) {
            return true;
        }

        if ($existing->getMatchOption() !== $update->getMatchOption()) {
            return true;
        }

        if ($existing->getUpdateRule() !== $update->getUpdateRule()) {
            return true;
        }

        if ($existing->getDeleteRule() !== $update->getDeleteRule()) {
            return true;
        }

        if ($existing->getCheckClause() !== $update->getCheckClause()) {
            return true;
        }

        return false;
    }

    protected function columnObjectToDdl(
        ColumnObject $columnObject
    ): ColumnInterface {
        $type = ucfirst($columnObject->getDataType());

        $columnNamespace = [
            "Itseasy\Database\Sql\Ddl\Column",
            "Laminas\Db\Sql\Ddl\Column"
        ];


        $object = new Column();
        foreach ($columnNamespace as $namespace) {
            if (class_exists(sprintf("{}\{}", $namespace, $type))) {
                $object = sprintf("{}\{}", $namespace, $type);
                break;
            }
        }

        return new $object(
            $columnObject->getName(),
            $columnObject->isNullable(),
            $columnObject->getColumnDefault(),
            []
        );
    }

    protected function constraintObjectToDdl(
        ConstraintObject $constraintObject
    ): ConstraintInterface {
        switch ($constraintObject->getType()) {
            case "PRIMARY KEY":
                $ddl = new PrimaryKey(
                    $constraintObject->getColumns(),
                    $constraintObject->getName()
                );
                break;
            case "UNIQUE":
                $ddl = new UniqueKey(
                    $constraintObject->getColumns(),
                    $constraintObject->getName()
                );
                break;
            case "FOREIGN KEY":
                $ddl = new ForeignKey(
                    $constraintObject->getName(),
                    $constraintObject->getColumns(),
                    $constraintObject->getReferencedTableName(),
                    $constraintObject->getReferencedColumns(),
                    $constraintObject->getDeleteRule(),
                    $constraintObject->getUpdateRule()
                );
                break;
            case "CHECK":
                // not sure about this
                $ddl = new Check(
                    $constraintObject->getCheckClause(),
                    $constraintObject->getName()
                );
                break;
            default:
                throw new Exception("Invalid constraint type");
        }
        return $ddl;
    }
}

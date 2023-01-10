<?php

namespace Itseasy\Database\Sql\Ddl;

use Exception;
use Itseasy\Database\Sql\Ddl\DdlUtilities;
use Laminas\Db\Sql\Ddl;
use Itseasy\Database\Metadata\Source\Factory;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Laminas\Db\Metadata\Object\ColumnObject;
use Laminas\Db\Metadata\Object\ConstraintObject;

class TableDiff
{
    protected $adapter;
    protected $metadata;
    protected $platformName;

    // array of table name
    protected $existingTableNames;

    public function __construct(
        AdapterInterface $adapter,
        ?array $existingTableNames = []
    ) {
        $this->adapter = $adapter;
        $this->platformName = $this->adapter->getPlatform()->getName();
        $this->metadata = Factory::createSourceFromAdapter($this->adapter);

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

        if (empty($table->getColumns())) {
            throw new Exception("Table require to have at least 1 column define");
        }

        if (
            is_null($existingTable)
            and in_array($table->getName(), $this->existingTableNames)
        ) {
            $existingTable = $this->metadata->getTable($table->getName());
        }

        if (!$existingTable) {
            $ddls[] = DdlUtilities::tableObjectToDdl($table, $this->platformName);
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
                        unset($existingColumns[$column->getName()]);
                        continue;
                    }

                    $hasChange = true;
                    $ddl->changeColumn(
                        $column->getName(),
                        DdlUtilities::columnObjectToDdl($column, $this->platformName)
                    );
                } else {
                    $hasChange = true;
                    $ddl->addColumn(
                        DdlUtilities::columnObjectToDdl($column, $this->platformName)
                    );
                }

                // Remove new column / alter column
                unset($existingColumns[$column->getName()]);
            }

            // Remove column
            // Drop column will automatically drop index related to the column
            foreach (array_keys($existingColumns) as $name) {
                $hasChange = true;
                $ddl->dropColumn($name);
            }

            if (!empty($table->getConstraints())) {
                foreach ($table->getConstraints() as $constraint) {
                    if (isset($existingConstraints[$constraint->getName()])) {
                        if (!$this->constraintHasUpdate(
                            $existingConstraints[$constraint->getName()],
                            $constraint
                        )) {
                            unset($existingConstraints[$constraint->getName()]);
                            continue;
                        }

                        $hasChange = true;
                        $dropDdl  = new Ddl\AlterTable($table->getName());
                        $dropDdl->dropConstraint($constraint->getName());
                        $ddls[] = $dropDdl;
                    }

                    $hasChange = true;
                    $ddl->addConstraint(
                        DdlUtilities::constraintObjectToDdl($constraint, $this->platformName)
                    );

                    unset($existingConstraints[$constraint->getName()]);
                }

                foreach (array_keys($existingConstraints) as $name) {
                    $hasChange = true;
                    $ddl->dropConstraint($name);
                }
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

        // Don't check octet length, not sure what the value means
        // if ($existing->getCharacterOctetLength() !== $update->getCharacterOctetLength()) {
        //     return true;
        // }

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

    protected function constraintHasUpdate(
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
}

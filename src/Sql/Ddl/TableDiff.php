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
     * @param AbstractTableObject   $table                    New Table Definition
     * @param AbstractTableObject   $existingTable            Old table definition, default retrieve from metadata
     * @param bool                  $ignoreConstraintPrefix   Ignore adding table prefix to constraint name
     * 
     * @return Ddl\SqlInterface[] | Ddl\SqlInterface
     */
    public function diff(
        AbstractTableObject $table,
        ?AbstractTableObject $existingTable = null,
        bool $ignoreConstraintPrefix = false
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
                    // Metadata combine table name with constraint name to differentiate constraint type
                    // All existingConstraintName use metadataConstraintName
                    // All ddl use $constraint->getName()
                    $metadataConstraintName = $constraint->getName();
                    if (
                        $constraint->getType() != "FOREIGN KEY"
                        and $ignoreConstraintPrefix == false
                    ) {
                        $metadataConstraintName = $table->getName() . "_" . $constraint->getName();
                    }

                    if (isset($existingConstraints[$metadataConstraintName])) {
                        if (!$this->constraintHasUpdate(
                            $existingConstraints[$metadataConstraintName],
                            $constraint
                        )) {
                            unset($existingConstraints[$metadataConstraintName]);
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

                    unset($existingConstraints[$metadataConstraintName]);
                }

                foreach (array_keys($existingConstraints) as $name) {
                    $hasChange = true;
                    // Metadata combine table name with constraint name to differentiate, strip to remove
                    if ($constraint->getType() == "FOREIGN KEY") {
                        $ddl->dropConstraint($name);
                    } else {
                        $ddl->dropConstraint(preg_replace(sprintf("/^%s_/", $table->getName()), "", $name));
                    }
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
        // Mysql / Mariadb save quote as value, remove it for correct diff
        $existingColumnDefault = (!empty($existing->getColumnDefault()) ? trim($existing->getColumnDefault(), '\'"') : "");
        if (
            !empty($update->getColumnDefault())
            and $existingColumnDefault != $update->getColumnDefault()
        ) {
            return true;
        }

        if ($existing->isNullable() != $update->isNullable()) {
            return true;
        }

        if (
            DdlUtilities::getColumnType($existing, $this->platformName)
            != DdlUtilities::getColumnType($update, $this->platformName)
        ) {
            return true;
        }

        if (
            !empty($update->getCharacterMaximumLength())
            and $existing->getCharacterMaximumLength()
            != $update->getCharacterMaximumLength()
        ) {
            return true;
        }

        // Don't check octet length, not sure what the value means
        // if ($existing->getCharacterOctetLength() !== $update->getCharacterOctetLength()) {
        //     return true;
        // }

        if (
            !empty($update->getNumericPrecision()) and
            $existing->getNumericPrecision() != $update->getNumericPrecision()
        ) {
            return true;
        }

        if (
            !empty($update->getNumericScale()) and
            $existing->getNumericScale() != $update->getNumericScale()
        ) {
            return true;
        }

        if (
            !empty($update->getNumericUnsigned()) and
            $existing->getNumericUnsigned() != $update->getNumericUnsigned()
        ) {
            return true;
        }

        foreach ($existing->getErratas() as $key => $value) {
            if ($update->getErrata($key) != $value) {
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

        // if ($existing->getSchemaName() !== $update->getSchemaName()) {
        //     return true;
        // }

        if ($existing->getType() !== $update->getType()) {
            return true;
        }

        $existingColumns = is_null($existing->getColumns()) ? [] : array_filter($existing->getColumns());
        $updateColumns = is_null($update->getColumns()) ? [] : array_filter($update->getColumns());
        if (count(array_diff($existingColumns, $updateColumns))) {
            return true;
        }

        // if ($existing->getReferencedTableSchema() !== $update->getReferencedTableSchema()) {
        //     return true;
        // }

        if ($existing->getReferencedTableName() !== $update->getReferencedTableName()) {
            return true;
        }

        $existingReferenceColumns = is_null($existing->getReferencedColumns()) ? [] : array_filter($existing->getReferencedColumns());
        $updateReferencesColumns = is_null($update->getReferencedColumns()) ? [] : array_filter($update->getReferencedColumns());
        if (count(array_diff(
            $existingReferenceColumns,
            $updateReferencesColumns
        ))) {
            return true;
        }

        if (
            !empty($update->getMatchOption())
            and $existing->getMatchOption() !== $update->getMatchOption()
        ) {
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

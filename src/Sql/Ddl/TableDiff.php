<?php

namespace Itseasy\Database\Sql\Ddl;

use Exception;
use Itseasy\Database\Sql\Ddl\DdlUtilities;
use Laminas\Db\Sql\Ddl;
use Itseasy\Database\Metadata\Source\Factory;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Metadata\Object\AbstractTableObject;

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
                    if (!DdlUtilities::columnHasUpdate(
                        $existingColumns[$column->getName()],
                        $column,
                        $this->platformName
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
                        if (!DdlUtilities::constraintHasUpdate(
                            $existingConstraints[$metadataConstraintName],
                            $constraint,
                            $this->platformName
                        )) {
                            unset($existingConstraints[$metadataConstraintName]);
                            continue;
                        }

                        $hasChange = true;
                        $dropDdl  = new Ddl\AlterTable($table->getName());
                        $dropDdl = DdlUtilities::dropConstraint(
                            $dropDdl,
                            $table,
                            $constraint
                        );
                        $ddls[] = $dropDdl;
                    }
                    $hasChange = true;
                    $ddl->addConstraint(
                        DdlUtilities::constraintObjectToDdl($constraint, $this->platformName)
                    );

                    unset($existingConstraints[$metadataConstraintName]);
                }

                foreach ($existingConstraints as $existingConstraint) {
                    $hasChange = true;
                    $ddl = DdlUtilities::dropConstraint(
                        $ddl,
                        $table,
                        $existingConstraint
                    );
                }
            }

            if ($hasChange) {
                $ddls[] = $ddl;
            }
        }

        return $ddls;
    }
}

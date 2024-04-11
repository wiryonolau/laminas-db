<?php

namespace Itseasy\Database\Sql\Ddl;

use Exception;
use Itseasy\Database\Sql\Ddl\DdlUtilities;
use Laminas\Db\Sql\Ddl;
use Itseasy\Database\Metadata\Source\Factory;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Composer\Semver\Comparator;

class TableDiff
{
    protected $adapter;
    protected $metadata;
    protected $platformName;
    protected $platformVersion;

    // array of table name
    protected $existingTableNames;

    public function __construct(
        AdapterInterface $adapter,
        ?array $existingTableNames = []
    ) {
        $this->adapter = $adapter;
        $this->platformName = $this->adapter->getPlatform()->getName();
        $this->metadata = Factory::createSourceFromAdapter($this->adapter);
        $this->platformVersion = "0.0.0";

        if (method_exists($this->metadata, "getVersion")) {
            $this->platformVersion = $this->metadata->getVersion();
        }

        if (empty($existingTableNames)) {
            $this->existingTableNames = $this->metadata->getTableNames();
        } else {
            $this->existingTableNames = $existingTableNames;
        }
    }

    /**
     * Check if difference exist in given table against existing table
     * WARNING Doesn't handle column rename, renaming column will drop old column and create a new column
     * 
     * @param AbstractTableObject   $table                    New Table Definition
     * @param AbstractTableObject   $existingTable            Old table definition, default retrieve from metadata
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
        } else {
            $existingColumns = [];
            $existingConstraints = [];

            switch ($this->platformName) {
                case Factory::PLATFORM_POSTGRESQL:
                    $ddl  = new PostgreAlterTable($table->getName());
                    break;
                default:
                    $ddl  = new Ddl\AlterTable($table->getName());
            }

            $hasChange = false;

            # Index existing column by name
            foreach ($existingTable->getColumns() as $column) {
                $existingColumns[$column->getName()] = $column;
            }

            // Index existing constraint
            foreach ($existingTable->getConstraints() as $constraint) {
                // $existingConstraints[$constraint->getName()] = $constraint;
                $constraint = DdlUtilities::filterConstraint(
                    $constraint
                );
                $existingConstraints[$constraint->getName()] = $constraint;
            }

            foreach ($table->getColumns() as $column) {
                if (
                    isset($existingColumns[$column->getName()])
                ) {
                    if (!DdlUtilities::columnHasUpdate(
                        $existingColumns[$column->getName()],
                        $column,
                        $this->platformName,
                        $this->platformVersion
                    )) {
                        unset($existingColumns[$column->getName()]);
                        continue;
                    }

                    // Check related constraints for this column
                    /* @var array $column_constraints */
                    $existingColumnConstraints = array_filter($existingConstraints, function ($c) use ($column) {
                        return in_array($column->getName(), $c->getColumns());
                    });

                    $hasChange = true;
                    switch ($this->platformName) {
                        case Factory::PLATFORM_POSTGRESQL:
                            call_user_func_array(
                                [$ddl, "alterColumn"],
                                [
                                    "name" => $column->getName(),
                                    "column" => DdlUtilities::columnObjectToDdl(
                                        $column,
                                        $this->platformName,
                                        $existingColumns[$column->getName()],
                                        $existingColumnConstraints,
                                    )
                                ]
                            );
                            break;
                        default:
                            call_user_func_array(
                                [$ddl, "changeColumn"],
                                [
                                    "name" => $column->getName(),
                                    "column" => DdlUtilities::columnObjectToDdl(
                                        $column,
                                        $this->platformName,
                                        $existingColumns[$column->getName()],
                                        $existingColumnConstraints,
                                    )
                                ]
                            );
                    }
                } else {
                    $hasChange = true;
                    $ddl->addColumn(
                        DdlUtilities::columnObjectToDdl(
                            $column,
                            $this->platformName,
                            null,
                            [],
                        )
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
                    $constraint = DdlUtilities::filterConstraint(
                        $constraint
                    );

                    if (isset($existingConstraints[$constraint->getName()])) {
                        if (!DdlUtilities::constraintHasUpdate(
                            $existingConstraints[$constraint->getName()],
                            $constraint,
                            $this->platformName,
                            $this->platformVersion
                        )) {
                            unset($existingConstraints[$constraint->getName()]);
                            continue;
                        }

                        $hasChange = true;

                        $ddls[] = DdlUtilities::dropConstraint(
                            $constraint,
                            null,
                            $this->platformName,
                            $this->platformVersion
                        );
                    }

                    $hasChange = true;
                    $ddl->addConstraint(
                        DdlUtilities::constraintObjectToDdl(
                            $constraint,
                            $this->platformName,
                            $existingTable ? true : false
                        )
                    );

                    unset($existingConstraints[$constraint->getName()]);
                }
            }

            // Remove rest of existingConstraints
            foreach ($existingConstraints as $existingConstraint) {
                $hasChange = true;

                $dropConstraint = DdlUtilities::dropConstraint(
                    $existingConstraint,
                    $ddl,
                    $this->platformName,
                    $this->platformVersion
                );

                // returned ddl might be special ddl due to platform version
                if ($dropConstraint instanceof Ddl\AlterTable) {
                    $ddl = $dropConstraint;
                } else {
                    $ddls[] = $dropConstraint;
                }
            }

            if ($hasChange) {
                $ddls[] = $ddl;
            }
        }

        return $ddls;
    }
}

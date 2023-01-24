<?php

namespace Itseasy\Database\Sql\Ddl;

use Exception;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Metadata\Object\ColumnObject;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Metadata\Object\TriggerObject;
use Itseasy\Database\Sql\Ddl\TableDiff;
use Itseasy\Database\Sql\Ddl\TriggerDiff;
use Itseasy\Database\Metadata\Hydrator\ColumnObjectHydrator;
use Itseasy\Database\Metadata\Hydrator\ConstraintObjectHydrator;
use Itseasy\Database\Metadata\Hydrator\TableObjectHydrator;
use Itseasy\Database\Metadata\Object\MysqlTableObject;
use Itseasy\Database\Metadata\Source\Factory;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Laminas\Db\Sql\Sql;
use Laminas\Hydrator\ClassMethodsHydrator;

/**
 * Calculate schema differences by comparing given array of tables and triggers with current metadata
 * 
 * Only diff table and constraint
 */
class SchemaDiff
{
    public static function diff(array $schema, AdapterInterface $adapter): array
    {
        $tableDiff = new TableDiff($adapter);
        $triggerDiff = new TriggerDiff($adapter);
        $sql = new Sql($adapter);

        switch ($adapter->getPlatform()->getName()) {
            case Factory::PLATFORM_MYSQL:
                $tableObject = MysqlTableObject::class;
                break;
            case Factory::PLATFORM_POSTGRESQL:
                $tableObject = AbstractTableObject::class;
                break;
            default:
                $tableObject = AbstractTableObject::class;
        }

        $schema = self::hydrate($schema, $tableObject);
        $ddl_string = [];

        foreach ($schema["tables"] as $table) {
            // Remove empty ddl 
            $ddls = array_filter($tableDiff->diff($table));

            foreach ($ddls as $ddl) {
                $ddl_string[] = $sql->buildSqlString($ddl);
            }
        }

        foreach ($schema["triggers"] as $trigger) {
            // Remove empty ddl 
            $ddls = array_filter($triggerDiff->diff($trigger));

            foreach ($ddls as $ddl) {
                $ddl_string[] = $sql->buildSqlString($ddl);
            }
        }

        foreach ($schema["expressions"] as $expression) {
            $ddl_string[] = $expression;
        }

        return $ddl_string;
    }

    public static function hydrate(
        array $schema,
        string $tableObject
    ) {
        $tableObjectHydrator = new TableObjectHydrator();
        $columnObjectHydrator = new ColumnObjectHydrator();
        $constraintObjectHydrator = new ConstraintObjectHydrator();
        $triggerObjectHydrator = new ClassMethodsHydrator();

        $schema = array_merge(
            ["tables" => [], "triggers" => [], "expressions" => []],
            $schema
        );

        foreach ($schema["tables"] as $index => $table) {
            if (is_array($table)) {
                $table = $tableObjectHydrator->hydrate(
                    $table,
                    new $tableObject($table["name"])
                );
            } else if (is_callable($table)) {
                $table = $table();
            }

            if (!$table instanceof AbstractTableObject) {
                throw new Exception("Invalid Table Object");
            }

            if (!$table->getColumns()) {
                throw new Exception("Table must have minimum 1 column");
            }

            $columns = array_map(function ($column) use (
                $table,
                $columnObjectHydrator
            ) {
                if ($column instanceof ColumnObject) {
                    return $column;
                }

                if (is_array($column)) {
                    return $columnObjectHydrator->hydrate(
                        $column,
                        new ColumnObject(
                            $column["name"],
                            $table->getName()
                        )
                    );
                }

                if (is_callable($column)) {
                    $column = $column();
                    if (!$column instanceof ColumnObject) {
                        throw new Exception("Invalid column object");
                    }

                    return $column;
                }
            }, $table->getColumns());

            $table->setColumns($columns);

            if (!empty($table->getConstraints())) {
                $constraints = array_map(function ($constraint) use (
                    $table,
                    $constraintObjectHydrator
                ) {
                    if ($constraint instanceof ConstraintObject) {
                        return $constraint;
                    }

                    if (is_array($constraint)) {
                        return $constraintObjectHydrator->hydrate(
                            $constraint,
                            new ConstraintObject(
                                $constraint["name"],
                                $table->getName()
                            )
                        );
                    }

                    if (is_callable($constraint)) {
                        $constraint = $constraint();
                        if (!$constraint instanceof ConstraintObject) {
                            throw new Exception("Invalid constraint object");
                        }
                        return $constraint;
                    }
                }, $table->getConstraints());
                $table->setConstraints($constraints);
            }

            $schema["tables"][$index] = $table;
        }


        $schema["triggers"]  = array_map(
            function ($trigger) use ($triggerObjectHydrator) {
                if ($trigger instanceof TriggerObject) {
                    return $trigger;
                }

                if (is_array($trigger)) {
                    return $triggerObjectHydrator->hydrate(
                        $trigger,
                        new TriggerObject()
                    );
                }

                if (is_callable($trigger)) {
                    $trigger = $trigger();
                    if (!$trigger instanceof TriggerObject) {
                        throw new Exception("Invalid trigger object");
                    }
                    return $trigger;
                }
            },
            $schema["triggers"]
        );

        $schema["expressions"] = array_map(
            function ($expression) {
                if (!is_string($expression)) {
                    throw new Exception("Invalid sql expression object");
                }
                return $expression;
            },
            $schema["expressions"]
        );

        /* TODO: Routines */

        return $schema;
    }
}

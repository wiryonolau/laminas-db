<?php

namespace Itseasy\Database\Sql\Ddl;

use Exception;
use Throwable;
use Itseasy\Database\Metadata\Source\Factory;
use Itseasy\Database\Sql\Ddl\Column\MysqlColumnInterface;
use Itseasy\Database\Sql\Ddl\Column\PostgresColumnInterface;
use Itseasy\Database\Sql\Ddl\Column\SqliteColumnInterface;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Laminas\Db\Metadata\Object\ColumnObject;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Sql\Ddl\Column\AbstractLengthColumn;
use Laminas\Db\Sql\Ddl\Column\AbstractPrecisionColumn;
use Laminas\Db\Sql\Ddl\Column\AbstractTimestampColumn;
use Laminas\Db\Sql\Ddl\Column\Column;
use Laminas\Db\Sql\Ddl\Column\ColumnInterface;
use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Constraint\Check;
use Laminas\Db\Sql\Ddl\Constraint\ConstraintInterface;
use Laminas\Db\Sql\Ddl\Constraint\ForeignKey;
use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Db\Sql\Ddl\Constraint\UniqueKey;
use Laminas\Db\Sql\Ddl\CreateTable;
use Laminas\Db\Sql\Ddl\SqlInterface;

class DdlUtilities
{
    /**
     * Mapping MySQL datatype to Ddl Object
     */
    protected static function getMysqlColumnType(
        ColumnObject $columnObject
    ): string {
        $type = strtolower($columnObject->getDataType());
        switch ($type) {
            case "bit":
            case "tinyint":
                return "Boolean";
            case "float":
                return "Floating";
            case "dec":
                return "Decimal";
            case "character":
                return "Char";
            case "tinyblob":
            case "mediumblob":
            case "longblob":
                return "Blob";
            case "int":
                return "Integer";
            case "smallint":
                return "SmallInteger";
            case "bigint":
            case "biginteger":
                return "BigInteger";
            case "json":
                return "Longtext";
            default:
                return ucfirst($type);
        }
    }

    /**
     * Mapping PostgreSQL datatype to Ddl Object
     */
    protected static function getPostgresqlColumnType(
        ColumnObject $columnObject
    ): string {
        $type = strtolower($columnObject->getDataType());
        switch ($type) {
            case "bit":
                return "Bit";
            case "binary":
                return "ByteA";
            case "character":
                return "Char";
            case "character varying":
                return "Varchar";
            case "dec":
                return "Decimal";
            case "double precision":
                return "DoublePrecision";
            case "float":
                return "Real";
            case "longblob":
            case "mediumblob":
            case "tinyblob":
            case "varbinary":
                return "ByteA";
            case "smallint":
                if (boolval($columnObject->getErrata("is_identity"))) {
                    return "SmallIdentity";
                }
                return "SmallInteger";
            case "int":
            case "mediumint":
                return "Integer";
            case "bigint":
            case "biginteger":
                if (boolval($columnObject->getErrata("is_identity"))) {
                    return "BigIdentity";
                }
                return "BigInteger";
            case "int":
            case "integer":
                if (boolval($columnObject->getErrata("is_identity"))) {
                    return "Identity";
                }
                return "Integer";
            case "longtext":
                return "Text";
            default:
                return ucfirst($type);
        }
    }

    protected static function getGenericColumnType(
        ColumnObject $columnObject
    ): string {
        return ucfirst(strtolower($columnObject->getDataType()));
    }

    /**
     * Convert datatype to corresponding ddl
     * Reference : https://www.convert-in.com/mysql-to-postgres-types-mapping.htm
     * 
     * Postgresql serial always save as integer in information_schema can be skipped
     */
    public static function getColumnType(
        ColumnObject $columnObject,
        string $platformName
    ): string {

        $interface = null;
        $type = "Column";

        switch ($platformName) {
            case Factory::PLATFORM_MYSQL:
                $interface = MysqlColumnInterface::class;
                $type = self::getMysqlColumnType($columnObject);
                break;
            case Factory::PLATFORM_POSTGRESQL:
                $interface = PostgresColumnInterface::class;
                $type = self::getPostgresqlColumnType($columnObject);
                break;
            case Factory::PLATFORM_SQLITE:
                $interface = SqliteColumnInterface::class;
                $type = self::getGenericColumnType($columnObject);
                break;
            default:
                $type = self::getGenericColumnType($columnObject);
        }

        // Use Itseasy as first priority Ddl column class if exist
        $columnNamespace = [
            "Itseasy\Database\Sql\Ddl\Column",
            "Laminas\Db\Sql\Ddl\Column"
        ];

        $object = Column::class;
        foreach ($columnNamespace as $namespace) {
            if (class_exists(sprintf("%s\%s", $namespace, $type))) {
                $object = sprintf("%s\%s", $namespace, $type);
                break;
            }
        }

        // Only check if itseasy ddl object
        if (strpos($object, "Itseasy") === 0 and $interface) {
            if (!is_subclass_of($object, $interface)) {
                throw new Exception(
                    sprintf(
                        "Database %s does not support column with %s data type",
                        $platformName,
                        $type
                    )
                );
            }
        }

        return $object;
    }

    public static function tableObjectToDdl(
        AbstractTableObject $table,
        string $platformName
    ): SqlInterface {
        $ddl = new CreateTable($table->getName());

        if (!empty($table->getColumns())) {
            foreach ($table->getColumns() as $column) {
                $ddl->addColumn(self::columnObjectToDdl($column, $platformName));
            }
        }

        if (!empty($table->getConstraints())) {
            foreach ($table->getConstraints() as $constraint) {
                $ddl->addConstraint(
                    self::constraintObjectToDdl($constraint, $platformName)
                );
            }
        }

        return $ddl;
    }

    public static function columnObjectToDdl(
        ColumnObject $columnObject,
        string $platformName
    ): ColumnInterface {
        $object = self::getColumnType($columnObject, $platformName);
        $object = new $object($columnObject->getName());

        if ($object instanceof AbstractLengthColumn) {
            $object->setLength($columnObject->getCharacterMaximumLength());
        }

        if ($object instanceof AbstractPrecisionColumn) {
            $object->setDigits($columnObject->getNumericPrecision());
            $object->setDecimal($columnObject->getNumericScale());
        }

        if ($object instanceof AbstractTimestampColumn) {
        }

        // Default column
        $object->setNullable($columnObject->isNullable());
        $object->setDefault($columnObject->getColumnDefault());

        $options = [];

        if ($columnObject->isNumericUnsigned()) {
            $options["unsigned"] = true;
        }

        // Specific platform 
        switch ($platformName) {
            case Factory::PLATFORM_MYSQL:
                if (!is_null($columnObject->getErrata("auto_increment"))) {
                    $options["auto_increment"] = true;
                }
                break;
            case Factory::PLATFORM_POSTGRESQL:
                if (!is_null($columnObject->getErrata("is_identity"))) {
                    $options["is_identity"] = $columnObject->getErrata("is_identity");
                    $options["identity_generation"] = $columnObject->getErrata("identity_generation");
                    $options["identity_start"] = $columnObject->getErrata("identity_start");
                    $options["identity_increment"] = $columnObject->getErrata("identity_increment");
                    $options["identity_minimum"] = $columnObject->getErrata("identity_minimum");
                    $options["identity_maximum"] = $columnObject->getErrata("identity_maximum");
                    $options["identity_cycle"] = $columnObject->getErrata("identity_cycle");
                }
                break;
            case Factory::PLATFORM_SQLITE:
                break;
            default:
        }

        $object->setOptions($options);

        return $object;
    }

    public static function constraintObjectToDdl(
        ConstraintObject $constraintObject,
        string $platformName
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

    public static function constraintHasUpdate(
        ConstraintObject $existing,
        ConstraintObject $update,
        string $platformName
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
            if (
                $platformName == Factory::PLATFORM_MYSQL
                and $update->getType() == "FOREIGN KEY"
                and in_array($existing->getUpdateRule(), ["NO ACTION", "RESTRICT"])
                and in_array($update->getUpdateRule(), ["NO ACTION", "RESTRICT"])
            ) {
                return false;
            }
            return true;
        }

        if ($existing->getDeleteRule() !== $update->getDeleteRule()) {
            if (
                $platformName == Factory::PLATFORM_MYSQL
                and $update->getType() == "FOREIGN KEY"
                and in_array($existing->getUpdateRule(), ["NO ACTION", "RESTRICT"])
                and in_array($update->getUpdateRule(), ["NO ACTION", "RESTRICT"])
            ) {
                return false;
            }
            return true;
        }

        if ($existing->getCheckClause() !== $update->getCheckClause()) {
            return true;
        }

        return false;
    }

    /**
     * Compare column differences
     * Each adapter might have unique requirement
     * Each attribute might have unique requirement
     */
    public static function columnHasUpdate(
        ColumnObject $existing,
        ColumnObject $update,
        string $platformName
    ): bool {
        // Mysql / Mariadb save quote as value, remove it for correct diff
        if ($platformName == Factory::PLATFORM_MYSQL) {
            $existingColumnDefault = (!empty($existing->getColumnDefault()) ? trim($existing->getColumnDefault(), '\'"') : "");
        }

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
            self::getColumnType($existing, $platformName)
            != self::getColumnType($update, $platformName)
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

    /**
     * For sanitize constraint name
     * laminas db metadata add additional table name 
     * when retrieving constraint name
     */
    public static function dropConstraint(
        SqlInterface $ddl,
        AbstractTableObject $table,
        ConstraintObject $constraint
    ): SqlInterface {
        if (!method_exists($ddl, "dropConstraint")) {
            throw new Exception("Ddl object must implement dropConstraint");
        }

        // Foreign key doesn't have prefix pass constraint name as is
        if ($constraint->getType() == "FOREIGN KEY") {
            $ddl->dropConstraint($constraint->getName());
            return $ddl;
        }

        preg_match_all(
            sprintf("/%s/", $table->getName()),
            $constraint->getName(),
            $matches
        );

        if (empty($matches) or count($matches[0]) == 1) {
            // Probably not prefix added by laminas-db so pass constraint name as is
            $ddl->dropConstraint($constraint->getName());
        } else {
            // Limit only first occurence
            $ddl->dropConstraint(
                preg_replace(
                    sprintf("/^%s_/", $table->getName()),
                    "",
                    $constraint->getName(),
                    1
                )
            );
        }

        return $ddl;
    }
}

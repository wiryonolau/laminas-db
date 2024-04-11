<?php

namespace Itseasy\Database\Sql\Ddl;

use Composer\Semver\Comparator;
use Exception;
use Itseasy\Database\Metadata\Source\Factory;
use Itseasy\Database\Sql\Ddl\Column\Longtext;
use Itseasy\Database\Sql\Ddl\Column\MysqlColumnInterface;
use Itseasy\Database\Sql\Ddl\Column\PostgreColumn;
use Itseasy\Database\Sql\Ddl\Column\PostgreColumnInterface;
use Itseasy\Database\Sql\Ddl\Column\SqliteColumnInterface;
use Itseasy\Database\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Laminas\Db\Metadata\Object\ColumnObject;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\Ddl\Column\AbstractLengthColumn;
use Laminas\Db\Sql\Ddl\Column\AbstractPrecisionColumn;
use Laminas\Db\Sql\Ddl\Column\AbstractTimestampColumn;
use Laminas\Db\Sql\Ddl\Column\Binary;
use Laminas\Db\Sql\Ddl\Column\Char;
use Laminas\Db\Sql\Ddl\Column\Column;
use Laminas\Db\Sql\Ddl\Column\ColumnInterface;
use Laminas\Db\Sql\Ddl\Column\Text;
use Laminas\Db\Sql\Ddl\Column\Varbinary;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Db\Sql\Ddl\Constraint\Check;
use Laminas\Db\Sql\Ddl\Constraint\ConstraintInterface;
use Laminas\Db\Sql\Ddl\Constraint\ForeignKey;
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
                $interface = PostgreColumnInterface::class;
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

        if (!empty($table->getConstraints())) {
            foreach ($table->getConstraints() as $constraint) {
                $ddl->addConstraint(
                    self::constraintObjectToDdl($constraint, $platformName)
                );
            }
        }

        if (!empty($table->getColumns())) {
            foreach ($table->getColumns() as $column) {
                $ddl->addColumn(self::columnObjectToDdl($column, $platformName));
            }
        }



        return $ddl;
    }

    public static function columnObjectToDdl(
        ColumnObject $columnObject,
        string $platformName,
        ?ColumnObject $existingColumnObject = null,
        array $existingColumnConstraints = []
    ): ColumnInterface {
        $object = self::getColumnType($columnObject, $platformName);
        $object = new $object($columnObject->getName());

        if ($object instanceof AbstractLengthColumn) {
            $characterMaximumLength = $columnObject->getCharacterMaximumLength();
            if ($object instanceof Varchar) {
                $characterMaximumLength = min($characterMaximumLength ?? 256, 255);
            } else if ($object instanceof Char) {
                $characterMaximumLength = min($characterMaximumLength ?? 256, 255);
            } else if ($object instanceof Text) {
                $characterMaximumLength = null;
            } else if ($object instanceof Longtext) {
                $characterMaximumLength = null;
            } else if ($object instanceof Binary) {
                $characterMaximumLength = min($characterMaximumLength ?? 256, 255);
            } else if ($object instanceof Varbinary) {
                $characterMaximumLength = min($characterMaximumLength ?? 65536, 65535);
            }

            $object->setLength($characterMaximumLength);
        }

        if ($object instanceof AbstractPrecisionColumn) {
            $object->setDigits($columnObject->getNumericPrecision() ?? 10);
            $object->setDecimal($columnObject->getNumericScale() ?? 0);
        }

        if ($object instanceof AbstractTimestampColumn) {
        }

        // Default column
        $object->setNullable($columnObject->isNullable() ?? false);
        $object->setDefault($columnObject->getColumnDefault());

        $options = [];

        if ($columnObject->isNumericUnsigned()) {
            $options["unsigned"] = true;
        }

        // Specific platform 
        switch ($platformName) {
            case Factory::PLATFORM_MYSQL:
                if (!is_null($columnObject->getErrata("auto_increment"))) {
                    $options["auto_increment"] = $columnObject->getErrata("auto_increment");
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

                    $remove_identity = false;
                    if ($existingColumnObject and $existingColumnObject->getErrata("is_identity")) {
                        $remove_identity = true;
                    }
                }


                $object = new PostgreColumn(
                    $object,
                    $remove_identity,
                    $existingColumnConstraints
                );
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
        string $platformName,
        bool $tableExist = false
    ): ConstraintInterface {
        $constraintObject = self::filterConstraint(
            $constraintObject
        );

        switch ($constraintObject->getType()) {
            case "PRIMARY KEY":
                // Primary constraint name always primary
                // Different sql for existing table
                $ddl = new PrimaryKey(
                    $constraintObject->getColumns(),
                    $constraintObject->getName(),
                    $tableExist
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
        string $platformName,
        string $platformVersion = "0.0.0",
        bool $checkSchema = false
    ): bool {
        if ($existing->getTableName() !== $update->getTableName()) {
            return true;
        }

        if (
            $checkSchema
            and $existing->getSchemaName() !== $update->getSchemaName()
        ) {
            return true;
        }

        if ($existing->getType() !== $update->getType()) {
            return true;
        }

        if (!self::arrayIsEqual($existing->getColumns(), $update->getColumns())) {
            return true;
        }

        if (
            $checkSchema
            and $existing->getReferencedTableSchema() !== $update->getReferencedTableSchema()
        ) {
            return true;
        }

        if ($existing->getReferencedTableName() !== $update->getReferencedTableName()) {
            return true;
        }

        if (!self::arrayIsEqual($existing->getReferencedColumns(), $update->getReferencedColumns())) {
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
        string $platformName,
        string $platformVersion = "0.0.0"
    ): bool {
        // Mysql / Mariadb save quote as value, remove it for correct diff
        $existingColumnDefault = $existing->getColumnDefault();
        if ($platformName == Factory::PLATFORM_MYSQL) {
            $existingColumnDefault = (!empty($existingColumnDefault) ? trim($existingColumnDefault, '\'"') : "");
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

    public static function dropConstraint(
        ConstraintObject $constraint,
        ?SqlInterface $ddl = null,
        ?string $platformName = null,
        string $platformVersion = "0.0.0"
    ): SqlInterface {
        if (is_null($ddl)) {
            $ddl = new AlterTable($constraint->getTableName());
        }

        if (!method_exists($ddl, "dropConstraint")) {
            throw new Exception("Ddl object must implement dropConstraint");
        }

        // UNIQUE, INDEX, CHECK constraint
        $constraint = self::filterConstraint($constraint);

        // Foreign key doesn't have prefix pass constraint name as is
        if ($constraint->getType() == "FOREIGN KEY") {
            if (
                $platformName == Factory::PLATFORM_MYSQL
                and Comparator::lessThan($platformVersion, "8")
            ) {
                $ddl = new DropForeignKey(
                    $constraint->getTableName(),
                    $constraint->getName()
                );
            } else {
                $ddl->dropConstraint($constraint->getName());
            }
            return $ddl;
        }

        // Primary key constraint name always PRIMARY
        if ($constraint->getType() == "PRIMARY KEY") {
            if (
                $platformName == Factory::PLATFORM_MYSQL
            ) {
                $ddl = new DropPrimaryKey(
                    $constraint->getTableName()
                );
            } else {
                $ddl->dropConstraint("PRIMARY");
            }
            return $ddl;
        }

        if (
            $platformName == Factory::PLATFORM_MYSQL
            and Comparator::lessThan($platformVersion, "8")
        ) {
            $ddl = new DropIndex(
                $constraint->getTableName(),
                $constraint->getName()
            );
        } else {
            $ddl->dropConstraint($constraint->getName());
        }

        return $ddl;
    }

    /**
     * Apply filter to constraint object
     * - Remove table prefix from constraint name
     */
    public static function filterConstraint(
        ConstraintObject $constraint
    ): ConstraintObject {
        if ($constraint->getType() == "FOREIGN KEY") {
            return $constraint;
        }

        if ($constraint->getType() == "PRIMARY KEY") {
            $constraint->setName("PRIMARY");
            return $constraint;
        }

        preg_match_all(
            sprintf("/%s_/", $constraint->getTableName()),
            $constraint->getName(),
            $matches
        );

        if (empty($matches) or count($matches[0]) == 1) {
            // Probably not prefix added by laminas-db so pass constraint name as is
            return $constraint;
        }

        // Remove first prefix occurence
        $constraint->setName(preg_replace(
            sprintf("/^%s_/", $constraint->getTableName()),
            "",
            $constraint->getName(),
            1
        ));

        return $constraint;
    }

    public static function arrayIsEqual(?array $a, ?array $b): bool
    {
        $a = $a ?? [];
        $b = $b ?? [];

        return (is_array($a)
            and is_array($b)
            and count($a) == count($b)
            and array_diff($a, $b) === array_diff($b, $a)
        );
    }
}

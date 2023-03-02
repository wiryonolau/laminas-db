<?php

namespace Itseasy\Database\Metadata\Source;

use Exception;
use Itseasy\Database\Metadata\Object\MysqlIndexObject;
use Itseasy\Database\Metadata\Object\MysqlTableObject;
use Itseasy\Database\Metadata\Object\RoutineObject;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\Object\ViewObject;
use Laminas\Db\Metadata\Source\MysqlMetadata as LaminasMysqlMetadata;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Composer\Semver\Comparator;
use Throwable;

class MysqlMetadata extends LaminasMysqlMetadata
{
    protected $version;

    public function  __construct(Adapter $adapter)
    {
        parent::__construct($adapter);
        try {
            $result = $this->adapter->query(
                "SELECT VERSION() AS VERSION",
                Adapter::QUERY_MODE_EXECUTE
            );
            $this->version = $result->toArray()[0]["VERSION"];
        } catch (Throwable $t) {
            $this->version = "0.0.0";
        }
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getMetadata(): array
    {
        return [
            "tables" => $this->getTables(null, false),
            "views" => $this->getViews(),
            "triggers" => $this->getTriggers(),
            "indexes" => $this->getIndexes(),
            "routines" => $this->getRoutines()
        ];
    }

    protected function loadTableNameData($schema): void
    {
        if (isset($this->data['table_names'][$schema])) {
            return;
        }
        $this->prepareDataHierarchy('table_names', $schema);

        $p = $this->adapter->getPlatform();

        $isColumns = [
            ['T', 'TABLE_NAME'],
            ['T', 'TABLE_TYPE'],
            ['T', 'TABLE_COLLATION'],
            ['T', 'ENGINE'],
            ['V', 'VIEW_DEFINITION'],
            ['V', 'CHECK_OPTION'],
            ['V', 'IS_UPDATABLE'],
        ];

        array_walk($isColumns, function (&$c) use ($p) {
            $c = $p->quoteIdentifierChain($c);
        });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'TABLES']) . 'T'

            . ' LEFT JOIN ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'VIEWS']) . ' V'
            . ' ON ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
            . '  = ' . $p->quoteIdentifierChain(['V', 'TABLE_SCHEMA'])
            . ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_NAME'])
            . '  = ' . $p->quoteIdentifierChain(['V', 'TABLE_NAME'])

            . ' WHERE ' . $p->quoteIdentifierChain(['T', 'TABLE_TYPE'])
            . ' IN (\'BASE TABLE\', \'VIEW\')';

        if ($schema != self::DEFAULT_SCHEMA) {
            $sql .= ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
                . ' = ' . $p->quoteTrustedValue($schema);
        } else {
            $sql .= ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
                . ' != \'INFORMATION_SCHEMA\'';
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $tables = [];
        foreach ($results->toArray() as $row) {
            $tables[$row['TABLE_NAME']] = [
                'table_type' => $row['TABLE_TYPE'],
                'table_collation' => $row['TABLE_COLLATION'],
                'engine' => $row['ENGINE'],
                'view_definition' => $row['VIEW_DEFINITION'],
                'check_option' => $row['CHECK_OPTION'],
                'is_updatable' => ('YES' == $row['IS_UPDATABLE']),
            ];
        }

        $this->data['table_names'][$schema] = $tables;
    }

    protected function getCharset(string $collation): string
    {
        $p = $this->adapter->getPlatform();

        $sql = 'SELECT CHARACTER_SET_NAME'
            . ' FROM ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'COLLATION_CHARACTER_SET_APPLICABILITY']) . 'T'
            . ' WHERE ' . $p->quoteIdentifierChain(['T', 'COLLATION_NAME'])
            . ' = \'' . $collation . '\'';

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        foreach ($results->toArray() as $row) {
            return $row["CHARACTER_SET_NAME"];
        }
    }

    /**
     * @throw Exception
     */
    public function getTable($tableName, $schema = null): AbstractTableObject
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $this->loadTableNameData($schema);

        if (!isset($this->data['table_names'][$schema][$tableName])) {
            throw new Exception('Table "' . $tableName . '" does not exist');
        }

        $data = $this->data['table_names'][$schema][$tableName];
        switch ($data['table_type']) {
            case 'BASE TABLE':
                $table = new MysqlTableObject($tableName);
                $table->setEngine($data['engine']);
                $table->setCollation($data['table_collation']);
                $table->setCharset($this->getCharset($data['table_collation']));
                break;
            case 'VIEW':
                $table = new ViewObject($tableName);
                $table->setViewDefinition($data['view_definition']);
                $table->setCheckOption($data['check_option']);
                $table->setIsUpdatable($data['is_updatable']);
                break;
            default:
                throw new Exception(
                    'Table "' . $tableName . '" is of an unsupported type "' . $data['table_type'] . '"'
                );
        }
        $table->setColumns($this->getColumns($tableName, $schema));
        $table->setConstraints($this->getConstraints($tableName, $schema));
        return $table;
    }

    protected function loadColumnData($table, $schema)
    {
        if (isset($this->data['columns'][$schema][$table])) {
            return;
        }
        $this->prepareDataHierarchy('columns', $schema, $table);
        $p = $this->adapter->getPlatform();

        $isColumns = [
            ['C', 'ORDINAL_POSITION'],
            ['C', 'COLUMN_DEFAULT'],
            ['C', 'IS_NULLABLE'],
            ['C', 'DATA_TYPE'],
            ['C', 'CHARACTER_MAXIMUM_LENGTH'],
            ['C', 'CHARACTER_OCTET_LENGTH'],
            ['C', 'NUMERIC_PRECISION'],
            ['C', 'NUMERIC_SCALE'],
            ['C', 'COLUMN_NAME'],
            ['C', 'COLUMN_TYPE'],
            ['C', 'EXTRA']
        ];

        array_walk($isColumns, function (&$c) use ($p) {
            $c = $p->quoteIdentifierChain($c);
        });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'TABLES']) . 'T'
            . ' INNER JOIN ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'COLUMNS']) . 'C'
            . ' ON ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
            . '  = ' . $p->quoteIdentifierChain(['C', 'TABLE_SCHEMA'])
            . ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_NAME'])
            . '  = ' . $p->quoteIdentifierChain(['C', 'TABLE_NAME'])
            . ' WHERE ' . $p->quoteIdentifierChain(['T', 'TABLE_TYPE'])
            . ' IN (\'BASE TABLE\', \'VIEW\')'
            . ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_NAME'])
            . '  = ' . $p->quoteTrustedValue($table);

        if ($schema !== self::DEFAULT_SCHEMA) {
            $sql .= ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
                . ' = ' . $p->quoteTrustedValue($schema);
        } else {
            $sql .= ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
                . ' != \'INFORMATION_SCHEMA\'';
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $columns = [];
        foreach ($results->toArray() as $row) {
            $erratas = [];
            $matches = [];
            if (preg_match('/^(?:enum|set)\((.+)\)$/i', $row['COLUMN_TYPE'], $matches)) {
                $permittedValues = $matches[1];
                if (
                    preg_match_all(
                        "/\\s*'((?:[^']++|'')*+)'\\s*(?:,|\$)/",
                        $permittedValues,
                        $matches,
                        PREG_PATTERN_ORDER
                    )
                ) {
                    $permittedValues = str_replace("''", "'", $matches[1]);
                } else {
                    $permittedValues = [$permittedValues];
                }
                $erratas['permitted_values'] = $permittedValues;
            }

            $erratas['auto_increment'] = (strpos($row['EXTRA'], "auto_increment") === false ? false : true);

            $columns[$row['COLUMN_NAME']] = [
                'ordinal_position'         => $row['ORDINAL_POSITION'],
                'column_default'           => $row['COLUMN_DEFAULT'],
                'is_nullable'              => 'YES' === $row['IS_NULLABLE'],
                'data_type'                => $row['DATA_TYPE'],
                'character_maximum_length' => $row['CHARACTER_MAXIMUM_LENGTH'],
                'character_octet_length'   => $row['CHARACTER_OCTET_LENGTH'],
                'numeric_precision'        => $row['NUMERIC_PRECISION'],
                'numeric_scale'            => $row['NUMERIC_SCALE'],
                'numeric_unsigned'         => false !== strpos($row['COLUMN_TYPE'], 'unsigned'),
                'erratas'                  => $erratas,
            ];
        }

        $this->data['columns'][$schema][$table] = $columns;
    }

    protected function loadConstraintData($table, $schema)
    {
        // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps
        if (isset($this->data['constraints'][$schema][$table])) {
            return;
        }

        $this->prepareDataHierarchy('constraints', $schema, $table);

        $isColumns = [
            ['T', 'TABLE_NAME'],
            ['TC', 'CONSTRAINT_NAME'],
            ['TC', 'CONSTRAINT_TYPE'],
            ['KCU', 'COLUMN_NAME'],
            ['RC', 'MATCH_OPTION'],
            ['RC', 'UPDATE_RULE'],
            ['RC', 'DELETE_RULE'],
            ['KCU', 'REFERENCED_TABLE_SCHEMA'],
            ['KCU', 'REFERENCED_TABLE_NAME'],
            ['KCU', 'REFERENCED_COLUMN_NAME'],
        ];

        // For mysql 8 and mariadb 10
        if (Comparator::greaterThanOrEqualTo($this->version, "8")) {
            $isColumns[] = ['CC', 'CHECK_CLAUSE'];
        }

        $p = $this->adapter->getPlatform();

        array_walk($isColumns, function (&$c) use ($p) {
            $c = $p->quoteIdentifierChain($c);
        });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'TABLES']) . ' T'

            . ' INNER JOIN ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'TABLE_CONSTRAINTS']) . ' TC'
            . ' ON ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
            . '  = ' . $p->quoteIdentifierChain(['TC', 'TABLE_SCHEMA'])
            . ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_NAME'])
            . '  = ' . $p->quoteIdentifierChain(['TC', 'TABLE_NAME'])

            . ' LEFT JOIN ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'KEY_COLUMN_USAGE']) . ' KCU'
            . ' ON ' . $p->quoteIdentifierChain(['TC', 'TABLE_SCHEMA'])
            . '  = ' . $p->quoteIdentifierChain(['KCU', 'TABLE_SCHEMA'])
            . ' AND ' . $p->quoteIdentifierChain(['TC', 'TABLE_NAME'])
            . '  = ' . $p->quoteIdentifierChain(['KCU', 'TABLE_NAME'])
            . ' AND ' . $p->quoteIdentifierChain(['TC', 'CONSTRAINT_NAME'])
            . '  = ' . $p->quoteIdentifierChain(['KCU', 'CONSTRAINT_NAME'])

            . ' LEFT JOIN ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'REFERENTIAL_CONSTRAINTS']) . ' RC'
            . ' ON ' . $p->quoteIdentifierChain(['TC', 'CONSTRAINT_SCHEMA'])
            . '  = ' . $p->quoteIdentifierChain(['RC', 'CONSTRAINT_SCHEMA'])
            . ' AND ' . $p->quoteIdentifierChain(['TC', 'CONSTRAINT_NAME'])
            . '  = ' . $p->quoteIdentifierChain(['RC', 'CONSTRAINT_NAME']);

        // For mysql 8 and mariadb 10
        if (Comparator::greaterThanOrEqualTo($this->version, "8")) {
            $sql .= ' LEFT JOIN ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'CHECK_CONSTRAINTS']) . ' CC'
                . ' ON ' . $p->quoteIdentifierChain(['TC', 'CONSTRAINT_SCHEMA'])
                . '  = ' . $p->quoteIdentifierChain(['CC', 'CONSTRAINT_SCHEMA'])
                . ' AND ' . $p->quoteIdentifierChain(['TC', 'CONSTRAINT_NAME'])
                . '  = ' . $p->quoteIdentifierChain(['CC', 'CONSTRAINT_NAME']);
        }

        $sql .= ' WHERE ' . $p->quoteIdentifierChain(['T', 'TABLE_NAME'])
            . ' = ' . $p->quoteTrustedValue($table)
            . ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_TYPE'])
            . ' IN (\'BASE TABLE\', \'VIEW\')';

        // For mariadb only ( start at 10 )
        if (Comparator::greaterThanOrEqualTo($this->version, "10")) {
            $sql .= ' AND (' . $p->quoteIdentifierChain(['CC', 'LEVEL'])
                . ' IS NULL OR ' . $p->quoteIdentifierChain(['CC', 'LEVEL']) . ' = \'Table\')';
        }

        if ($schema !== self::DEFAULT_SCHEMA) {
            $sql .= ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
                . ' = ' . $p->quoteTrustedValue($schema);
        } else {
            $sql .= ' AND ' . $p->quoteIdentifierChain(['T', 'TABLE_SCHEMA'])
                . ' != \'INFORMATION_SCHEMA\'';
        }

        $sql .= ' ORDER BY CASE ' . $p->quoteIdentifierChain(['TC', 'CONSTRAINT_TYPE'])
            . " WHEN 'PRIMARY KEY' THEN 1"
            . " WHEN 'UNIQUE' THEN 2"
            . " WHEN 'FOREIGN KEY' THEN 3"
            . " ELSE 4 END"

            . ', ' . $p->quoteIdentifierChain(['TC', 'CONSTRAINT_NAME'])
            . ', ' . $p->quoteIdentifierChain(['KCU', 'ORDINAL_POSITION']);


        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $realName    = null;
        $constraints = [];
        foreach ($results->toArray() as $row) {
            if ($row['CONSTRAINT_NAME'] !== $realName) {
                $realName = $row['CONSTRAINT_NAME'];
                $isFK     = 'FOREIGN KEY' === $row['CONSTRAINT_TYPE'];
                if ($isFK) {
                    $name = $realName;
                } else {
                    $name = $row['TABLE_NAME'] . '_' . $realName;
                }
                $constraints[$name] = [
                    'constraint_name' => $name,
                    'constraint_type' => $row['CONSTRAINT_TYPE'],
                    'table_name'      => $row['TABLE_NAME'],
                    'columns'         => [],
                    'check_clause'    => isset($row["CHECK_CLAUSE"]) ? $row["CHECK_CLAUSE"] : null
                ];
                if ($isFK) {
                    $constraints[$name]['referenced_table_schema'] = $row['REFERENCED_TABLE_SCHEMA'];
                    $constraints[$name]['referenced_table_name']   = $row['REFERENCED_TABLE_NAME'];
                    $constraints[$name]['referenced_columns']      = [];
                    $constraints[$name]['match_option']            = $row['MATCH_OPTION'];
                    $constraints[$name]['update_rule']             = $row['UPDATE_RULE'];
                    $constraints[$name]['delete_rule']             = $row['DELETE_RULE'];
                }
            }
            $constraints[$name]['columns'][] = $row['COLUMN_NAME'];
            if ($isFK) {
                $constraints[$name]['referenced_columns'][] = $row['REFERENCED_COLUMN_NAME'];
            }
        }

        $this->data['constraints'][$schema][$table] = $constraints;
        // phpcs:enable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps
    }

    public function getRoutineNames($schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $this->loadRoutinesData($schema);

        return array_keys($this->data['routines'][$schema]);
    }

    public function getRoutines($schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $sequences = [];
        foreach ($this->getRoutineNames($schema) as $sequenceName) {
            $sequences[] = $this->getRoutine($sequenceName, $schema);
        }
        return $sequences;
    }

    public function getRoutine($routineName, $schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $this->loadRoutinesData($schema);

        if (!isset($this->data['routines'][$schema][$routineName])) {
            throw new Exception('Routine "' . $routineName . '" does not exist');
        }

        $info = $this->data['routines'][$schema][$routineName];

        $routine = new RoutineObject();

        $routine->setName($routineName);
        $routine->setType($info["routine_type"]);
        $routine->setDataType($info["data_type"]);
        $routine->setBody($info['routine_body']);
        $routine->setDefinition($info['routine_definition']);
        $routine->setExternalName($info['external_name']);
        $routine->setExternalLanguage($info['external_language']);

        return $routine;
    }

    protected function loadRoutinesData($schema)
    {
        if (isset($this->data['routines'][$schema])) {
            return;
        }

        $this->prepareDataHierarchy('routines', $schema);

        $p = $this->adapter->getPlatform();

        $isColumns = [
            'routine_name',
            'routine_type',
            'routine_body',
            'routine_definition',
            'data_type',
            'external_name',
            'external_language'
        ];

        array_walk($isColumns, function (&$c) use ($p) {
            if (is_array($c)) {
                $alias = key($c);
                $c     = $p->quoteIdentifierChain($c);
                if (is_string($alias)) {
                    $c .= ' ' . $p->quoteIdentifier($alias);
                }
            } else {
                $c = $p->quoteIdentifier($c);
            }
        });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $p->quoteIdentifierChain(['information_schema', 'routines'])
            . ' WHERE ';

        if ($schema !== self::DEFAULT_SCHEMA) {
            $sql .= $p->quoteIdentifier('routine_schema')
                . ' = ' . $p->quoteTrustedValue($schema);
        } else {
            $sql .= $p->quoteIdentifier('routine_schema')
                . ' != \'information_schema\'';
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $data = [];
        foreach ($results->toArray() as $row) {
            $row                             = array_change_key_case($row, CASE_LOWER);
            $data[$row['routine_name']] = $row;
        }

        $this->data['routines'][$schema] = $data;
    }

    public function getIndexNames($schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $this->loadIndexData($schema);

        return array_keys($this->data['indexes'][$schema]);
    }

    public function getIndexes($schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $indexes = [];
        foreach ($this->getIndexNames($schema) as $sequenceName) {
            $indexes[] = $this->getIndex($sequenceName, $schema);
        }
        return $indexes;
    }

    public function getIndex($indexName, $schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $this->loadRoutinesData($schema);

        if (!isset($this->data['indexes'][$schema][$indexName])) {
            throw new Exception('Index "' . $indexName . '" does not exist');
        }

        $info = $this->data['indexes'][$schema][$indexName];

        $index = new MysqlIndexObject();

        $index->setName($indexName);
        $index->setTableName($info["table_name"]);
        $index->setColumnName($info["column_name"]);
        $index->setIndexType($info["index_type"]);

        return $index;
    }

    protected function loadIndexData($schema)
    {
        if (isset($this->data['indexes'][$schema])) {
            return;
        }

        $this->prepareDataHierarchy('indexes', $schema);

        $p = $this->adapter->getPlatform();

        $isColumns = [
            'index_name',
            'table_name',
            'column_name',
            'index_type'
        ];

        array_walk($isColumns, function (&$c) use ($p) {
            if (is_array($c)) {
                $alias = key($c);
                $c     = $p->quoteIdentifierChain($c);
                if (is_string($alias)) {
                    $c .= ' ' . $p->quoteIdentifier($alias);
                }
            } else {
                $c = $p->quoteIdentifier($c);
            }
        });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $p->quoteIdentifierChain(['information_schema', 'statistics'])
            . ' WHERE ';

        if ($schema !== self::DEFAULT_SCHEMA) {
            $sql .= $p->quoteIdentifier('index_schema')
                . ' = ' . $p->quoteTrustedValue($schema);
        } else {
            $sql .= $p->quoteIdentifier('index_schema')
                . ' != \'information_schema\'';
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $data = [];
        foreach ($results->toArray() as $row) {
            $row                             = array_change_key_case($row, CASE_LOWER);
            $data[$row['index_name']] = $row;
        }

        $this->data['indexes'][$schema] = $data;
    }
}

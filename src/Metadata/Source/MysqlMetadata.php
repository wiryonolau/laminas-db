<?php

namespace Itseasy\Database\Metadata\Source;

use Exception;
use Itseasy\Database\Metadata\Object\MysqlTableObject;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\Object\ViewObject;
use Laminas\Db\Metadata\Source\MysqlMetadata as LaminasMysqlMetadata;

class MysqlMetadata extends LaminasMysqlMetadata
{
    protected function loadTableNameData($schema)
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

    protected function getCharset(string $collation) : string {
        $p = $this->adapter->getPlatform();

        $sql = 'SELECT CHARACTER_SET_NAME'
             . ' FROM ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'COLLATION_CHARACTER_SET_APPLICABILITY']) . 'T'
             . ' WHERE ' . $p->quoteIdentifierChain(['T', 'COLLATION_NAME'])
             . ' = \''.$collation.'\'';

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        foreach ($results->toArray() as $row) {
            return $row["CHARACTER_SET_NAME"];
        }
    }

    public function getTable($tableName, $schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $this->loadTableNameData($schema);

        if (! isset($this->data['table_names'][$schema][$tableName])) {
            throw new Exception('Table "' . $tableName . '" does not exist');
        }

        $data = $this->data['table_names'][$schema][$tableName];
        switch ($data['table_type']) {
            case 'BASE TABLE':
                $table = new MysqlTableObject($tableName);
                $table->setEngine($data['engine']);
                $table->setCollation($data['table_collation']);
                $table->setCharset($this->getCharset($data['table_collation']));
                $table->setType($data['table_type']);
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

    protected function loadSchemaData()
    {
        if (isset($this->data['schemas'])) {
            return;
        }
        $this->prepareDataHierarchy('schemas');

        $p = $this->adapter->getPlatform();

        $sql = 'SELECT *'
             . ' FROM ' . $p->quoteIdentifierChain(['INFORMATION_SCHEMA', 'SCHEMATA'])
             . ' WHERE ' . $p->quoteIdentifier('SCHEMA_NAME')
             . ' != \'INFORMATION_SCHEMA\'';

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $schemas = [];
        foreach ($results->toArray() as $row) {
            $schemas[] = $row;
        }

        $this->data['schemas'] = $schemas;
    }
}

<?php

namespace Itseasy\Database\Metadata\Source;

use Exception;
use Itseasy\Database\Metadata\Object\MysqlTableObject;
use Itseasy\Database\Metadata\Object\SequenceObject;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\Object\ViewObject;
use Laminas\Db\Metadata\Object\AbstractTableObject;
use Laminas\Db\Metadata\Source\PostgresqlMetadata as SourcePostgresqlMetadata;

class PostgresqlMetadata extends SourcePostgresqlMetadata
{
    public function getSequenceNames($schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $this->loadSequenceData($schema);

        return array_keys($this->data['sequences'][$schema]);
    }

    public function getSequences($schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $sequences = [];
        foreach ($this->getSequenceNames($schema) as $sequenceName) {
            $sequences[] = $this->getSequence($sequenceName, $schema);
        }
        return $sequences;
    }

    public function getSequence($sequenceName, $schema = null)
    {
        if ($schema === null) {
            $schema = $this->defaultSchema;
        }

        $this->loadSequenceData($schema);

        if (!isset($this->data['sequences'][$schema][$sequenceName])) {
            throw new Exception('Sequence "' . $sequenceName . '" does not exist');
        }

        $info = $this->data['sequences'][$schema][$sequenceName];

        $sequence = new SequenceObject();

        $sequence->setName($sequenceName);
        $sequence->setDataType($info['data_type']);
        $sequence->setNumericPrecision($info['numeric_precision']);
        $sequence->setNumericPrecisionRadix($info['numeric_precision_radix']);
        $sequence->setNumericScale($info['numeric_scale']);
        $sequence->setStartValue($info['start_value']);
        $sequence->setMinimumValue($info['minimum_value']);
        $sequence->setMaximumValue($info['maximum_value']);
        $sequence->setIncrement($info['increment']);
        $sequence->setCycleOption($info['cycle_option']);

        return $sequence;
    }

    protected function loadColumnData($table, $schema)
    {
        if (isset($this->data['columns'][$schema][$table])) {
            return;
        }

        $this->prepareDataHierarchy('columns', $schema, $table);

        $platform = $this->adapter->getPlatform();

        $isColumns = [
            'table_name',
            'column_name',
            'ordinal_position',
            'column_default',
            'is_nullable',
            'data_type',
            'character_maximum_length',
            'character_octet_length',
            'numeric_precision',
            'numeric_scale',
        ];

        array_walk($isColumns, function (&$c) use ($platform) {
            $c = $platform->quoteIdentifier($c);
        });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $platform->quoteIdentifier('information_schema')
            . $platform->getIdentifierSeparator() . $platform->quoteIdentifier('columns')
            . ' WHERE ' . $platform->quoteIdentifier('table_schema')
            . ' != \'information\''
            . ' AND ' . $platform->quoteIdentifier('table_name')
            . ' = ' . $platform->quoteTrustedValue($table);

        if ($schema !== '__DEFAULT_SCHEMA__') {
            $sql .= ' AND ' . $platform->quoteIdentifier('table_schema')
                . ' = ' . $platform->quoteTrustedValue($schema);
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $columns = [];
        foreach ($results->toArray() as $row) {
            // nextval('user_id_seq'::regclass)
            $erratas = [];

            $column_default = is_null($row["column_default"]) ? "" : $row["column_default"];
            preg_match("/nextval\('([a-z_]+)'.*\).*/", $column_default, $matches);
            if (count($matches)) {
                $erratas['auto_increment'] = true;
                $erratas['sequence'] = $matches[1];
            }

            $columns[$row['column_name']] = [
                'ordinal_position'         => $row['ordinal_position'],
                'column_default'           => $row['column_default'],
                'is_nullable'              => 'YES' === $row['is_nullable'],
                'data_type'                => $row['data_type'],
                'character_maximum_length' => $row['character_maximum_length'],
                'character_octet_length'   => $row['character_octet_length'],
                'numeric_precision'        => $row['numeric_precision'],
                'numeric_scale'            => $row['numeric_scale'],
                'numeric_unsigned'         => null,
                'erratas'                  => $erratas,
            ];
        }

        $this->data['columns'][$schema][$table] = $columns;
    }

    protected function loadSequenceData($schema)
    {
        if (isset($this->data['sequences'][$schema])) {
            return;
        }

        $this->prepareDataHierarchy('sequences', $schema);

        $p = $this->adapter->getPlatform();

        $isColumns = [
            'sequence_name',
            'data_type',
            'numeric_precision',
            'numeric_precision_radix',
            'numeric_scale',
            'start_value',
            'minimum_value',
            'maximum_value',
            'increment',
            'cycle_option'
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
            . ' FROM ' . $p->quoteIdentifierChain(['information_schema', 'sequences'])
            . ' WHERE ';

        if ($schema !== self::DEFAULT_SCHEMA) {
            $sql .= $p->quoteIdentifier('sequence_schema')
                . ' = ' . $p->quoteTrustedValue($schema);
        } else {
            $sql .= $p->quoteIdentifier('sequence_schema')
                . ' != \'information_schema\'';
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $data = [];
        foreach ($results->toArray() as $row) {
            $row                             = array_change_key_case($row, CASE_LOWER);
            $data[$row['sequence_name']] = $row;
        }

        $this->data['sequences'][$schema] = $data;
    }
}

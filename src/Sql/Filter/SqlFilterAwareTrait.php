<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Filter;

use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\SqlInterface;

trait SqlFilterAwareTrait
{
    protected $_sqlFilter = null;
    protected $_filterValueSeparator = " ";
    protected $_fitlerCombination = PredicateSet::OP_AND;

    protected function setSqlFilter(SqlFilterInterface $sqlFilter): void
    {
        $this->_sqlFilter = $sqlFilter;
    }

    protected function getSqlFilter(): SqlFilterInterface
    {
        return $this->_sqlFilter;
    }

    protected function setFilterValueSeparator(string $separator = " "): void
    {
        $this->_filterValueSeparator = $separator;
    }

    protected function setFilterCombination(string $combination): void
    {
        $this->_fitlerCombination = $combination;
    }

    protected function getFilterCombination(): string
    {
        return $this->_fitlerCombination;
    }

    public function applyFilter(
        SqlInterface $sql,
        string $filters = "",
        string $combination = PredicateSet::OP_AND
    ): SqlInterface {
        if (is_null($this->_sqlFilter)) {
            return $sql;
        }

        if (!is_null($filters)) {
            foreach (explode($this->_filterValueSeparator, trim($filters)) as $filter) {
                $sql = $this->getSqlFilter()->applyFilter(
                    $sql,
                    urldecode(trim($filter)),
                    $combination
                );
            }
        }

        return $sql;
    }
}

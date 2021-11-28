<?php
declare(strict_types=1);

namespace Itseasy\Database;

use ArrayIterator;
use ArrayObject;
use Exception;
use Itseasy\Database\ResultInterface as ItseasyResultInterface;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Traversable;

class Result implements ItseasyResultInterface
{
    protected $errors = [];
    protected $resultSet;
    protected $lastGeneratedValue;
    protected $resultSetObjectPrototype;

    public function __construct(
        $arrayObjectPrototype = null,
        $resultSetObjectPrototype = null,
        ResultSetInterface $resultSet = null
    ) {
        if (is_null($resultSet)) {
            $this->resultSet = new ResultSet();
        }

        if (!is_null($resultSetObjectPrototype)) {
            $this->setResultSetObjectPrototype($resultSetObjectPrototype);
        } else {
            $this->resultSetObjectPrototype = new ArrayIterator();
        }

        if (!is_null($arrayObjectPrototype)) {
            $this->setArrayObjectPrototype($arrayObjectPrototype);
        }

    }

    public function setArrayObjectPrototype($object) : self
    {
        if (is_string($object) and class_exists($object)) {
            $object = new $object;
        }

        $this->resultSet->setArrayObjectPrototype($object);
        return $this;
    }

    public function setResultSetObjectPrototype($object) : self
    {
        if (is_string($object) and class_exists($object)) {
            $object = new $object;
        }
        $this->resultSetObjectPrototype = $object;
        return $this;
    }

    public function setResult(ResultInterface $result) : void
    {
        if ($result->isQueryResult()) {
            $rowset = $result->getResource()->fetchAll(\PDO::FETCH_ASSOC);
            $this->resultSet->initialize($rowset);
        }
        $this->setGeneratedValue(intval($result->getGeneratedValue()));
    }

    public function addError($error) : void
    {
        preg_match('/\(([^)]+)\)/', $error, $matches);
        if (empty($matches[1])) {
            $this->errors[] = $error;
        } else {
            $errors = array_map('trim', explode('-', $matches[1]));
            $this->errors[] = end($errors);
        }
    }

    protected function setGeneratedValue(?int $value) : void
    {
        $this->lastGeneratedValue = $value;
    }

    public function getGeneratedValue() : ?int
    {
        return $this->lastGeneratedValue;
    }

    /**
     * @return object|null
     */
    public function getFirstRow($arrayObjectPrototype = null)
    {
        if (!is_null($arrayObjectPrototype)) {
            $this->setArrayObjectPrototype($arrayObjectPrototype);
        }

        $this->resultSet->rewind();
        if ($this->resultSet->getArrayObjectPrototype() instanceof ArrayObject) {
            return $this->resultSet->current()->getArrayCopy();
        }
        return $this->resultSet->current();
    }

    /**
     * @return mixed
     */
    public function getSingleValue()
    {
        try {
            return current($this->getFirstRow());
        } catch (Exception $e) {
            return null;
        }
    }

    public function getRows(
        $resultSetObjectPrototype = null,
        $arrayObjectPrototype = null
    ) : Traversable
    {
        if (!$this->resultSet->count()) {
            return $this->resultSet->getDataSource();
        }

        if (!is_null($resultSetObjectPrototype)) {
            $this->setResultSetObjectPrototype($resultSetObjectPrototype);
        }

        if (!is_null($arrayObjectPrototype)) {
            $this->setArrayObjectPrototype($arrayObjectPrototype);
        }

        $resultSetObjectPrototype = clone $this->resultSetObjectPrototype;

        while ($this->resultSet->valid()) {
            $row = $this->resultSet->current();
            if ($this->resultSet->getArrayObjectPrototype() instanceof ArrayObject) {
                $row = $row->getArrayCopy();
            }

            if (is_array($resultSetObjectPrototype)) {
                $resultSetObjectPrototype[] = $row;
            } else if (method_exists($resultSetObjectPrototype, "append")) {
                $resultSetObjectPrototype->append($row);
            }

            $this->resultSet->next();
        }
        return $resultSetObjectPrototype;
    }

    public function getErrors() : array
    {
        return $this->errors;
    }

    public function isSuccess(bool $check_empty = false) : bool
    {
        if ($check_empty) {
            $noerror = ($this->isError() ? false : true);
            $noempty = ($this->isEmpty() ? false : true);

            return ($noerror and $noempty);
        } else {
            return ($this->isError() ? false : true);
        }
    }

    public function isEmpty() : bool
    {
        return $this->resultSet->count() > 0 ? false : true;
    }

    public function isError() : bool
    {
        return count($this->errors) ? true : false;
    }
}

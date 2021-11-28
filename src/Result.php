<?php
declare(strict_types=1);

namespace Itseasy\Database;

use ArrayIterator;
use ArrayObject;
use Exception;
use Itseasy\Database\ResultInterface as ItseasyResultInterface;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Traversable;

class Result implements ItseasyResultInterface
{
    protected $errors = [];
    protected $resultSet;
    protected $object = null;
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

        $this->resultSetObjectPrototype = new ArrayIterator();

        if (!is_null($arrayObjectPrototype)) {
            $this->resultSet->setArrayObjectPrototype($arrayObjectPrototype);
        }

        if (!is_null($resultSetObjectPrototype)) {
            $this->setResultSetObjectPrototype($resultSetObjectPrototype);
        }
    }

    public function setObject($object) : self
    {
        return $this->setArrayObjectPrototype($object);
    }

    public function setArrayObjectPrototype($arrayObjectPrototype) : self
    {
        if (is_string($arrayObjectPrototype) and class_exists($arrayObjectPrototype)) {
            $arrayObjectPrototype = new $arrayObjectPrototype;
        }
        $this->resultSet->setArrayObjectPrototype($arrayObjectPrototype);
        return $this;
    }

    public function setResultSetObjectPrototype(
        $resultSetObjectPrototype,
        $arrayObjectPrototype = null
    ) : self {
        if (is_string($resultSetObjectPrototype)
            and class_exists($resultSetObjectPrototype)) {
            $resultSetObjectPrototype = new $resultSetObjectPrototype;
        }
        $this->resultSetObjectPrototype = $resultSetObjectPrototype;

        if (!is_null($arrayObjectPrototype)) {
            $this->setArrayObjectPrototype($arrayObjectPrototype);
        }

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
    public function getFirstRow()
    {
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

    public function getRows() : Traversable
    {
        if (!$this->resultSet->count()) {
            return $this->resultSet->getDataSource();
        }

        while ($this->resultSet->valid()) {
            $row = $this->resultSet->current();
            if ($this->resultSet->getArrayObjectPrototype() instanceof ArrayObject) {
                $row = $row->getArrayCopy();
            }
            $this->resultSetObjectPrototype->append($row);
            $this->resultSet->next();
        }
        return $this->resultSetObjectPrototype;
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

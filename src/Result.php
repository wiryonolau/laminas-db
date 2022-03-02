<?php
declare(strict_types=1);

namespace Itseasy\Database;

use ArrayIterator;
use ArrayObject;
use Exception;
use Itseasy\Database\ResultInterface as ItseasyResultInterface;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Hydrator\HydratorInterface;
use Traversable;

class Result implements ItseasyResultInterface
{
    protected $errors = [];
    protected $resultSet;
    protected $lastGeneratedValue;
    protected $resultSetObjectPrototype;

    public function __construct(
        $objectPrototype = null,
        $resultSetObjectPrototype = null
    ) {
        $this->resultSet = new HydratingResultSet();

        if (!is_null($resultSetObjectPrototype)) {
            $this->setResultSetObjectPrototype($resultSetObjectPrototype);
        } else {
            $this->resultSetObjectPrototype = new ArrayIterator();
        }

        if (!is_null($objectPrototype)) {
            $this->setObjectPrototype($objectPrototype);
        }
    }

    public function getHydrator() : HydratorInterface
    {
        return $this->resultSet->getHydrator();
    }

    public function setHydrator(HydratorInterface $hydrator) : self
    {
        $this->resultSet->setHydrator($hydrator);
        return $this;
    }

    public function setObjectPrototype($object) : self
    {
        if (is_string($object) and class_exists($object)) {
            $object = new $object;
        }

        $this->resultSet->setObjectPrototype($object);
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
     * @return object
     */
    public function getFirstRow($objectPrototype = null)
    {
        if (!is_null($objectPrototype)) {
            $this->setObjectPrototype($objectPrototype);
        }

        if (!$this->resultSet->count()) {
            return $this->resultSet->getObjectPrototype();
        }

        $this->resultSet->rewind();
        if ($this->resultSet->getObjectPrototype() instanceof ArrayObject) {
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

    /**
     * @return array|Traversable
     */
    public function getRows(
        $resultSetObjectPrototype = null,
        $objectPrototype = null
    ) {
        if (!is_null($resultSetObjectPrototype)) {
            $this->setResultSetObjectPrototype($resultSetObjectPrototype);
        }

        if (!is_null($objectPrototype)) {
            $this->setObjectPrototype($objectPrototype);
        }

        if (is_array($this->resultSetObjectPrototype)) {
            $resultSetObjectPrototype = [];
        } else if ($this->resultSetObjectPrototype instanceof ArrayIterator){
            // Cannot clone instanceof ArrayIterator , will cause nested storage attribute
            $class = get_class($this->resultSetObjectPrototype);
            $resultSetObjectPrototype = new $class;
        } else {
            $resultSetObjectPrototype = clone $this->resultSetObjectPrototype;
        }

        if (!$this->resultSet->count()) {
            return $resultSetObjectPrototype;
        }

        $this->resultSet->rewind();
        while ($this->resultSet->valid()) {
            // hydrate object
            $row = $this->resultSet->current();
            if ($this->resultSet->getObjectPrototype() instanceof ArrayObject) {
                $row = $row->getArrayCopy();
            }

            if (is_array($resultSetObjectPrototype)) {
                $resultSetObjectPrototype[] = $row;
            } elseif (method_exists($resultSetObjectPrototype, "append")) {
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

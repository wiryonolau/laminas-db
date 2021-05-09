<?php

namespace Itseasy\Database;

use ArrayIterator;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\ReflectionHydrator;

class Result
{
    protected $errors = [];
    protected $resultSet;
    protected $object;
    protected $hydrator;
    protected $lastGeneratedValue;

    public function __construct(?HydratorInterface $hydrator = null)
    {
        if (is_null($hydrator)) {
            $this->setHydrator(ReflectionHydrator::class);
        } else {
            $this->setHydrator($hydrator);
        }

        $this->resultSet = new ArrayIterator();
    }

    public function setHydrator($hydrator) : self
    {
        if (is_string($hydrator) and class_exists($hydrator)) {
            $hydrator = new $hydrator;
        }

        if ($hydrator instanceof HydratorInterface) {
            $this->hydrator = $hydrator;
        }

        return $this;
    }

    public function setObject(string $object) : self
    {
        if (class_exists($object)) {
            $this->object = $object;
        }
        return $this;
    }

    public function setResult(ResultInterface $result) : void
    {
        if ($result->isQueryResult()) {
            $rowset = $result->getResource()->fetchAll(\PDO::FETCH_ASSOC);

            $resultSet = new ResultSet();
            $resultSet->initialize($rowset);

            foreach ($resultSet->getDataSource() as $row) {
                $this->resultSet->append($row);
            }
        }
        $this->lastGeneratedValue = $result->getGeneratedValue();
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

    public function getGeneratedValue() : int
    {
        return $this->lastGeneratedValue;
    }

    /**
     * @return object
     */
    public function getFirstRow()
    {
        return $this->hydrate($this->resultSet->offsetGet(0));
    }

    /**
     * @return mixed
     */
    public function getSingleValue()
    {
        return current((array) $this->resultSet->offsetGet(0));
    }

    public function getRows() : ArrayIterator
    {
        return $this->hydrate($this->resultSet);
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

    /**
     * @return objectsf
     */
    protected function hydrate($rows)
    {
        if (is_null($rows)) {
            if ($this->object == "") {
                return $rows;
            } else {
                return new $this->object();
            }
        }

        if ($this->object == "") {
            return $rows;
        }

        if ($rows instanceof ArrayIterator) {
            $result = new ArrayIterator();
            foreach ($rows as $row) {
                $object = new $this->object();
                $result->append($this->hydrator->hydrate($row, $object));
            }
            return $result;
        }

        $object = new $this->object();
        return $this->hydrator->hydrate($rows, $object);
    }
}

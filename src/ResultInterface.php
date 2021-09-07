<?php

namespace Itseasy\Database;

use ArrayIterator;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\ReflectionHydrator;

interface ResultInterface
{
    public function setHydrator($hydrator) : self;
    public function setObject(string $object) : self;
    public function setResult(ResultInterface $result) : void;
    public function addError($error) : void;
    public function getGeneratedValue() : int;
    public function getFirstRow();
    public function getSingleValue();
    public function getRows();
    public function getErrors();
}

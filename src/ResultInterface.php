<?php

namespace Itseasy\Database;

use ArrayIterator;
use Laminas\Db\Adapter\Driver\ResultInterface as LaminasResultInterface;

interface ResultInterface
{
    public function setObject(string $object) : self;
    public function setResult(LaminasResultInterface $result) : void;
    public function addError($error) : void;
    public function getGeneratedValue() : ?int;
    public function getFirstRow();
    public function getSingleValue();
    public function getRows();
    public function getErrors();
}

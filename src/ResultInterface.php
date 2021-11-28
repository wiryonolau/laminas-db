<?php

namespace Itseasy\Database;

use Laminas\Db\Adapter\Driver\ResultInterface as LaminasResultInterface;

interface ResultInterface
{
    public function setResult(LaminasResultInterface $result) : void;
    public function addError($error) : void;
    public function getGeneratedValue() : ?int;
    public function getFirstRow();
    public function getSingleValue();
    public function getRows();
    public function getErrors();
}

<?php

namespace Itseasy\DatabaseTest\Model;

class TestModel {
    protected $id;
    protected $name;
    protected $tech_creation_date;
    protected $tech_modification_date;

    public function __get($name) {
        return $this->{$name};
    }
}

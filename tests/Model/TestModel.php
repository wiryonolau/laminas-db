<?php

namespace Itseasy\DatabaseTest\Model;

class TestModel {
    protected $id;
    protected $name;

    public function __get($name) {
        return $this->{$name};
    }
}

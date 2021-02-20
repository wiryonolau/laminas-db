<?php

namespace Itseasy\Database\Metadata\Object;

use Laminas\Db\Metadata\Object\TableObject;

class MysqlTableObject extends TableObject {
    protected $charset;
    protected $collation;
    protected $engine;

    public function setCharset(string $charset) : void {
        $this->charset = $charset;
    }

    public function setCollation(string $collation) : void {
        $this->collation = $collation;
    }

    public function setEngine(string $engine) : void {
        $this->engine = $engine;
    }

    public function setType(string $type) : void {
        $this->type = $type;
    }

    public function getEngine() : string {
        return $this->engine;
    }

    public function getCollation() : string {
        return $this->collation;
    }

    public function getCharset() : string {
        return $this->charset;
    }
}

<?php

namespace Itseasy\DatabaseTest\Model;

use Itseasy\Model\CollectionModel;

class TestCollectionModel extends CollectionModel
{
    public function __construct()
    {
        parent::__construct(TestModel::class);
    }
}

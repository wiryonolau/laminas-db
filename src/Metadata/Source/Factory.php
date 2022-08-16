<?php

namespace Itseasy\Database\Metadata\Source;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\Source\Factory as LaminasSourceFactory;
use Laminas\Db\Metadata\MetadataInterface;

class Factory extends LaminasSourceFactory
{
    public static function createSourceFromAdapter(Adapter $adapter): MetadataInterface
    {
        $platformName = $adapter->getPlatform()->getName();

        switch ($platformName) {
            case 'MySQL':
                return new MysqlMetadata($adapter);
            default:
                return parent::createSourceFromAdapter($adapter);
        }
    }
}

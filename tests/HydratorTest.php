<?php

namespace Itseasy\DatabaseTest;

use Itseasy\Database\Metadata\Hydrator\ColumnObjectHydrator;
use Laminas\Db\Metadata\Object\ColumnObject;
use Laminas\Log\Logger;
use PHPUnit\Framework\TestCase;

final class HydratorTest extends TestCase
{
    protected $logger;

    public function setUp(): void
    {
        $this->logger = new Logger();
        $this->logger->addWriter('stream', null, ['stream' => 'php://stderr']);
    }

    public function testColumnErrataHydrator(): void
    {
        $hydrator = new ColumnObjectHydrator();
        $obj = $hydrator->hydrate([
            "errata" => [
                "auto_increment" => true
            ]
        ], new ColumnObject("test", "test"));

        $this->assertEquals($obj->getErratas()["auto_increment"], true);
    }
}

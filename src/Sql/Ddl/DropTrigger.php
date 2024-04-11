<?php

namespace Itseasy\Database\Sql\Ddl;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;

class DropTrigger extends AbstractSql implements SqlInterface
{
    public const TRIGGER = 'trigger';

    /** @var array */
    protected $specifications = [
        self::TRIGGER => 'DROP TRIGGER %1$s',
    ];

    protected $trigger = '';

    public function __construct($trigger = '')
    {
        $this->trigger = $trigger;
    }

    protected function processTrigger(?PlatformInterface $adapterPlatform = null)
    {
        return [
            $adapterPlatform->quoteIdentifier($this->trigger),
        ];
    }
}

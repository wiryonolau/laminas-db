<?php

namespace Itseasy\Database\Sql\Ddl\Trigger;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Metadata\Object\TriggerObject;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;

class MysqlCreateTrigger extends AbstractSql implements SqlInterface
{
    const TRIGGER = "trigger";

    protected $trigger;

    // CREATE
    //  [DEFINER = user]
    //  TRIGGER [IF NOT EXISTS] trigger_name
    //  trigger_time trigger_event
    //  ON tbl_name FOR EACH ROW
    //  [trigger_order]
    //  trigger_body

    protected $specifications = [
        self::TRIGGER => <<<EOF
            CREATE DEFINER=CURRENT_USER TRIGGER IF NOT EXISTS %1\$s
            %2\$s %3\$s ON %4\$s FOR EACH %5\$s
            BEGIN
            %6\$s
            END
        EOF
    ];

    public function __construct(
        TriggerObject $trigger
    ) {
        $this->trigger = $trigger;
    }

    protected function processTrigger(PlatformInterface $adapterPlatform = null): array
    {
        return [
            $this->trigger->getName(),
            $this->trigger->getActionTiming(),
            $this->trigger->getEventManipulation(),
            $this->trigger->getEventObjectTable(),
            $this->trigger->getActionOrientation(),
            $this->trigger->getActionStatement()
        ];
    }
}

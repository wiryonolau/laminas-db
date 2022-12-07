<?php

namespace Itseasy\Database\Sql\Ddl\Trigger;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Metadata\Object\TriggerObject;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;


class PostgresqlCreateTrigger extends AbstractSql implements SqlInterface
{
    const TRIGGER = "trigger";

    protected $trigger;

    // CREATE [ OR REPLACE ] [ CONSTRAINT ] TRIGGER name { BEFORE | AFTER | INSTEAD OF } { event [ OR ... ] }
    // ON table_name
    // [ FROM referenced_table_name ]
    // [ NOT DEFERRABLE | [ DEFERRABLE ] [ INITIALLY IMMEDIATE | INITIALLY DEFERRED ] ]
    // [ REFERENCING { { OLD | NEW } TABLE [ AS ] transition_relation_name } [ ... ] ]
    // [ FOR [ EACH ] { ROW | STATEMENT } ]
    // [ WHEN ( condition ) ]
    // EXECUTE { FUNCTION | PROCEDURE } function_name ( arguments )

    // where event can be one of:

    //     INSERT
    //     UPDATE [ OF column_name [, ... ] ]
    //     DELETE
    //     TRUNCATE


    protected $specifications = [
        self::TRIGGER => <<<EOF
            CREATE OR REPLACE TRIGGER %1\$s"
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

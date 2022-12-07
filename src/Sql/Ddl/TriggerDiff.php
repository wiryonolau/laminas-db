<?php

namespace Itseasy\Database\Sql\Ddl;

use Exception;
use Itseasy\Database\Metadata\Object\RoutineObject;
use Itseasy\Database\Sql\Ddl\Routine\PostgresqlCreateRoutine;
use Itseasy\Database\Sql\Ddl\Trigger\MysqlCreateTrigger;
use Itseasy\Database\Sql\Ddl\Trigger\PostgresqlCreateTrigger;
use Laminas\Db\Metadata\Object\TriggerObject;

class TriggerDiff
{
    protected $metadata;

    protected $driver;

    // array of table name
    protected $existingTriggerNames;

    protected $existingRoutineNames;

    public function __construct(
        AdapterInterface $adapter,
        ?array $existingTriggerNames = [],
        ?array $existingRoutineNames = []
    ) {
        $this->metadata = Factory::createSourceFromAdapter($adapter);

        $this->driver = $adapter->getDriver()->getConnection()->getDriverName();

        if (empty($existingTriggerNames)) {
            $this->existingTriggerNames = $this->metadata->getTriggerNames();
        } else {
            $this->existingTriggerNames = $existingTriggerNames;
        }

        // Postgre required routines
        if ($this->driver == "pgsql" and empty($existingRoutineNames)) {
            $this->existingRoutineNames = $this->metadata->getRoutineNames();
        } else {
            $this->existingRoutineNames = $existingRoutineNames;
        }
    }

    public function diff(
        TriggerObject $trigger,
        ?TriggerObject $existingTrigger = null,
        ?RoutineObject $routine = null
    ) {
        $ddls = [];

        if (
            is_null($existingTrigger)
            and in_array($trigger->getName(), $this->existingTriggerNames)
        ) {
            $existingTrigger = $this->metadata->getTrigger($trigger->getName());
        }

        if ($existingTrigger) {
            // some db can replace, for now drop everything
            $ddls[] = new DropTrigger($existingTrigger->getName());
        }

        switch ($this->driver) {
            case "mysql":
                $ddls[] = new MysqlCreateTrigger($trigger);
                break;
            case "pgsql":
                if (is_null($routine)) {
                    throw new Exception("Routine required for postgresql trigger");
                }

                if (!in_array($routine->getName(), $this->existingRoutineNames)) {
                    $ddls[] = new PostgresqlCreateRoutine($routine);
                }

                $ddls[] = new PostgresqlCreateTrigger($trigger);
                break;
        }

        return $ddls;
    }
}

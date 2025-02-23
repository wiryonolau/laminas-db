<?php

namespace Itseasy\Database\Sql\Ddl;

use Exception;
use Itseasy\Database\Metadata\Object\RoutineObject;
use Itseasy\Database\Metadata\Source\Factory;
use Itseasy\Database\Sql\Ddl\Routine\PostgresqlCreateRoutine;
use Itseasy\Database\Sql\Ddl\Trigger\MysqlCreateTrigger;
use Itseasy\Database\Sql\Ddl\Trigger\PostgresqlCreateTrigger;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Metadata\Object\TriggerObject;

class TriggerDiff
{
    protected $metadata;
    protected $driver;
    protected $adapter;

    // array of table name
    protected $existingTriggerNames;

    protected $existingRoutineNames;

    /**
     * TriggerDiff doesn't use metadata, so driver name is retrieve from connection directly
     */
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
            if (!$this->triggerHasDiff($existingTrigger, $trigger)) {
                return $ddls;
            }

            // some db can replace, for now safer to drop everything if different
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

    protected function triggerHasDiff(
        TriggerObject $existing,
        TriggerObject $update
    ) {
        if (
            !empty($update->getEventManipulation())
            and $existing->getEventManipulation() != $update->getEventManipulation()
        ) {
            return true;
        }

        if (
            !empty($update->getEventObjectCatalog())
            and $existing->getEventObjectCatalog() != $update->getEventObjectCatalog()
        ) {
            return true;
        }

        if (
            !empty($update->getEventObjectSchema())
            and $existing->getEventObjectSchema() != $update->getEventObjectSchema()
        ) {
            return true;
        }

        if (
            !empty($update->getEventObjectTable())
            and $existing->getEventObjectTable() != $update->getEventObjectTable()
        ) {
            return true;
        }

        if (
            !empty($update->getActionCondition())
            and $existing->getActionCondition() != $update->getActionCondition()
        ) {
            return true;
        }

        if (
            !empty($update->getActionOrder())
            and $existing->getActionOrder() != $update->getActionOrder()
        ) {
            return true;
        }

        if (
            !empty($update->getActionOrder())
            and $existing->getActionOrder() != $update->getActionOrder()
        ) {
            return true;
        }

        if (
            !empty($update->getActionOrientation())
            and $existing->getActionOrientation() != $update->getActionOrientation()
        ) {
            return true;
        }

        if (
            !empty($update->getActionReferenceNewRow())
            and $existing->getActionReferenceNewRow() != $update->getActionReferenceNewRow()
        ) {
            return true;
        }

        if (
            !empty($update->getActionReferenceNewTable())
            and $existing->getActionReferenceNewTable() != $update->getActionReferenceNewTable()
        ) {
            return true;
        }

        if (
            !empty($update->getActionReferenceOldRow())
            and $existing->getActionReferenceOldRow() != $update->getActionReferenceOldRow()
        ) {
            return true;
        }

        if (
            !empty($update->getActionReferenceOldTable())
            and $existing->getActionReferenceOldTable() != $update->getActionReferenceOldTable()
        ) {
            return true;
        }

        $existingActionStatement = md5(trim(preg_replace("/\s*/m", "", $existing->getActionStatement())));
        $updateActionStatement = md5(trim(preg_replace("/\s*/m", "", $update->getActionStatement())));
        if (!hash_equals($existingActionStatement, $updateActionStatement)) {
            return true;
        }

        if (
            !empty($update->getActionTiming())
            and $existing->getActionTiming() != $update->getActionTiming()
        ) {
            return true;
        }
    }
}

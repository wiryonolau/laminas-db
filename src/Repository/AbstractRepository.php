<?php

declare(strict_types=1);

namespace Itseasy\Repository;

use Exception;
use Itseasy\Database\Database;
use Itseasy\Database\Result;
use Itseasy\Database\Sql\Filter\SqlFilterAwareInterface;
use Itseasy\Database\ResultInterface;
use Itseasy\Database\Sql\Filter\SqlFilterAwareTrait;
use Laminas\Db\Adapter\Driver\AbstractConnection;
use Laminas\Db\Sql;
use Laminas\Db\Sql\Predicate\Expression;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Log\LoggerAwareTrait;

abstract class AbstractRepository implements
    SqlFilterAwareInterface,
    LoggerAwareInterface,
    EventManagerAwareInterface
{
    use SqlFilterAwareTrait;
    use EventManagerAwareTrait;
    use LoggerAwareTrait;

    protected $db;
    protected $table;

    public function __construct(
        Database $db,
        string $table
    ) {
        $this->db = $db;
        $this->table = $table;

        $this->defineSqlFilter();
    }

    abstract protected function defineSqlFilter(): void;

    /**
     * @return ResultInterface | $objectPrototype
     */
    public function getRowByIdentifier(
        $value,
        string $identifier = "id",
        $objectPrototype = null
    ) {
        $this->getEventManager()->trigger(
            "repository.select.pre",
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => null
            ]
        );

        $select = new Sql\Select($this->table);
        $select->where([
            $identifier => $value
        ]);

        $result = $this->db->execute($select);

        $this->getEventManager()->trigger(
            "repository.select.post",
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => $result->isError()
            ]
        );

        if (is_null($objectPrototype)) {
            return $result;
        }

        return $result->getFirstRow($objectPrototype);
    }

    /**
     * @return ResultInterface | $resultSetObjectPrototype | ArrayIterator
     */
    public function getRows(
        array $where = [],
        ?string $orders = null,
        ?int $offset = null,
        ?int $limit = null,
        $resultSetObjectPrototype = null,
        $objectPrototype = null
    ) {
        $this->getEventManager()->trigger(
            'repository.select.pre',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => null
            ]
        );

        $select = new Sql\Select($this->table);
        $select->where($where);

        if (is_string($orders) and !empty(trim($orders))) {
            $select->order($orders);
        }

        if (!is_null($limit)) {
            $select->limit($limit);
        }

        if ($limit and !is_null($offset)) {
            $select->offset($offset);
        }

        $result = $this->db->execute($select);

        $this->getEventManager()->trigger(
            'repository.select.post',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => $result->isError()
            ]
        );

        if (is_null($resultSetObjectPrototype) and is_null($objectPrototype)) {
            return $result;
        }

        return $result->getRows($resultSetObjectPrototype, $objectPrototype);
    }

    public function getRowCount(
        array $where = []
    ): int {
        $this->getEventManager()->trigger(
            'repository.select.pre',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => null
            ]
        );

        $result = new Result();

        try {
            $select = new Sql\Select($this->table);
            $select->columns(["num" => new Expression("COUNT(*)")]);
            $select->where($where);

            $result = $this->db->execute($select);

            return intval($result->getSingleValue());
        } catch (Exception $e) {
            return 0;
        } finally {
            $this->getEventManager()->trigger(
                'repository.select.post',
                null,
                [
                    "function" => __FUNCTION__,
                    "table" => $this->table,
                    "arguments" => func_get_args(),
                    "success" => $result->isError()
                ]
            );
        }
    }

    /**
     * @return ResultInterface | $resultSetObjectPrototype | ArrayIterator
     */
    public function getFilterAwareRows(
        string $filters = "",
        ?string $orders = null,
        ?int $offset = null,
        ?int $limit = null,
        $resultSetObjectPrototype = null,
        $objectPrototype = null
    ) {
        $this->getEventManager()->trigger(
            'repository.select.pre',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => null
            ]
        );

        $select = new Sql\Select($this->table);

        $select = $this->applyFilter($select, $filters, $this->getFilterCombination());

        if (is_string($orders) and !empty(trim($orders))) {
            $select->order($orders);
        }

        if (!is_null($limit)) {
            $select->limit($limit);
        }

        if ($limit and !is_null($offset)) {
            $select->offset($offset);
        }

        $result = $this->db->execute($select);

        $this->getEventManager()->trigger(
            'repository.select.post',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => $result->isError()
            ]
        );

        if (is_null($resultSetObjectPrototype) and is_null($objectPrototype)) {
            return $result;
        }

        return $result->getRows($resultSetObjectPrototype, $objectPrototype);
    }

    public function getFilterAwareRowCount(
        string $filters = ""
    ): int {
        $this->getEventManager()->trigger(
            'repository.select.pre',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => null
            ]
        );

        $result = new Result();

        try {
            $select = new Sql\Select($this->table);
            $select->columns(["num" => new Expression("COUNT(*)")]);
            $select = $this->applyFilter($select, $filters, $this->getFilterCombination());
            $result = $this->db->execute($select);

            if ($result->isError()) {
                throw new Exception(sprintf("Unable to Retrieve Record"));
            }

            return intval($result->getSingleValue());
        } catch (Exception $e) {
            return 0;
        } finally {
            $this->getEventManager()->trigger(
                'repository.select.post',
                null,
                [
                    "function" => __FUNCTION__,
                    "table" => $this->table,
                    "arguments" => func_get_args(),
                    "success" => $result->isError()
                ]
            );
        }
    }

    public function delete(array $where = []): ResultInterface
    {
        $this->getEventManager()->trigger(
            'repository.delete.pre',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => null
            ]
        );


        $delete = new Sql\Delete($this->table);
        $delete->where($where);

        $result = new Result();

        $this->db->beginTransaction();
        try {
            $result = $this->db->execute($delete);
            if ($result->isError()) {
                throw new Exception(sprintf("Unable to Delete Record"));
            }

            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            return $result;
        } finally {
            $this->getEventManager()->trigger(
                'repository.delete.post',
                null,
                [
                    "function" => __FUNCTION__,
                    "table" => $this->table,
                    "arguments" => func_get_args(),
                    "success" => $result->isSuccess()
                ]
            );
        }
    }

    public function filterAwareDelete(string $filters = ""): ResultInterface
    {
        $this->getEventManager()->trigger(
            'repository.delete.pre',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => null
            ]
        );


        $delete = new Sql\Delete($this->table);
        $delete = $this->applyFilter(
            $delete,
            $filters,
            $this->getFilterCombination()
        );

        $result = new Result();

        $this->db->beginTransaction();
        try {
            $result = $this->db->execute($delete);

            if ($result->isError()) {
                throw new Exception(sprintf("Unable to Delete Record"));
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            return $result;
        } finally {
            $this->getEventManager()->trigger(
                'repository.delete.post',
                null,
                [
                    "function" => __FUNCTION__,
                    "table" => $this->table,
                    "arguments" => func_get_args(),
                    "success" => $result->isSuccess()
                ]
            );
        }
    }

    /**
     * @return object
     */
    public function upsert(
        object $model,
        string $identifier = "id",
        array $exclude_attributes = []
    ) {
        $this->getEventManager()->trigger(
            'repository.upsert.pre',
            null,
            [
                "function" => __FUNCTION__,
                "table" => $this->table,
                "arguments" => func_get_args(),
                "success" => null
            ]
        );

        $success = true;

        $this->db->beginTransaction();

        try {
            $attributes = [];
            foreach ($model->getArrayCopy() as $key => $value) {
                if (in_array($key, $exclude_attributes)) {
                    continue;
                }

                if (is_bool($value)) {
                    $attributes[$key] = intval($value);
                } else {
                    $attributes[$key] = $value;
                }
            }

            // Always exclude identifier
            unset($attributes[$identifier]);

            if ($model->{$identifier}) {
                $update = new Sql\Update($this->table);
                $update->set($attributes);
                $update->where([
                    $identifier => $model->{$identifier}
                ]);

                $updateResult = $this->db->execute($update);
                if ($updateResult->isError()) {
                    throw new Exception(vsprintf("Unable to update %s row with %s = %s", [
                        $this->table,
                        $identifier,
                        strval($model->{$identifier})
                    ]));
                }
                $id = $model->{$identifier};
            } else {
                $insert = new Sql\Insert($this->table);
                $insert->values($attributes);
                $insertResult = $this->db->execute($insert);
                if ($insertResult->isError()) {
                    throw new Exception(sprintf("Unable to add new row to %s", $this->table));
                }

                $id = $insertResult->getGeneratedValue();
            }

            $this->db->commit();

            $select = $this->getRowByIdentifier($id, $identifier);
            return $select->getFirstRow($model);
        } catch (Exception $e) {
            $this->db->rollback();
            $success = false;
            return new $model();
        } finally {
            $this->getEventManager()->trigger(
                'repository.upsert.post',
                null,
                [
                    "function" => __FUNCTION__,
                    "table" => $this->table,
                    "arguments" => func_get_args(),
                    "success" => $success
                ]
            );
        }
    }

    public function inTransaction(): bool
    {
        return $this->db->inTransaction();
    }

    public function beginTransaction(
        string $isolation_level = Database::ISOLATION_SERIALIZABLE
    ): AbstractConnection {
        return $this->db->beginTransaction($isolation_level);
    }

    public function rollback(): AbstractConnection
    {
        return $this->db->rollback();
    }

    public function commit(): AbstractConnection
    {
        return $this->db->commit();
    }
}

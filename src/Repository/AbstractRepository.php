<?php

declare(strict_types=1);

namespace Itseasy\Repository;

use Exception;
use Itseasy\Database\Database;
use Itseasy\Database\Sql\Filter\SqlFilterAwareInterface;
use Itseasy\Database\ResultInterface;
use Itseasy\Database\Sql\Filter\SqlFilterAwareTrait;
use Laminas\Db\Sql;
use Laminas\Db\Sql\Predicate\Expression;

abstract class AbstractRepository implements SqlFilterAwareInterface
{
    use SqlFilterAwareTrait;

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
        $select = new Sql\Select($this->table);
        $select->where([
            $identifier => $value
        ]);

        $result = $this->db->execute($select);

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
        $select = new Sql\Select($this->table);
        $select->where($where);

        if (!is_null($orders)) {
            $select->order($orders);
        }

        if (!is_null($limit)) {
            $select->limit($limit);
        }

        if ($limit and !is_null($offset)) {
            $select->offset($offset);
        }

        $result = $this->db->execute($select);

        if (is_null($resultSetObjectPrototype) and is_null($objectPrototype)) {
            return $result;
        }

        return $result->getRows($resultSetObjectPrototype, $objectPrototype);
    }

    public function getRowCount(
        array $where = []
    ): int {
        try {
            $select = new Sql\Select($this->table);
            $select->columns(["num" => new Expression("COUNT(*)")]);
            $select->where($where);

            $result = $this->db->execute($select);

            return intval($result->getSingleValue());
        } catch (Exception $e) {
            return 0;
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
        $select = new Sql\Select($this->table);

        $select = $this->applyFilter($select, $filters, $this->getFilterCombination());

        if (!is_null($orders)) {
            $select->order($orders);
        }

        if (!is_null($limit)) {
            $select->limit($limit);
        }

        if ($limit and !is_null($offset)) {
            $select->offset($offset);
        }

        $result = $this->db->execute($select);

        if (is_null($resultSetObjectPrototype) and is_null($objectPrototype)) {
            return $result;
        }

        return $result->getRows($resultSetObjectPrototype, $objectPrototype);
    }

    public function getFilterAwareRowCount(
        string $filters = ""
    ): int {
        try {
            $select = new Sql\Select($this->table);
            $select->columns(["num" => new Expression("COUNT(*)")]);
            $select = $this->applyFilter($select, $filters, $this->getFilterCombination());
            $result = $this->db->execute($select);

            return intval($result->getSingleValue());
        } catch (Exception $e) {
            return 0;
        }
    }

    public function delete(array $where = []): ResultInterface
    {
        $delete = new Sql\Delete($this->table);
        $delete->where($where);
        return $this->db->execute($delete);
    }

    public function filterAwareDelete(string $filters = ""): ResultInterface
    {
        $delete = new Sql\Delete($this->table);
        $delete = $this->applyFilter(
            $delete,
            $filters,
            $this->getFilterCombination()
        );

        return $this->db->execute($delete);
    }

    /**
     * @return $model
     */
    public function upsert(
        object $model,
        string $identifier = "id",
        array $exclude_attributes = []
    ) {
        $this->db->beginTransaction();

        try {
            $attributes = [];
            foreach ($model->getArrayCopy() as $key => $value) {
                if (in_array($key, $exclude_attributes)) {
                    continue;
                }
                $attributes[$key] = $value;
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
            return new $model();
        }
    }
}

<?php

declare(strict_types=1);

namespace Itseasy\Repository;

use Exception;
use Itseasy\Database\Database;
use Itseasy\Database\ResultInterface;

class GenericRepository
{
    protected $db;
    protected $table;

    public function __construct(
        Database $db,
        string $table
    ) {
        $this->db = $db;
        $this->table = $table;
    }

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
        ?int $offset = null,
        ?int $limit = null,
        $resultSetObjectPrototype = null,
        $objectPrototype = null
    ) {
        $select = new Sql\Select($this->table);
        $select->where($where);

        if (!is_null($offset)) {
            $select->offset($offset);
        }

        if (!is_null($limit)) {
            $select->offset($limit);
        }

        $result = $this->db->execute($select);

        if (is_null($resultSetObjectPrototype) and is_null($objectPrototype)) {
            return $result;
        }

        return $result->getRows($resultSetObjectPrototype, $objectPrototype);
    }

    public function delete(array $where = []): ResultInterface
    {
        $delete = new Sql\Delete($this->table);
        $delete->where($where);
        return $this->db->execute($delete);
    }

    /**
     * @return $model
     */
    public function upsert(
        AbstractModel $model,
        string $identifier = "id"
    ) {
        $this->db->beginTransaction();

        try {
            $attributes = $model->getArrayCopy();
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

            $select = $this->getRowByIdentifier($this->table, $id);
            return $select->getFirstRow($model);
        } catch (Exception $e) {
            $this->db->rollback();
            return new $model();
        }
    }
}

<?php

namespace Itseasy\Database;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\Sql\SqlInterface;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Adapter\Exception\RuntimeException;
use Laminas\Db\Adapter\ExceptionInterface;
use PDO;
use PDOException;
use Exception;

class Database
{
    protected $dbAdapter;

    public function __construct(AdapterInterface $dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
    }

    public function beginTransaction()
    {
        return $this->dbAdapter->getDriver()->getConnection()->beginTransaction();
    }

    public function commit()
    {
        return $this->dbAdapter->getDriver()->getConnection()->commit();
    }

    public function rollback()
    {
        return $this->dbAdapter->getDriver()->getConnection()->rollback();
    }

    public function getSqlString(SqlInterface $statement) {
        $sql = new Sql($this->dbAdapter);
        return $sql->getSqlStringForSqlObject($statement);
    }

    protected function prepare($statement, $parameters)
    {
        if ($statement instanceof SqlInterface) {
            $statement = $this->getSqlString($statement);
        }

        if (!$statement) {
            throw new Exception('No statement given');
        }

        try {
            $stmt = $this->dbAdapter->createStatement();
            $stmt->prepare($statement);
        } catch (RuntimeException $e) {
            throw new Exception('Cannot prepare statement');
        }

        // For special case like transaction that require marking param as PARAM_OUTPUT
        // Pass parameters as ParameterContainer
        if ($parameters instanceof ParameterContainer) {
            $stmt->setParameterContainer($parameters);
        } else if (is_array($parameters)) {
            $parameters = new ParameterContainer($parameters);
            $stmt->setParameterContainer($parameters);
        }

        return $stmt;
    }

    public function execute($statement, array $parameters = [], bool $disconnect = false) : Result
    {
        $dbResult = new Result();

        // Prepare statement and parameters
        try {
            $stmt = $this->prepare($statement, $parameters);
        } catch (Exception $e) {
            $dbResult->addError($e->getMessage());
            return $dbResult;
        }

        // Execute statement
        try {
            $result = $stmt->execute();
            $dbResult->setResult($result);
        } catch (ExceptionInterface $e) {
            $message = $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();
            $dbResult->addError($message);
        } catch (PDOException $e) {
            $dbResult->addError($e->getMessage());
        } catch (Exception $e) {
            $dbResult->addError($e->getMessage());
        } finally {
            $stmt->getResource()->closeCursor();
        }

        // Request for Disconnect
        if ($disconnect) {
            $this->dbAdapter->getDriver()->getConnection()->disconnect();
        }

        return $dbResult;
    }
}

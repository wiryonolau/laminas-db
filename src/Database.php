<?php

namespace Itseasy\Database;

use Exception;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\Driver\AbstractConnection;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Adapter\Exception\RuntimeException;
use Laminas\Db\Adapter\ExceptionInterface;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\SqlInterface;
use Laminas\Log\LoggerAwareTrait;
use Laminas\Log\LoggerInterface;
use PDO;
use PDOException;

class Database
{
    use LoggerAwareTrait;

    const ISOLATION_SERIALIZABLE = "SERIALIZABLE";
    const ISOLATION_REPEATABLE_READ = "REPEATABLE READ";
    const ISOLATION_READ_COMMITTED = "READ UNCOMMITED";
    const ISOLATION_READ_UNCOMMITTED = "READ UNCOMMITED";

    protected $dbAdapter;

    public function __construct(
        AdapterInterface $dbAdapter,
        LoggerInterface $logger
    ) {
        $this->dbAdapter = $dbAdapter;
        $this->setLogger($logger);
    }

    public function getAdapter(): AdapterInterface
    {
        return $this->dbAdapter;
    }

    public function inTransaction(): bool
    {
        return $this->dbAdapter->getDriver()->getConnection()->inTransaction();
    }

    /*
     * Only support common isolation level
     * For oci8 or ibmdb2 use db config level to set this or call manually
     * For some engine in mysql / mariadb doesn't have transaction feature
     * 
     * This set isolation level for next transaction only
     * 
     * @throw Exception
     */
    public function beginTransaction(
        string $isolation_level = self::ISOLATION_SERIALIZABLE
    ): AbstractConnection {
        if (!in_array($isolation_level, [
            self::ISOLATION_SERIALIZABLE,
            self::ISOLATION_READ_COMMITTED,
            self::ISOLATION_READ_UNCOMMITTED,
            self::ISOLATION_REPEATABLE_READ
        ])) {
            throw new Exception("Invalid isolation level given");
        };

        switch ($this->dbAdapter->getDriver()->getConnection()->getDriverName()) {
            case "mssql":
            case "mysql":
                $this->execute(sprintf("SET TRANSACTION ISOLATION LEVEL %s", $isolation_level));
                break;
            case "pgsql":
                $this->execute(sprintf("SET TRANSACTION %s", $isolation_level));
                break;
            default:
        }
        return $this->dbAdapter->getDriver()->getConnection()->beginTransaction();
    }

    public function commit(): AbstractConnection
    {
        return $this->dbAdapter->getDriver()->getConnection()->commit();
    }

    public function rollback(): AbstractConnection
    {
        return $this->dbAdapter->getDriver()->getConnection()->rollback();
    }

    public function getSqlString(SqlInterface $statement): string
    {
        $sql = new Sql($this->dbAdapter);
        return $sql->buildSqlString($statement);
    }

    /**
     * @throw Exception
     */
    protected function prepare($statement, $parameters): StatementInterface
    {
        if ($statement instanceof SqlInterface) {
            $statement = $this->getSqlString($statement);
        }

        if (!$statement) {
            $this->logger->debug("No statement given");
            throw new Exception("No statement given");
        }

        try {
            $stmt = $this->dbAdapter->createStatement();
            $stmt->prepare($statement);
        } catch (RuntimeException $e) {
            $this->logger->debug($e->getMessage());
            throw new Exception("Cannot prepare statement");
        }

        // For special case like transaction that require marking param as PARAM_OUTPUT
        // Pass parameters as ParameterContainer
        if ($parameters instanceof ParameterContainer) {
            $stmt->setParameterContainer($parameters);
        } elseif (is_array($parameters)) {
            $parameters = new ParameterContainer($parameters);
            $stmt->setParameterContainer($parameters);
        }

        return $stmt;
    }

    public function execute(
        $statement,
        array $parameters = [],
        ?ResultInterface $result = null,
        bool $disconnect = false
    ): Result {
        if (is_null($result)) {
            $dbResult = new Result(null, null, new ResultSetHydrator());
        } else {
            $dbResult = $result;
        }

        // Prepare statement and parameters
        try {
            $stmt = $this->prepare($statement, $parameters);
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $dbResult->addError($e->getMessage());
            return $dbResult;
        }

        // Execute statement
        try {
            // Can only check if connection is cut off by php
            // If connection is cut off by database, this value will remain true ( Database has gone away )
            if (!$this->dbAdapter->getDriver()->getConnection()->isConnected()) {
                $this->dbAdapter->getDriver()->getConnection()->connect();
            }
            $result = $stmt->execute();
            $dbResult->setResult($result);
        } catch (ExceptionInterface $e) {
            $message = $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();
            $this->logger->debug($message);
            $dbResult->addError($message);
        } catch (PDOException $e) {
            $this->logger->debug($e->getMessage());
            $dbResult->addError($e->getMessage());
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $dbResult->addError($e->getMessage());
        } finally {
            /** @var Resource $stmt */
            $stmt->getResource()->closeCursor();
        }

        // Request for Disconnect
        if ($disconnect) {
            $this->dbAdapter->getDriver()->getConnection()->disconnect();
        }

        return $dbResult;
    }
}

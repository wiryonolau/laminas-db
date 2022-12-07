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

    // Nested transaction level
    protected $transactionLevel = 0;

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

    public function getTransactionLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Only support common isolation level
     * For oci8 or ibmdb2 use db config level to set this or call manually
     * For some engine in mysql / mariadb doesn't have transaction feature
     * 
     * This set isolation level for next transaction only
     * 
     * if ignore_nested_transaction is true
     *  beginTransaction will not run if already inside transaction
     * 
     * @param isolation_level string
     * @param ignore_nested_transaction bool 
     * 
     * @throw Exception
     */
    public function beginTransaction(
        string $isolation_level = self::ISOLATION_SERIALIZABLE,
        bool $ignore_nested_transaction = true
    ): AbstractConnection {
        if (!in_array($isolation_level, [
            self::ISOLATION_SERIALIZABLE,
            self::ISOLATION_READ_COMMITTED,
            self::ISOLATION_READ_UNCOMMITTED,
            self::ISOLATION_REPEATABLE_READ
        ])) {
            throw new Exception("Invalid isolation level given");
        };

        if ($this->inTransaction()) {
            if ($ignore_nested_transaction) {
                return $this->dbAdapter->getDriver()->getConnection();
            } else {
                $this->transactionLevel++;
            }
        } else {
            // Can only be set on first level transaction
            switch ($this->dbAdapter->getDriver()->getConnection()->getDriverName()) {
                case "mysql":
                case "mssql":
                case "pgsql":
                    $this->execute(sprintf("SET TRANSACTION ISOLATION LEVEL %s", $isolation_level));
                    break;
                default:
            }
        }

        return $this->dbAdapter->getDriver()->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     * Default ignore commit if nested
     */
    public function commit(bool $ignore_nested_transaction = true): AbstractConnection
    {
        if (
            $this->inTransaction()
            and $this->getTransactionLevel() > 0
            and $ignore_nested_transaction
        ) {
            return $this->dbAdapter->getDriver()->getConnection();
        }

        // This will throw if error
        $connection = $this->dbAdapter->getDriver()->getConnection()->commit();

        if ($this->transactionLevel > 0) {
            $this->transactionLevel--;
        }

        return $connection;
    }

    /**
     * Rollback transaction
     * Default ignore rollback if nested
     */
    public function rollback(bool $ignore_nested_transaction = true): AbstractConnection
    {
        if (
            $this->inTransaction()
            and $this->getTransactionLevel() > 0
            and $ignore_nested_transaction
        ) {
            return $this->dbAdapter->getDriver()->getConnection();
        }

        // This will throw if error
        $connection = $this->dbAdapter->getDriver()->getConnection()->rollback();

        if ($this->transactionLevel > 0) {
            $this->transactionLevel--;
        }

        return $connection;
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

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
use Laminas\Log\LoggerInterface;
use Laminas\Log\LoggerAwareTrait;
use PDO;
use PDOException;

class Database
{
    use LoggerAwareTrait;

    protected $dbAdapter;

    public function __construct(AdapterInterface $dbAdapter, LoggerInterface $logger)
    {
        $this->dbAdapter = $dbAdapter;
        $this->setLogger($logger);
    }

    public function getAdapter() : AdapterInterface
    {
        return $this->dbAdapter;
    }

    public function beginTransaction() : AbstractConnection
    {
        return $this->dbAdapter->getDriver()->getConnection()->beginTransaction();
    }

    public function commit() : AbstractConnection
    {
        return $this->dbAdapter->getDriver()->getConnection()->commit();
    }

    public function rollback() : AbstractConnection
    {
        return $this->dbAdapter->getDriver()->getConnection()->rollback();
    }

    public function getSqlString(SqlInterface $statement) : string
    {
        $sql = new Sql($this->dbAdapter);
        return $sql->getSqlStringForSqlObject($statement);
    }

    /**
     * @throw Exception
     */
    protected function prepare($statement, $parameters) : StatementInterface
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

    public function execute($statement, array $parameters = [], bool $disconnect = false) : Result
    {
        $dbResult = new Result();

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
            if(!$this->dbAdapter->getDriver()->getConnection()->isConnected()) {
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
            $stmt->getResource()->closeCursor();
        }

        // Request for Disconnect
        if ($disconnect) {
            $this->dbAdapter->getDriver()->getConnection()->disconnect();
        }

        return $dbResult;
    }
}

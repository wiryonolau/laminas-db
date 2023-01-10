<?php

namespace Itseasy\Database\Console\Command;

use Exception;
use Itseasy\Database\Metadata\Source\Factory;
use Itseasy\Database\Sql\Ddl\SchemaDiff;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\Driver\Pdo\Pdo as LaminasPdo;
use Laminas\Db\Sql\PreparableSqlInterface;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\SqlInterface;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use PDO;
use Throwable;

class DataCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected static $defaultName = "db:data";
    protected $db;
    protected $adapter;

    protected function configure(): void
    {
        $this->setHelp('Database data manager');
        $this->setDescription('Database data manager');

        $this->addArgument("file", InputArgument::REQUIRED, "Php file, must return array of PreparableSqlInterface", null);
        $this->addOption("username", "u", InputOption::VALUE_REQUIRED, "Connection username");
        $this->addOption("password", "p", InputOption::VALUE_REQUIRED, "Connection password");
        $this->addOption("dsn", null, InputOption::VALUE_REQUIRED, implode("\n", [
            "Connection DSN, Must be active Database and User",
            "Use of environment also supported",
            " DB_DRIVER, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD"
        ]));
        $this->addOption("disable-fk", null, InputOption::VALUE_NONE, "Disable foreign key check");
        $this->addOption("use-tx", null, InputOption::VALUE_NONE, "Run inside transaction");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data_file = realpath($input->getArgument("file"));
        $dsn = $input->getOption("dsn");
        $username = $input->getOption("username");
        $password = $input->getOption("password");
        $disableFk = $input->getOption("disable-fk", false);
        $useTransaction = $input->getOption("use-tx", false);

        $this->createAdapter();

        $data = include $data_file;

        $platform = $this->adapter->getPlatform()->getName();

        try {
            if ($disableFk) {
                switch ($platform) {
                    case Factory::PLATFORM_MYSQL:
                        $this->executeExpression("SET FOREIGN_KEY_CHECKS=0");
                        break;
                    case Factory::PLATFORM_POSTGRESQL:
                        // postgresql can disable constraints but must inside transaction
                        $this->adapter->getDriver()->getConnection()->beginTransaction();
                        $this->executeExpressoin("SET CONSTRAINTS ALL DEFERRED");
                        break;
                    default:
                }
            }

            if ($useTransaction) {
                $this->adapter->getDriver()->getConnection()->beginTransaction();
                try {
                    $this->executeQueries($data);
                    $this->adapter->getDriver()->getConnection()->commit();
                } catch (Throwable $th) {
                    $this->adapter->getDriver()->getConnection()->rollback();
                }
            } else {
                $this->executeQueries($data);
            }

            if ($disableFk) {
                switch ($platform) {
                    case Factory::PLATFORM_MYSQL:
                        $this->executeExpression("SET FOREIGN_KEY_CHECKS=1");
                        break;
                    case Factory::PLATFORM_POSTGRESQL:
                        $this->adapter->getDriver()->getConnection()->commit();
                        break;
                    default:
                }
            }
        } catch (Throwable $t) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function executeQueries(array $data): void
    {
        $sql = new Sql($this->adapter);
        foreach ($data as $query) {
            if (is_callable($query)) {
                $query = $query();
            }

            if (!$query instanceof PreparableSqlInterface) {
                throw new Exception("Invalid sql object");
            }

            $statement = $sql->prepareStatementForSqlObject($query);
            $statement->execute();
        }
    }

    protected function executeExpression(string $expression): void
    {
        $stmt = $this->adapter->createStatement();
        $stmt->prepare($expression);
        $stmt->execute();
    }

    protected function createAdapter(
        ?string $dsn = null,
        ?string $username = null,
        ?string $password = null
    ): void {
        $driver_options = [
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        if (is_null($dsn)) {
            $this->adapter = new Adapter([
                "driver" => getenv("DB_DRIVER"),
                "hostname" => getenv("DB_HOSTNAME"),
                "port" => getenv("DB_PORT"),
                "username" => $username ?? getenv("DB_USERNAME"),
                "password" => $password ?? getenv("DB_PASSWORD"),
                "database" => getenv("DB_DATABASE"),
                "driver_options" => $driver_options
            ]);
        } else {
            $pdo =  new PDO($dsn, $username, $password, $driver_options);
            $this->adapter = new Adapter(new LaminasPdo($pdo));
        }
    }
}

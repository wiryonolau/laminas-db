<?php

namespace Itseasy\Database\Console\Command;

use Exception;
use Itseasy\Database\Metadata\Source\Factory;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\Pdo\Pdo as LaminasPdo;
use Laminas\Db\Sql\PreparableSqlInterface;
use Laminas\Db\Sql\Sql;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
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
    protected $output;

    protected function configure(): void
    {
        $this->setHelp('Database data manager');
        $this->setDescription('Database data manager');

        $this->addOption("file", "f", InputOption::VALUE_REQUIRED, "Php file, must return array of PreparableSqlInterface");
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
        $this->output = $output;
        $data_file = realpath(APP_DIR . DIRECTORY_SEPARATOR . $input->getOption("file"));
        $dsn = $input->getOption("dsn");
        $username = $input->getOption("username");
        $password = $input->getOption("password");
        $disableFk = $input->getOption("disable-fk", false);
        $useTransaction = $input->getOption("use-tx", false);

        $this->createAdapter($dsn, $username, $password);
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
                        $output->writeln("COMMIT\n");
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
            try {
                if (is_callable($query)) {
                    $query = $query();
                }


                if (!$query instanceof PreparableSqlInterface) {
                    throw new Exception("Invalid sql object");
                }

                $statement = $sql->prepareStatementForSqlObject($query);
                $this->output->writeln($statement->getSql());
                $this->output->writeln(print_r($query->getRawState()["values"], true));
                $statement->execute();
            } catch (Throwable $t) {
                $this->output->writeln(sprintf("<comment>%s</comment>\n", $t->getMessage()));
            }
        }
    }

    protected function executeExpression(string $expression): void
    {
        $this->output->writeln($expression . "\n");
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
                "driver" => get_env("DB_DRIVER", "PdoMysql"),
                "hostname" => get_env("DB_HOSTNAME", "localhost"),
                "port" => get_env("DB_PORT", 3306),
                "username" => $username ?? get_env("DB_USERNAME", ""),
                "password" => $password ?? get_docker_secret("DB_PASSWORD", get_env("DB_PASSWORD", ""), true),
                "database" => get_env("DB_DATABASE", ""),
                "driver_options" => $driver_options
            ]);
        } else {
            $pdo =  new PDO($dsn, $username, $password, $driver_options);
            $this->adapter = new Adapter(new LaminasPdo($pdo));
        }
    }
}

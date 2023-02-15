<?php

namespace Itseasy\Database\Console\Command;

use Itseasy\Database\Metadata\Source\Factory;
use Itseasy\Database\Sql\Ddl\SchemaDiff;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\Driver\Pdo\Pdo as LaminasPdo;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use PDO;

class SchemaCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected static $defaultName = "db:diff";
    protected $db;
    protected $adapter;
    protected $output;

    protected function configure(): void
    {
        $this->setHelp('Database schema manager');
        $this->setDescription('Database schema manager');

        $this->addOption("file", "f", InputOption::VALUE_REQUIRED, "Php metadata file, must return a metadata array");
        $this->addOption("username", "u", InputOption::VALUE_REQUIRED, "Connection username");
        $this->addOption("password", "p", InputOption::VALUE_REQUIRED, "Connection password");
        $this->addOption("dsn", null, InputOption::VALUE_REQUIRED, implode("\n", [
            "Connection DSN, Must be active Database and User",
            "Use of environment also supported",
            " DB_DRIVER, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD"
        ]));
        $this->addOption("disable-fk", null, InputOption::VALUE_NONE, "Disable foreign key check");
        $this->addOption("apply", "a", InputOption::VALUE_NONE, "Apply diff");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $schema_file = realpath(APP_DIR . DIRECTORY_SEPARATOR . $input->getOption("file"));
        $dsn = $input->getOption("dsn");
        $username = $input->getOption("username");
        $password = $input->getOption("password");
        $apply = $input->getOption("apply");
        $disableFk = $input->getOption("disable-fk", false);

        $this->createAdapter($dsn, $username, $password);
        $schema = include $schema_file;

        $ddls = SchemaDiff::diff($schema, $this->adapter);

        $platform = $this->adapter->getPlatform()->getName();

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

        foreach ($ddls as $ddl) {
            $output->writeln(trim($ddl) . "\n");

            if ($apply) {
                $this->adapter->query($ddl, Adapter::QUERY_MODE_EXECUTE);
            }
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

        return Command::SUCCESS;
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

<?php

namespace Itseasy\Database\Console\Command;

use Itseasy\Database\Sql\Ddl\SchemaDiff;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\Driver\Pdo\Pdo as LaminasPdo;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use PDO;

class SchemaCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected static $defaultName = "db:diff";
    protected $db;

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
        $this->addOption("apply", "a", InputOption::VALUE_NONE, "Apply diff");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema_file = realpath(APP_DIR.DIRECTORY_SEPARATOR.$input->getOption("file"));
        $dsn = $input->getOption("dsn");
        $username = $input->getOption("username");
        $password = $input->getOption("password");
        $apply = $input->getOption("apply");

        $adapter = $this->createAdapter($dsn, $username, $password);
        $schema = include $schema_file;

        $ddls = SchemaDiff::diff($schema, $adapter);

        foreach ($ddls as $ddl) {
            $output->writeln(trim($ddl) . "\n");

            if ($apply) {
                $adapter->query($ddl, Adapter::QUERY_MODE_EXECUTE);
            }
        }

        return Command::SUCCESS;
    }

    protected function createAdapter(
        ?string $dsn = null,
        ?string $username = null,
        ?string $password = null
    ): AdapterInterface {
        $driver_options = [
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        if (is_null($dsn)) {
            return new Adapter([
                "driver" => getenv("DB_DRIVER"),
                "hostname" => getenv("DB_HOSTNAME"),
                "port" => getenv("DB_PORT"),
                "username" => $username ?? getenv("DB_USERNAME"),
                "password" => $password ?? getenv("DB_PASSWORD"),
                "database" => getenv("DB_DATABASE"),
                "driver_options" => $driver_options
            ]);
        }

        $pdo =  new PDO($dsn, $username, $password, $driver_options);
        return new Adapter(new LaminasPdo($pdo));
    }
}

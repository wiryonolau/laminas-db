<?php

namespace Itseasy\Database\Sql\Ddl\Privilege;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\SqlInterface;
use Laminas\Db\Sql\TableIdentifier;

class MysqlGrantPrivilege extends AbstractSql implements SqlInterface
{
    const PRIVILEGE = "privilege";
    const DBOBJECT = "dbobject";
    const USER = "user";

    protected $privilege;
    protected $schema;
    protected $object;
    protected $user;
    protected $host;

    protected $all_privilege = false;
    protected $all_host = false;
    protected $all_object = false;

    protected $specifications = [
        self::PRIVILEGE => "GRANT %1\$s",
        self::DBOBJECT => "ON `%1\$s`.%2\$s",
        self::USER => "TO '%1\$s'@'%2\$s'"
    ];

    public function __construct(string $schema, string $object, string $user, string $host, string $privilege = null)
    {
        $this->privilege = (is_null($privilege) ? "ALL PRIVILEGES" : $privilege);
        $this->schema = $schema;
        $this->object = $object;
        $this->user = $user;
        $this->host = $host;

        if ($this->object == "*") {
            $this->all_object = true;
        }
    }

    public function setAllPrivilege($all_privilege = false) : void
    {
        $this->all_privilege = (bool) $all_privilege;
    }

    public function setAllHost($all_host = false) : void
    {
        $this->all_host = (bool) $all_host;
    }

    public function setAllObject($all_object = false) : void
    {
        $this->all_object = (bool) $all_object;
    }

    protected function processPrivilege(PlatformInterface $adapterPlatform = null) : array
    {
        return [
            ($this->all_privilege ? "ALL PRIVILEGES" : strtoupper($this->privilege))
        ];
    }

    protected function processDbObject(PlatformInterface $adapterPlatform = null) : array
    {
        return [
            $this->schema,
            ($this->all_object ? "*" : sprintf("`%s`", $this->object))
        ];
    }

    protected function processUser(PlatformInterface $adapterPlatform = null) : array
    {
        return [
            $this->user,
            ($this->all_host ? "%" : $this->host)
        ];
    }
}

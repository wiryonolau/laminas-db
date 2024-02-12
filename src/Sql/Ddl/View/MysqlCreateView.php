<?php

namespace Itseasy\Database\Sql\Ddl\View;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Metadata\Object\ViewObject;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;

class MysqlCreateView extends AbstractSql implements SqlInterface
{
    const VIEW = "view";

    protected $view;

    // CREATE
    // [OR REPLACE]
    // [ALGORITHM = {UNDEFINED | MERGE | TEMPTABLE}]
    // [DEFINER = user]
    // [SQL SECURITY { DEFINER | INVOKER }]
    // VIEW view_name [(column_list)]
    // AS select_statement
    // [WITH [CASCADED | LOCAL] CHECK OPTION]

    protected $specifications = [
        self::VIEW => <<<EOF
            CREATE OR REPLACE 
            DEFINER=CURRENT_USER 
            VIEW %1\$s 
            AS %2\$s 
        EOF
    ];

    public function __construct(
        ViewObject $view
    ) {
        $this->view = $view;
    }

    protected function processView(PlatformInterface $adapterPlatform = null): array
    {
        return [
            $this->view->getName(),
            $this->view->getViewDefinition()
        ];
    }
}

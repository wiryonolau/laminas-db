<?php

namespace Itseasy\Database\Sql\Ddl\View;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Metadata\Object\ViewObject;
use Laminas\Db\Sql\AbstractSql;

class CreateView extends AbstractSql implements SqlInterface
{
    const VIEW = "view";

    protected $routine;

    // CREATE
    // [OR REPLACE]
    // [ALGORITHM = {UNDEFINED | MERGE | TEMPTABLE}]
    // [DEFINER = user]
    // [SQL SECURITY { DEFINER | INVOKER }]
    // VIEW view_name [(column_list)]
    // AS select_statement
    // [WITH [CASCADED | LOCAL] CHECK OPTION]

    // CREATE [ OR REPLACE ] [ TEMP | TEMPORARY ] [ RECURSIVE ] VIEW name [ ( column_name [, ...] ) ]
    // [ WITH ( view_option_name [= view_option_value] [, ... ] ) ]
    // AS query
    // [ WITH [ CASCADED | LOCAL ] CHECK OPTION ]

    protected $specifications = [
        self::VIEW => <<<EOF
            CREATE OR REPLACE %1\$s 
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

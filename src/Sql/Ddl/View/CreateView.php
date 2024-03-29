<?php

namespace Itseasy\Database\Sql\Ddl\View;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Metadata\Object\ViewObject;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;

class CreateView extends AbstractSql implements SqlInterface
{
    const VIEW = "view";

    protected $view;

    protected $specifications = [
        self::VIEW => <<<EOF
            CREATE OR REPLACE 
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

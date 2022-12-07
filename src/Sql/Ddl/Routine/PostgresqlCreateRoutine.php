<?php

namespace Itseasy\Database\Sql\Ddl\Routine;

use Itseasy\Database\Metadata\Object\RoutineObject;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\AbstractSql;
use Laminas\Db\Sql\Ddl\SqlInterface;


class PostgresqlCreateRoutine extends AbstractSql implements SqlInterface
{
    const ROUTINE = "routine";

    protected $routine;

    //     CREATE [ OR REPLACE ] FUNCTION
    //     name ( [ [ argmode ] [ argname ] argtype [ { DEFAULT | = } default_expr ] [, ...] ] )
    //     [ RETURNS rettype
    //       | RETURNS TABLE ( column_name column_type [, ...] ) ]
    //   { LANGUAGE lang_name
    //     | TRANSFORM { FOR TYPE type_name } [, ... ]
    //     | WINDOW
    //     | { IMMUTABLE | STABLE | VOLATILE }
    //     | [ NOT ] LEAKPROOF
    //     | { CALLED ON NULL INPUT | RETURNS NULL ON NULL INPUT | STRICT }
    //     | { [ EXTERNAL ] SECURITY INVOKER | [ EXTERNAL ] SECURITY DEFINER }
    //     | PARALLEL { UNSAFE | RESTRICTED | SAFE }
    //     | COST execution_cost
    //     | ROWS result_rows
    //     | SUPPORT support_function
    //     | SET configuration_parameter { TO value | = value | FROM CURRENT }
    //     | AS 'definition'
    //     | AS 'obj_file', 'link_symbol'
    //     | sql_body
    //   } ...

    protected $specifications = [
        self::ROUTINE => <<<EOF
            CREATE OR REPLACE %1\$s %2\$s 
            RETURNS %3\$s 
            LANGUAGE %4\$s"
            AS %5\$s
        EOF
    ];

    public function __construct(
        RoutineObject $routine
    ) {
        $this->routine = $routine;
    }

    protected function processRoutine(PlatformInterface $adapterPlatform = null): array
    {
        return [
            $this->routine->getType(),
            $this->routine->getName(),
            $this->routine->getDataType(),
            $this->routine->getExternalLanguage(),
            $this->routine->getDefinition()
        ];
    }
}

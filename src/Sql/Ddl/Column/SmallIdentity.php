<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Ddl\Column;

class SmallIdentity extends Identity implements PostgreColumnInterface
{
    protected $type = 'SMALLINT';
}

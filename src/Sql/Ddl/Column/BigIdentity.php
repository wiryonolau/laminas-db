<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Ddl\Column;

class BigIdentity extends Identity implements PostgreColumnInterface
{
    protected $type = 'BIGINT';
}

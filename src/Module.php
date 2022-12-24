<?php

namespace Itseasy\Database;

class Module
{
    public static function getConfigPath(): array
    {
        return [
            __DIR__ . "/../config/*.{local,config}.php"
        ];
    }
}

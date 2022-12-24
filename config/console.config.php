<?php

namespace Itseasy\Database\Console;

return [
    "console" => [
        "commands" => [
            Command\DatabaseCommand::class,
        ],
        "factories" => [
            Command\DatabaseCommand::class => Command\Factory\DatabaseCommandFactory::class
        ]
    ]
];

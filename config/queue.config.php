<?php

namespace Itseasy\Queue;

return [
    "queue" => [
        "hosts" => [
        ],
        "options" => [
        ],
        "callback" => Callback\ServiceCallback::class,
        "set_close_on_destruct" => false,
        "channels" => [
            [
                "queue" => "default",
                "passive" => false,
                "durable" => true,
                "exclusive" => false,
                "auto_delete" => true,
                "nowait" => false,
                "arguments"=> [],
                "ticket" => null
            ]
        ]
    ]
];

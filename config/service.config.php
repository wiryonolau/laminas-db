<?php

namespace Itseasy\Queue;

return [
    "service" => [
        "factories" => [
            Service\QueueService::class => Service\Factory\QueueServiceFactory::class,
            Callback\ServiceCallback::class => Callback\Factory\ServiceCallbackFactory::class,
            Logger\DefaultLogger::class => Logger\Factory\DefaultLoggerFactory::class
        ]
    ]
];

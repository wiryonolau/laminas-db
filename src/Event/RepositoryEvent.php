<?php

declare(strict_types=1);

namespace Itseasy\Database\Event;

use Laminas\EventManager\Event;

class RepositoryEvent extends Event
{
    protected $table;
    protected $arguments;
    protected $success = false;

    public function __construct($target)
    {
        $this->setName("repository");
        $this->setTarget($target);
    }

    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function addArgument(string $key, $value): void
    {
        $this->arguments[$key] = $value;
    }

    public function setArguments(array $arguments = []): void
    {
        $this->arguments = $arguments;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setIsSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}

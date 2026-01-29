<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Utils;

final class FakeQueryFactory
{
    public ?object $lastBuilder = null;

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data = []): FakeInsertBuilder
    {
        $b = new FakeInsertBuilder($table, $data);
        $this->lastBuilder = $b;
        return $b;
    }

    /** @param array<string, mixed> $data */
    public function update(string $table, array $data = []): FakeUpdateBuilder
    {
        $b = new FakeUpdateBuilder($table, $data);
        $this->lastBuilder = $b;
        return $b;
    }

    public function delete(string $table): FakeDeleteBuilder
    {
        $b = new FakeDeleteBuilder($table);
        $this->lastBuilder = $b;
        return $b;
    }
}

final class FakeInsertBuilder
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $table,
        public array $data,
    ) {
    }

    public function execute(): int
    {
        return 1;
    }
}

final class FakeUpdateBuilder
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $table,
        public array $data,
    ) {
    }

    public ?string $whereSql = null;

    /** @var array<string, mixed> */
    public array $whereParams = [];

    /** @param array<string, mixed> $params */
    public function where(string $sql, array $params = []): self
    {
        $this->whereSql = $sql;
        $this->whereParams = $params;
        return $this;
    }

    public function execute(): int
    {
        return 1;
    }
}

final class FakeDeleteBuilder
{
    public function __construct(
        public string $table,
    ) {
    }

    public ?string $whereSql = null;

    /** @var array<string, mixed> */
    public array $whereParams = [];

    /** @param array<string, mixed> $params */
    public function where(string $sql, array $params = []): self
    {
        $this->whereSql = $sql;
        $this->whereParams = $params;
        return $this;
    }

    public function execute(): int
    {
        return 1;
    }
}

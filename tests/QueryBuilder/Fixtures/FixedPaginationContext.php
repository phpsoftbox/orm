<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures;

use PhpSoftBox\Pagination\Contracts\PaginationContextResolverInterface;

/**
 * Контекст пагинации с фиксированными значениями (вместо HTTP-запроса).
 */
final readonly class FixedPaginationContext implements PaginationContextResolverInterface
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        private string $path,
        private array $query,
        private ?int $page,
        private ?int $perPage,
    ) {
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function fragment(): ?string
    {
        return null;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function page(): ?int
    {
        return $this->page;
    }

    public function perPage(): ?int
    {
        return $this->perPage;
    }

    public function pageParam(): string
    {
        return 'page';
    }
}

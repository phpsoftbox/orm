<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\ManyToOne;

#[Entity(table: 'authors2')]
final class AuthorWithCompany implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        #[Column(type: 'string')]
        public string $name,

        #[Column(name: 'company_id', type: 'int')]
        public int $companyId,

        #[ManyToOne(targetEntity: Company::class, joinColumn: 'companyId')]
        public ?Company $company = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}


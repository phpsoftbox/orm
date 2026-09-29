<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\FixturesKeys;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsTo;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'key_books')]
final class KeyBook implements EntityInterface
{
    /**
     * Ключи заданы именами колонок.
     */
    #[BelongsTo(targetEntity: KeyAuthor::class, joinColumn: 'author_code', referencedColumn: 'external_code')]
    public ?KeyAuthor $author = null;

    /**
     * Те же ключи заданы именами свойств.
     */
    #[BelongsTo(targetEntity: KeyAuthor::class, joinColumn: 'authorCode', referencedColumn: 'externalCode')]
    public ?KeyAuthor $authorByProperty = null;

    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(name: 'author_code', type: 'string')]
        public string $authorCode,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}

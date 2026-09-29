<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\FixturesKeys;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

/**
 * Автор, связи которого строятся не по id, а по внешнему коду (колонка `external_code`).
 */
#[Entity(table: 'key_authors')]
final class KeyAuthor implements EntityInterface
{
    /**
     * @var EntityCollection<KeyBook>|null
     */
    #[HasMany(targetEntity: KeyBook::class, foreignKey: 'author_code', localKey: 'external_code')]
    public ?EntityCollection $books = null;

    /**
     * @var EntityCollection<KeyTag>|null
     */
    #[BelongsToMany(
        targetEntity: KeyTag::class,
        pivotTable: 'key_author_tags',
        foreignPivotKey: 'author_code',
        relatedPivotKey: 'tag_id',
        parentKey: 'external_code',
    )]
    public ?EntityCollection $tags = null;

    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(name: 'external_code', type: 'string')]
        public string $externalCode,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}

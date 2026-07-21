<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting\Fixtures;

use PhpSoftBox\DataCasting\Attributes\JsonCollectionOf;
use PhpSoftBox\DataCasting\Attributes\JsonFactory;
use PhpSoftBox\DataCasting\Attributes\JsonMapOf;
use PhpSoftBox\DataCasting\Contracts\JsonValueFactoryInterface;
use PhpSoftBox\DataCasting\JsonHydrationContext;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'json_object_entities')]
final class JsonObjectEntity
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'json', nullable: true)]
        public ?EntityMetadata $metadata,
        #[Column(type: 'json')]
        #[JsonCollectionOf(EntityItemMetadata::class)]
        public array $items,
        #[Column(type: 'json')]
        #[JsonMapOf(EntityItemMetadata::class)]
        public array $itemsByCode,
        #[Column(type: 'json')]
        public array $plainPayload,
        #[Column(name: 'metadata_type')]
        public string $metadataType,
        #[Column(type: 'json')]
        #[JsonFactory(EntityVariantFactory::class)]
        public EntityVariant $variant,
    ) {
    }
}

final readonly class EntityMetadata
{
    public function __construct(
        public string $name,
        public ?EntityNestedMetadata $nested,
    ) {
    }
}

final readonly class EntityNestedMetadata
{
    public function __construct(
        public bool $enabled,
    ) {
    }
}

final readonly class EntityItemMetadata
{
    public function __construct(
        public string $code,
    ) {
    }
}

interface EntityVariant
{
}

final readonly class FboEntityVariant implements EntityVariant
{
    public function __construct(
        public string $code,
    ) {
    }
}

final readonly class FbsEntityVariant implements EntityVariant
{
    public function __construct(
        public string $code,
    ) {
    }
}

final class EntityVariantFactory implements JsonValueFactoryInterface
{
    public function create(array $data, JsonHydrationContext $context): object
    {
        $class = $context->source['metadata_type'] === 'fbs'
            ? FbsEntityVariant::class
            : FboEntityVariant::class;

        return new $class($data['code']);
    }
}

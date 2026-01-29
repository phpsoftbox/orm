<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostBelongsTo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMetadataProvider::class)]
final class NamingConventionMetadataTest extends TestCase
{
    /**
     * Проверяет соглашение: свойства сущности camelCase, колонки БД snake_case.
     * Важно: joinColumn в BelongsTo/ManyToOne — это имя свойства сущности (authorId),
     * а имя колонки БД берётся из #[Column(name: ...)] и может быть author_id.
     */
    #[Test]
    public function joinColumnIsEntityPropertyAndColumnNameIsSnakeCase(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);
        $provider = new AttributeMetadataProvider(namingConvention: $config->namingConvention);
        $meta = $provider->for(PostBelongsTo::class);

        self::assertArrayHasKey('authorId', $meta->columns);
        self::assertSame('author_id', $meta->columns['authorId']->column);

        self::assertArrayHasKey('author', $meta->relations);
        self::assertSame('authorId', $meta->relations['author']->joinColumn);
    }
}

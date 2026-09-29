<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\Hook;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use RuntimeException;

#[Entity(table: 'hook_entities')]
#[Hook(callable: [self::class, 'failOnCreate'], events: [OnCreate::class])]
final class ThrowingHookEntity implements EntityInterface
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'auto')]
        #[Column(type: 'int')]
        public ?int $id = null,
        #[Column(type: 'string')]
        public string $name = '',
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public static function failOnCreate(OnCreate $event): void
    {
        throw new RuntimeException('Hook failed.');
    }
}

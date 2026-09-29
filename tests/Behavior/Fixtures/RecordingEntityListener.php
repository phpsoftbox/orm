<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;

/**
 * Listener сущности, который запоминает классы сущностей из полученных событий.
 */
final class RecordingEntityListener
{
    /**
     * @var list<class-string>
     */
    public static array $entities = [];

    #[Listen(OnCreate::class)]
    public function onCreate(OnCreate $event): void
    {
        self::$entities[] = $event->entity()::class;
    }
}

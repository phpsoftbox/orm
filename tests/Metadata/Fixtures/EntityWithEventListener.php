<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\EventListener;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;

#[Entity(table: 't')]
#[EventListener(listener: DummyListener::class)]
final class EntityWithEventListener
{
}

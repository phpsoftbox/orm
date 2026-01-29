<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\EventListener;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\DummyListener;

#[Entity(table: 't')]
#[EventListener(listener: DummyListener::class)]
final class EntityWithEventListener
{
}

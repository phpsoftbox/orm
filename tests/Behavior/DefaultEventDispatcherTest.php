<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use PhpSoftBox\Orm\Behavior\Command\MutableEntityState;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\Behavior\ListenerMethodResolver;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\CountingAttributeListener;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\CountingConventionListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultEventDispatcher::class)]
#[CoversClass(ListenerMethodResolver::class)]
#[CoversMethod(DefaultEventDispatcher::class, 'dispatch')]
#[CoversMethod(ListenerMethodResolver::class, 'invoke')]
final class DefaultEventDispatcherTest extends TestCase
{
    /**
     * Проверим, что метод `onCreate(OnCreate)` с #[Listen(OnCreate::class)] вызывается один раз,
     * а не дважды (по атрибуту и по соглашению имени).
     *
     * @see DefaultEventDispatcher::dispatch()
     * @see ListenerMethodResolver::invoke()
     */
    #[Test]
    public function attributeListenerIsCalledOnce(): void
    {
        $listener = new CountingAttributeListener();

        new DefaultEventDispatcher([$listener])->dispatch($this->event());

        self::assertSame(1, $listener->calls);
    }

    /**
     * Проверим, что метод `on*(Event)` без #[Listen] по-прежнему вызывается по соглашению имени.
     *
     * @see DefaultEventDispatcher::dispatch()
     * @see ListenerMethodResolver::invoke()
     */
    #[Test]
    public function conventionListenerIsCalledWithoutAttribute(): void
    {
        $listener = new CountingConventionListener();

        new DefaultEventDispatcher([$listener])->dispatch($this->event());

        self::assertSame(1, $listener->calls);
    }

    private function event(): OnCreate
    {
        return new OnCreate(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(EntityInterface::class),
            new MutableEntityState([]),
        );
    }
}

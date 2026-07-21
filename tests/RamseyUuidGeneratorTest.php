<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Orm\Uuid\RamseyUuidGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(RamseyUuidGenerator::class)]
final class RamseyUuidGeneratorTest extends TestCase
{
    /**
     * Проверяет, что генератор UUID по умолчанию создаёт UUIDv7.
     */
    #[Test]
    public function generatesUuid7(): void
    {
        $uuid = new RamseyUuidGenerator()->generate();

        self::assertInstanceOf(UuidInterface::class, $uuid);
        self::assertSame(7, $uuid->getFields()->getVersion());
    }
}

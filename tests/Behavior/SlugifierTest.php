<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use PhpSoftBox\Orm\Behavior\Slugifier;
use PHPUnit\Framework\TestCase;

final class SlugifierTest extends TestCase
{
    public function testSlugifyTransliteratesCyrillic(): void
    {
        $slugifier = new Slugifier();

        self::assertSame('pervaya-liniya', $slugifier->slugify('Первая линия'));
        self::assertSame('otdelnyy-vhod', $slugifier->slugify('Отдельный вход'));
    }
}

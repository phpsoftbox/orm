<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting\Fixtures;

use DateTimeImmutable;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\TypeCasting\Options\DatetimeCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\JsonCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\JsonInvalidPolicy;

#[Entity(table: 'options')]
final class OptionsEntity
{
    #[Column(type: 'datetime', options: new DatetimeCastOptions(formatTo: 'Y-m-d H:i:s', formatFrom: 'Y-m-d H:i:s', dateTimeClass: DateTimeImmutable::class))]
    public DateTimeImmutable $created;

    #[Column(type: 'json', options: new JsonCastOptions(jsonEncodeFlags: 0, invalidJson: JsonInvalidPolicy::Throw))]
    public array $payload;
}

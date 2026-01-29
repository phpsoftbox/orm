<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\EncryptorInterface;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeHandlerInterface;

use function is_string;

/**
 * Примитивный encrypted handler.
 *
 * Требует внедрённый EncryptorInterface и опцию key.
 */
final readonly class EncryptedHandler implements OrmTypeHandlerInterface
{
    public function __construct(
        private EncryptorInterface $encryptor,
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === 'encrypted';
    }

    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null) {
            return null;
        }

        $key = $options['key'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException('Encrypted cast requires option "key".');
        }

        return $this->encryptor->encrypt((string) $value, $key);
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $key = $options['key'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException('Encrypted cast requires option "key".');
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Encrypted value must be string|null.');
        }

        return $this->encryptor->decrypt($value, $key);
    }

    public function cast(mixed $value): mixed
    {
        throw new InvalidArgumentException('Encrypted handler requires castFrom/castTo with options.');
    }
}

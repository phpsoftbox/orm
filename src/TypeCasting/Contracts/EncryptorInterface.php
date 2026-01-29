<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Contracts;

interface EncryptorInterface
{
    public function encrypt(string $plaintext, string $key): string;

    public function decrypt(string $ciphertext, string $key): string;
}

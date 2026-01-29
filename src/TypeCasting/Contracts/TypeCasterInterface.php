<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Contracts;

interface TypeCasterInterface
{
    /**
     * Возвращает список зарегистрированных handler'ов.
     *
     * @return list<TypeHandlerInterface>
     */
    public function handlers(): array;

    /**
     * Регистрирует handler.
     *
     * Handler будет использоваться, если `supports($type)` вернёт true.
     */
    public function registerHandler(TypeHandlerInterface $handler): void;

    /**
     * Приводит значение к типу (в PHP-направлении).
     *
     * @param string|class-string<TypeHandlerInterface> $type
     */
    public function cast(string $type, mixed $value): mixed;

    /**
     * Делает каст массива по конфигурации.
     *
     * Пример:
     *  $config = ['created' => 'datetime', 'id' => 'uuid', 'custom' => CustomHandler::class]
     *  $data = ['created' => '2022-01-01T00:00:00+00:00', 'id' => '...', 'custom' => '...']
     *
     * @param array<string, string|class-string<TypeHandlerInterface>> $config
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function castArray(array $config, array $data): array;
}

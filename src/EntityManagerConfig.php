<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm;

use PhpSoftBox\Inflector\Contracts\InflectorInterface;
use PhpSoftBox\Inflector\InflectorFactory;
use PhpSoftBox\Inflector\LanguageEnum;
use PhpSoftBox\Orm\Behavior\BuiltInListenersRegistry;
use PhpSoftBox\Orm\Contracts\BuiltInListenersRegistryInterface;
use PhpSoftBox\Orm\Metadata\Conventions\InflectorNamingConvention;
use PhpSoftBox\Orm\Metadata\Conventions\NamingConventionInterface;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;

/**
 * Конфигурация EntityManager.
 */
final class EntityManagerConfig
{
    public function __construct(
        /**
         * Включать ли встроенные listeners/behaviors ORM.
         */
        public bool $enableBuiltInListeners = true,
        /**
         * Кастомный реестр built-in listeners.
         *
         * Если null, будет использован BuiltInListenersRegistry.
         */
        public ?BuiltInListenersRegistryInterface $builtInListenersRegistry = null,
        /**
         * Инфлектор (singuar/plural + кейсы), используемый ORM для конвенций.
         *
         * Если null — будет создан дефолтный EN-инфлектор из пакета phpsoftbox/inflector.
         */
        public ?InflectorInterface $inflector = null,
        /**
         * Соглашения (conventions) именования.
         *
         * Если null — будет создан InflectorNamingConvention на основе $inflector.
         */
        public ?NamingConventionInterface $namingConvention = null,
    ) {
        $this->inflector ??= InflectorFactory::create(LanguageEnum::EN);

        $this->namingConvention ??= new InflectorNamingConvention($this->inflector);
    }

    public function resolveBuiltInRegistry(
        MetadataProviderInterface $metadata,
    ): BuiltInListenersRegistryInterface {
        return $this->builtInListenersRegistry ?? new BuiltInListenersRegistry($metadata);
    }
}

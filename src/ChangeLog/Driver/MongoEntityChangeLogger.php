<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog\Driver;

use MongoDB\BSON\UTCDateTime;
use PhpSoftBox\Orm\ChangeLog\ChangeLogDocumentBuilder;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use RuntimeException;

use function class_exists;
use function is_object;
use function method_exists;
use function trim;

final readonly class MongoEntityChangeLogger implements EntityChangeLoggerInterface
{
    public function __construct(
        private object $mongo,
        private string $collection = 'entity_changelog',
        private string $connection = 'default',
        private ChangeLogDocumentBuilder $documentBuilder = new ChangeLogDocumentBuilder(),
        private ?bool $isMongoExtensionAvailable = null,
    ) {
    }

    public function log(EntityChangeRecord $record): void
    {
        if (!$this->isMongoExtensionAvailable()) {
            throw new RuntimeException('MongoEntityChangeLogger requires ext-mongodb (MongoDB\\BSON\\UTCDateTime is unavailable).');
        }

        $collection = $this->collection();
        $collection->insertOne($this->documentBuilder->build($record));
    }

    private function collection(): object
    {
        if (!method_exists($this->mongo, 'collection')) {
            throw new RuntimeException('MongoEntityChangeLogger expects a mongo manager with collection(string, string) method.');
        }

        $collection = trim($this->collection);
        if ($collection === '') {
            $collection = 'entity_changelog';
        }

        $mongoCollection = $this->mongo->collection($collection, $this->connection);
        if (!is_object($mongoCollection) || !method_exists($mongoCollection, 'insertOne')) {
            throw new RuntimeException('MongoEntityChangeLogger expects a collection object with insertOne(array $document) method.');
        }

        return $mongoCollection;
    }

    private function isMongoExtensionAvailable(): bool
    {
        return $this->isMongoExtensionAvailable ?? class_exists(UTCDateTime::class);
    }
}

<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Persistence;

use PDO;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Contracts\DriverInterface;
use PhpSoftBox\Database\Dsn\Dsn;
use PhpSoftBox\Database\IsolationLevelEnum;
use PhpSoftBox\Database\QueryBuilder\CompiledQuery;
use PhpSoftBox\Database\QueryBuilder\Compiler\QueryCompilerInterface;
use PhpSoftBox\Database\QueryBuilder\DeleteQueryBuilder;
use PhpSoftBox\Database\QueryBuilder\InsertQueryBuilder;
use PhpSoftBox\Database\QueryBuilder\QueryFactory;
use PhpSoftBox\Database\QueryBuilder\Quoting\QuoterInterface;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use PhpSoftBox\Database\QueryBuilder\UpdateQueryBuilder;
use PhpSoftBox\Database\SchemaBuilder\SchemaBuilderInterface;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Exception\EntityPersistException;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\AutoIdEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(DefaultEntityPersister::class)]
final class DefaultEntityPersisterInsertFailureTest extends TestCase
{
    /**
     * Проверяет, что persister сообщает об ошибке, если auto-id не был присвоен после insert.
     */
    #[Test]
    public function insertThrowsWhenGeneratedIdMissing(): void
    {
        $connection = new FakeConnection(new FakePdo());

        $metadata = new AttributeMetadataProvider();

        $mapper = new AutoEntityMapper(
            metadata: $metadata,
            typeCaster: new DefaultTypeCasterFactory()->create(),
            optionsManager: new TypeCastOptionsManager(),
        );

        $persister = new DefaultEntityPersister($connection, $metadata, $mapper);

        $entity = new AutoIdEntity(name: 'Test');

        $this->expectException(EntityPersistException::class);

        $persister->insert($entity);
    }
}

final class FakePdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    public function lastInsertId($name = null): string
    {
        return '';
    }
}

final class FakeConnection implements ConnectionInterface
{
    private readonly QueryFactory $queryFactory;
    private readonly SchemaBuilderInterface $schema;
    private readonly DriverInterface $driver;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->driver = new FakeDriver();

        $this->queryFactory = new QueryFactory($this);

        $this->schema = new class () implements SchemaBuilderInterface {
            public function create(string $table, callable $definition, bool $ifNotExists = true): void
            {
            }

            public function createIfNotExists(string $table, callable $definition): void
            {
            }

            public function alterTable(string $table, callable $definition): void
            {
            }

            public function addColumn(string $table, callable $definition): void
            {
            }

            public function dropIfExists(string $table): void
            {
            }

            public function drop(string $table): void
            {
            }

            public function createExtensionIfNotExists(string $extension): void
            {
            }

            public function dropExtensionIfExists(string $extension): void
            {
            }

            public function renameTable(string $from, string $to): void
            {
            }
        };
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        return null;
    }

    public function execute(string $sql, array $params = []): int
    {
        return 1;
    }

    public function transaction(callable $fn, ?IsolationLevelEnum $isolationLevel = null): mixed
    {
        return $fn($this);
    }

    public function lastInsertId(?string $name = null): string
    {
        return '';
    }

    public function prefix(): string
    {
        return '';
    }

    public function table(string $name): string
    {
        return $name;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->driver->createQuoter()->ident($identifier);
    }

    public function quoteTable(string $table): string
    {
        return $this->driver->createQuoter()->dotted($this->table($table));
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function schema(): SchemaBuilderInterface
    {
        return $this->schema;
    }

    public function logger(): ?LoggerInterface
    {
        return null;
    }

    public function query(): QueryFactory
    {
        return $this->queryFactory;
    }

    public function driver(): DriverInterface
    {
        return $this->driver;
    }
}

final class FakeDriver implements DriverInterface
{
    public function name(): string
    {
        return 'sqlite';
    }

    public function pdoDsn(Dsn $dsn): string
    {
        return 'sqlite::memory:';
    }

    public function validate(Dsn $dsn): void
    {
    }

    public function defaultPdoOptions(): array
    {
        return [];
    }

    public function createQuoter(): QuoterInterface
    {
        return new class () implements QuoterInterface {
            public function ident(string $ident): string
            {
                return $ident;
            }

            public function dotted(string $ident): string
            {
                return $ident;
            }

            public function alias(string $alias): string
            {
                return $alias;
            }
        };
    }

    public function createQueryCompiler(): QueryCompilerInterface
    {
        return new class () implements QueryCompilerInterface {
            public function compileSelect(SelectQueryBuilder $builder): CompiledQuery
            {
                return new CompiledQuery('SELECT 1', []);
            }

            public function compileInsert(InsertQueryBuilder $builder): CompiledQuery
            {
                return new CompiledQuery('INSERT 1', []);
            }

            public function compileUpdate(UpdateQueryBuilder $builder): CompiledQuery
            {
                return new CompiledQuery('UPDATE 1', []);
            }

            public function compileDelete(DeleteQueryBuilder $builder): CompiledQuery
            {
                return new CompiledQuery('DELETE 1', []);
            }
        };
    }
}

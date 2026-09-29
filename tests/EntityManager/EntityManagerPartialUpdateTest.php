<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\UpdateStateListener;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\ContactEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
#[CoversClass(UnitOfWork::class)]
#[CoversMethod(EntityManager::class, 'flush')]
#[CoversMethod(UnitOfWork::class, 'changedFields')]
final class EntityManagerPartialUpdateTest extends TestCase
{
    /**
     * Проверим, что UPDATE пишет только изменённые колонки: параллельная правка разных полей
     * в двух EntityManager не теряет ни одно из изменений.
     *
     * @see EntityManager::flush()
     * @see UnitOfWork::changedFields()
     */
    #[Test]
    public function concurrentEditsOfDifferentColumnsAreBothKept(): void
    {
        $conn = $this->connection();

        $first  = new EntityManager(connection: $conn);
        $second = new EntityManager(connection: $conn);

        $contactA = $first->find(ContactEntity::class, 1);
        $contactB = $second->find(ContactEntity::class, 1);

        $contactA->name = 'Renamed';
        $first->persist($contactA);
        $first->flush();

        // Второй manager меняет другое поле по устаревшему состоянию.
        $contactB->email = 'new@example.com';
        $second->persist($contactB);
        $second->flush();

        $row = $conn->fetchOne('SELECT name, email FROM contacts WHERE id = 1');
        self::assertSame('Renamed', $row['name']);
        self::assertSame('new@example.com', $row['email']);
    }

    /**
     * Проверим, что колонка, зарегистрированная слушателем OnUpdate, попадает в UPDATE
     * вместе с изменёнными колонками сущности.
     *
     * @see EntityManager::flush()
     */
    #[Test]
    public function columnRegisteredByListenerIsWritten(): void
    {
        $conn = $this->connection();

        $em = new EntityManager(
            connection: $conn,
            events: new DefaultEventDispatcher([new UpdateStateListener(['email' => 'listener@example.com'])]),
        );

        $contact       = $em->find(ContactEntity::class, 1);
        $contact->name = 'Renamed';

        $em->persist($contact);
        $em->flush();

        $row = $conn->fetchOne('SELECT name, email FROM contacts WHERE id = 1');
        self::assertSame('Renamed', $row['name']);
        self::assertSame('listener@example.com', $row['email']);
    }

    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute('CREATE TABLE contacts (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL)');
        $conn->execute('INSERT INTO contacts (id, name, email) VALUES (1, \'Anton\', \'old@example.com\')');

        return $conn;
    }
}

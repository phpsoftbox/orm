<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Exception\EntityIdentityCollisionException;
use PhpSoftBox\Orm\UnitOfWork\EntityHeap;
use PhpSoftBox\Orm\UnitOfWork\EntityRuntimeRegistry;
use PhpSoftBox\Orm\UnitOfWork\EntityState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WeakReference;

use function gc_collect_cycles;

#[CoversClass(EntityHeap::class)]
final class EntityHeapTest extends TestCase
{
    #[Test]
    public function storesRuntimeStateOutsideEntity(): void
    {
        $heap   = new EntityHeap();
        $entity = $this->entity(10);
        $node   = $heap->getOrCreateNode($entity, EntityState::Managed);

        $node->markRelationLoaded('products');

        self::assertSame(EntityState::Managed, $node->state());
        self::assertTrue($node->isRelationLoaded('products'));
        self::assertTrue($node->loadedRelation('products')?->complete);
        self::assertSame($entity, $heap->find($entity::class, 10));
    }

    #[Test]
    public function indexesIdentityAssignedAfterEntityWasAttached(): void
    {
        $heap   = new EntityHeap();
        $entity = $this->entity(null);

        $heap->getOrCreateNode($entity, EntityState::New);
        $entity->id = 15;
        $heap->getOrCreateNode($entity, EntityState::Managed);

        self::assertSame($entity, $heap->find($entity::class, 15));
    }

    #[Test]
    public function rejectsSecondInstanceWithSameIdentity(): void
    {
        $heap    = new EntityHeap();
        $managed = $this->entity(10);
        $heap->getOrCreateNode($managed, EntityState::Managed);

        $this->expectException(EntityIdentityCollisionException::class);

        $heap->getOrCreateNode($this->entity(10), EntityState::Managed);
    }

    #[Test]
    public function doesNotKeepEntityAlive(): void
    {
        $heap      = new EntityHeap();
        $entity    = $this->entity(10);
        $class     = $entity::class;
        $reference = WeakReference::create($entity);

        $heap->getOrCreateNode($entity, EntityState::Managed);
        unset($entity);
        gc_collect_cycles();

        self::assertNull($reference->get());
        self::assertNull($heap->find($class, 10));
    }

    #[Test]
    public function sharedRuntimeRegistryResolvesStateByEntityInstanceAcrossHeaps(): void
    {
        $runtimeRegistry = new EntityRuntimeRegistry();

        $dispatcherHeap = new EntityHeap($runtimeRegistry);
        $tenantHeap     = new EntityHeap($runtimeRegistry);
        $dispatcher     = $this->entity(10);
        $tenant         = $this->entity(10);

        $dispatcherNode = $dispatcherHeap->getOrCreateNode($dispatcher, EntityState::Managed);
        $tenantNode     = $tenantHeap->getOrCreateNode($tenant, EntityState::Managed);
        $dispatcherNode->markRelationLoaded('permissions');
        $tenantNode->markRelationLoaded('products');

        self::assertSame($dispatcherNode, $runtimeRegistry->node($dispatcher));
        self::assertSame($tenantNode, $runtimeRegistry->node($tenant));
        self::assertTrue($runtimeRegistry->node($dispatcher)?->isRelationLoaded('permissions'));
        self::assertFalse($runtimeRegistry->node($dispatcher)?->isRelationLoaded('products'));
        self::assertTrue($runtimeRegistry->node($tenant)?->isRelationLoaded('products'));

        $tenantHeap->clear();

        self::assertSame($dispatcherNode, $runtimeRegistry->node($dispatcher));
        self::assertNull($runtimeRegistry->node($tenant));
    }

    private function entity(?int $id): EntityInterface
    {
        return new class ($id) implements EntityInterface {
            public function __construct(
                public ?int $id,
            ) {
            }

            public function id(): ?int
            {
                return $this->id;
            }
        };
    }
}

<?php

namespace Modera\FileRepositoryBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Injects a reference to service container to Repository entity whenever it is fetched
 * from database.
 *
 * @copyright 2014 Modera Foundation
 */
#[AsEntityListener(event: Events::postLoad, lazy: true, entity: Repository::class)]
#[AsEntityListener(event: Events::postLoad, lazy: true, entity: StoredFile::class)]
class ContainerInjectorListener
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function postLoad(Repository|StoredFile $entity, LifecycleEventArgs $event): void
    {
        $entity->init($this->container);
    }
}

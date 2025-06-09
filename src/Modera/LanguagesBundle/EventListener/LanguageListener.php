<?php

namespace Modera\LanguagesBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Modera\LanguagesBundle\Entity\Language;

/**
 * @copyright 2020 Modera Foundation
 */
#[AsEntityListener(event: Events::postPersist, lazy: true, entity: Language::class)]
#[AsEntityListener(event: Events::postUpdate, lazy: true, entity: Language::class)]
class LanguageListener
{
    public function postPersist(Language $entity, LifecycleEventArgs $args): void
    {
        $this->updateDefaultLanguage($entity, $args);
    }

    public function postUpdate(Language $entity, LifecycleEventArgs $args): void
    {
        $this->updateDefaultLanguage($entity, $args);
    }

    private function updateDefaultLanguage(Language $entity, LifecycleEventArgs $args): void
    {
        if ($entity->isDefault()) {
            $om = $args->getObjectManager();
            if ($om instanceof EntityManagerInterface) {
                $query = $om->createQuery(
                    \sprintf(
                        'UPDATE %s l SET l.isDefault = :status WHERE l.id != :id',
                        Language::class
                    )
                );
                $query->setParameter('status', false);
                $query->setParameter('id', $entity->getId());
                $query->execute();
            }
        }
    }
}

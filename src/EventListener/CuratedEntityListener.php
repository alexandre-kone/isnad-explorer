<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\CuratedEntity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Renseigne horodatage et attribution des entités curatées.
 *
 * Pourquoi `onFlush` et pas les callbacks `#[ORM\PrePersist]` / `#[ORM\PreUpdate]` :
 * dans `preUpdate`, Doctrine a déjà calculé le jeu de modifications, et un champ
 * qui n'y figure pas est purement ignoré. Il faut donc écrire les champs puis
 * demander le recalcul explicite du changeset.
 *
 * Sans curateur connecté — import, fixtures, commande console — l'attribution
 * reste nulle : la donnée n'a effectivement été saisie par personne.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class CuratedEntityListener
{
    public function __construct(private readonly Security $security)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $unitOfWork = $em->getUnitOfWork();

        $now = new \DateTimeImmutable();
        $curator = $this->currentCurator();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof CuratedEntity) {
                continue;
            }

            if (null === $entity->getCreatedAt()) {
                $entity->setCreatedAt($now);
            }
            if (null === $entity->getCreatedBy()) {
                $entity->setCreatedBy($curator);
            }

            // « recompute » et non « compute » : sur une insertion, computeChangeSet()
            // remplace le changeset déjà calculé et perd les associations dont la
            // cible n'a pas encore d'identité — ici person.period_id.
            $unitOfWork->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof CuratedEntity) {
                continue;
            }

            $entity->setUpdatedAt($now);
            $entity->setUpdatedBy($curator);

            $unitOfWork->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }
    }

    private function currentCurator(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}

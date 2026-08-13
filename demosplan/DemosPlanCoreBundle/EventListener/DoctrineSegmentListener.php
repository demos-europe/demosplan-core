<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use Doctrine\ORM\Event\OnFlushEventArgs;

class DoctrineSegmentListener
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(Segment::class);

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Segment) {
                continue;
            }

            $changeSet = $unitOfWork->getEntityChangeSet($entity);

            // Reset the deadline when the workflow place changes, unless this same update
            // already set a deadline explicitly (then the user's input wins).
            if (isset($changeSet['place']) && !isset($changeSet['deadline']) && null !== $entity->getDeadline()) {
                $entity->setDeadline(null);
                $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
            }
        }
    }
}

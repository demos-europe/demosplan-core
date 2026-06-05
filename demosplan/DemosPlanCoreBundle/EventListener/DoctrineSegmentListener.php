<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class DoctrineSegmentListener
{
    public function preUpdate(Segment $segment, PreUpdateEventArgs $args): void
    {
        // Convert it to a DateTime so Doctrine can persist it to the date column.
        if ($args->hasChangedField('deadline')) {
            $value = $args->getNewValue('deadline');
            if (is_string($value)) {
                $args->setNewValue('deadline', '' === $value ? null : new DateTime($value));
            }
        }

        // Reset the deadline when the workflow place changes, unless this same update
        // already set a deadline explicitly (then the user's input wins).
        if ($args->hasChangedField('place')
            && !$args->hasChangedField('deadline')
            && null !== $segment->getDeadline()
        ) {
            $args->setNewValue('deadline', null);
        }
    }
}

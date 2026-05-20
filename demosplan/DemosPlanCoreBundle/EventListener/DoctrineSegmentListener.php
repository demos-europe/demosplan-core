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
use Doctrine\ORM\Event\PreUpdateEventArgs;

class DoctrineSegmentListener
{
    public function preUpdate(Segment $segment, PreUpdateEventArgs $args): void
    {
        if ($args->hasChangedField('place')) {
            $args->setNewValue('deadline', null);
        }
    }
}


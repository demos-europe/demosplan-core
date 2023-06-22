<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Doctrine\Persistence\ManagerRegistry;

class MasterTemplateService
{
    /**
     * This was the static ProcedureId that was used to determine master template
     * Do not use this any more.
     *
     * @deprecated
     */
    final public const FORMER_MASTER_TEMPLATE_ID = 'ae65efdb-8414-4deb-bc81-26efdfc9560b';

    public function __construct(private readonly ManagerRegistry $registry)
    {
    }

    public function getMasterTemplate(): Procedure
    {
        return $this->registry->getRepository(Procedure::class)
            ->findOneBy(['masterTemplate' => true]);
    }

    public function getMasterTemplateId(): string
    {
        return $this->getMasterTemplate()->getId();
    }
}

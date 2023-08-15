<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;

final class ProcedureTemplateFactory extends ProcedureFactory
{
    public function __construct(GlobalConfigInterface $globalConfig)
    {
        parent::__construct($globalConfig);
    }

    protected function getDefaults(): array
    {
        $defaults = parent::getDefaults();

        $defaults['externalName'] = 'procedure Template';
        $defaults['master'] = true;
        $defaults['masterTemplate'] = false;
        $defaults['name'] = 'procedure Template';
        $defaults['phase'] = $this->globalConfig->getInternalPhaseKeys('hidden')[0];
        $defaults['publicParticipationPhase'] = $this->globalConfig->getExternalPhaseKeys('hidden')[0];

        return $defaults;
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Procedure::class;
    }

    public function asMasterTemplate(): self
    {
        return $this->addState(['masterTemplate' => true]);
    }
}

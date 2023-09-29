<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class PublicAffairsAgentNotFoundException extends ResourceNotFoundException
{
    public static function createFromId(string $id): PublicAffairsAgentNotFoundException
    {
        return new self("PublicAffairsAgent with ID {$id} was not found.");
    }
}

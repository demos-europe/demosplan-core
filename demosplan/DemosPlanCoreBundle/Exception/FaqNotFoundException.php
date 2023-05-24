<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class FaqNotFoundException extends ResourceNotFoundException
{
    /**
     * @return static
     */
    public static function createFromId(string $faqId): self
    {
        return new self("No FAQ with the ID {$faqId} available.");
    }
}
